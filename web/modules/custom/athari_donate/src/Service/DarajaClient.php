<?php

declare(strict_types=1);

namespace Drupal\athari_donate\Service;

use Drupal\athari_donate\Exception\DarajaException;
use Drupal\athari_donate\PaymentProviderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;

/**
 * Talks to Safaricom Daraja: OAuth tokens and Lipa na M-Pesa STK Push.
 *
 * Credentials are read only from the athari_donate.settings config object and
 * are meant to be supplied by $config overrides in settings.local.php, which is
 * git-ignored. They are never saved by the settings form and never logged.
 */
class DarajaClient implements PaymentProviderInterface {

  /**
   * Daraja sandbox base URL.
   */
  public const SANDBOX_URL = 'https://sandbox.safaricom.co.ke';

  /**
   * Daraja production base URL.
   */
  public const PRODUCTION_URL = 'https://api.safaricom.co.ke';

  /**
   * Result code of the STK Query meaning "still being processed".
   */
  public const RESULT_STILL_PROCESSING = 4999;

  /**
   * The credential names handled by getCredential().
   */
  public const CREDENTIALS = ['consumer_key', 'consumer_secret', 'passkey'];

  public function __construct(
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
    protected CacheBackendInterface $cache,
    protected TimeInterface $time,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return 'mpesa';
  }

  /**
   * Returns "sandbox" or "production".
   */
  public function getEnvironment(): string {
    return $this->configFactory->get('athari_donate.settings')->get('environment') === 'production' ? 'production' : 'sandbox';
  }

  /**
   * Returns the API base URL without a trailing slash.
   */
  public function getBaseUrl(): string {
    $override = trim((string) $this->configFactory->get('athari_donate.settings')->get('api_base_url'));
    if ($override !== '') {
      return rtrim($override, '/');
    }
    return $this->getEnvironment() === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL;
  }

  /**
   * Returns a credential from config (the settings.local.php override).
   */
  public function getCredential(string $name): string {
    return trim((string) $this->configFactory->get('athari_donate.settings')->get($name));
  }

  /**
   * Whether a credential is set (without exposing its value).
   */
  public function isOverridden(string $name): bool {
    return $this->getCredential($name) !== '';
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    $shortcode = trim((string) $this->configFactory->get('athari_donate.settings')->get('shortcode'));
    if ($shortcode === '') {
      return FALSE;
    }
    foreach (self::CREDENTIALS as $name) {
      if ($this->getCredential($name) === '') {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Returns an OAuth access token, cached until shortly before it expires.
   *
   * @param bool $refresh
   *   Skip the cache and request a new token.
   *
   * @throws \Drupal\athari_donate\Exception\DarajaException
   */
  public function getAccessToken(bool $refresh = FALSE): string {
    $key = $this->getCredential('consumer_key');
    $secret = $this->getCredential('consumer_secret');
    if ($key === '' || $secret === '') {
      throw new DarajaException('Mobile-money payments are not configured yet.');
    }

    // The cache id includes the environment, base URL and a hash of the key, so
    // changing credentials or environment never reuses an old token.
    $cid = 'athari_donate:token:' . $this->getEnvironment() . ':' . hash('sha256', $this->getBaseUrl() . '|' . $key);
    if (!$refresh && ($cached = $this->cache->get($cid)) && !empty($cached->data)) {
      return (string) $cached->data;
    }

    try {
      $response = $this->httpClient->request('GET', $this->getBaseUrl() . '/oauth/v1/generate', [
        'query' => ['grant_type' => 'client_credentials'],
        'auth' => [$key, $secret],
        'timeout' => 15,
        'headers' => ['Accept' => 'application/json'],
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Daraja token request failed: @message', ['@message' => $this->describe($e)]);
      throw new DarajaException('We could not reach M-Pesa. Please try again in a few minutes.', 0, $e);
    }

    $token = is_array($data) ? (string) ($data['access_token'] ?? '') : '';
    if ($token === '') {
      $this->logger->error('Daraja token response had no access_token.');
      throw new DarajaException('We could not reach M-Pesa. Please try again in a few minutes.');
    }

    $lifetime = max(60, (int) ($data['expires_in'] ?? 3599) - 120);
    $this->cache->set($cid, $token, $this->time->getRequestTime() + $lifetime);
    return $token;
  }

  /**
   * Builds the STK Push password: base64(shortcode + passkey + timestamp).
   */
  public static function buildPassword(string $shortcode, string $passkey, string $timestamp): string {
    return base64_encode($shortcode . $passkey . $timestamp);
  }

  /**
   * Returns the Daraja timestamp (YYYYMMDDHHMMSS) in East Africa Time.
   */
  public function buildTimestamp(): string {
    return (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->setTimezone(new \DateTimeZone('Africa/Nairobi'))
      ->format('YmdHis');
  }

  /**
   * {@inheritdoc}
   */
  public function initiatePayment(string $phone, int $amount, string $reference, string $description, string $callback_url): array {
    $settings = $this->configFactory->get('athari_donate.settings');
    $shortcode = trim((string) $settings->get('shortcode'));
    if (!$this->isConfigured()) {
      throw new DarajaException('Mobile-money payments are not configured yet.');
    }

    $timestamp = $this->buildTimestamp();
    $payload = [
      'BusinessShortCode' => $shortcode,
      'Password' => self::buildPassword($shortcode, $this->getCredential('passkey'), $timestamp),
      'Timestamp' => $timestamp,
      'TransactionType' => (string) ($settings->get('transaction_type') ?: 'CustomerPayBillOnline'),
      'Amount' => $amount,
      'PartyA' => $phone,
      'PartyB' => $shortcode,
      'PhoneNumber' => $phone,
      'CallBackURL' => $callback_url,
      'AccountReference' => substr($reference, 0, 12),
      'TransactionDesc' => substr($description, 0, 13),
    ];

    $data = $this->postStkPush($payload, FALSE);

    if ((string) ($data['ResponseCode'] ?? '') !== '0' || empty($data['CheckoutRequestID'])) {
      $this->logger->warning('Daraja rejected the STK Push: @body', ['@body' => $this->truncate(json_encode($this->redact($data)))]);
      throw new DarajaException('M-Pesa could not start this payment. Please check the phone number and try again.');
    }

    return [
      'merchant_request_id' => (string) ($data['MerchantRequestID'] ?? ''),
      'checkout_request_id' => (string) $data['CheckoutRequestID'],
      'customer_message' => (string) ($data['CustomerMessage'] ?? ''),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Uses the STK Push Query API. While the donor has not answered the prompt,
   * Daraja replies with an error saying the request is still being processed;
   * that is reported as the "processing" state, not as a failure.
   */
  public function queryPayment(string $checkout_request_id): array {
    if (!$this->isConfigured()) {
      throw new DarajaException('Mobile-money payments are not configured yet.');
    }
    $shortcode = trim((string) $this->configFactory->get('athari_donate.settings')->get('shortcode'));
    $timestamp = $this->buildTimestamp();
    $payload = [
      'BusinessShortCode' => $shortcode,
      'Password' => self::buildPassword($shortcode, $this->getCredential('passkey'), $timestamp),
      'Timestamp' => $timestamp,
      'CheckoutRequestID' => $checkout_request_id,
    ];

    $data = $this->postQuery($payload, FALSE);
    if (!empty($data['processing'])) {
      return ['state' => 'processing', 'result_code' => NULL, 'result_desc' => 'The request is being processed.'];
    }

    $code = (int) ($data['ResultCode'] ?? -1);
    $state = match (TRUE) {
      !isset($data['ResultCode']) => 'processing',
      // 4999 is Daraja's "still under processing": not a result yet.
      $code === self::RESULT_STILL_PROCESSING => 'processing',
      $code === 0 => 'success',
      $code === 1032 => 'cancelled',
      default => 'failed',
    };
    return [
      'state' => $state,
      'result_code' => isset($data['ResultCode']) ? $code : NULL,
      'result_desc' => (string) ($data['ResultDesc'] ?? ''),
    ];
  }

  /**
   * Sends the STK Push Query request, retrying once on an expired token.
   *
   * @return array
   *   The decoded response, or ['processing' => TRUE] when Daraja says the
   *   transaction is still being processed.
   *
   * @throws \Drupal\athari_donate\Exception\DarajaException
   */
  protected function postQuery(array $payload, bool $is_retry): array {
    $token = $this->getAccessToken($is_retry);
    try {
      $response = $this->httpClient->request('POST', $this->getBaseUrl() . '/mpesa/stkpushquery/v1/query', [
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
        'json' => $payload,
        'timeout' => 20,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (ClientException | ServerException $e) {
      $status = $e->getResponse()->getStatusCode();
      $body = (string) $e->getResponse()->getBody();
      if ($status === 401 && !$is_retry) {
        return $this->postQuery($payload, TRUE);
      }
      // Daraja answers 500 "being processed" until the donor responds.
      if (stripos($body, 'being processed') !== FALSE || stripos($body, '500.001.1001') !== FALSE) {
        return ['processing' => TRUE];
      }
      $this->logger->error('Daraja STK Query failed: @message', ['@message' => $this->describe($e)]);
      throw new DarajaException('We could not check the payment status. Please try again shortly.', 0, $e);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Daraja STK Query failed: @message', ['@message' => $this->describe($e)]);
      throw new DarajaException('We could not reach M-Pesa. Please try again in a few minutes.', 0, $e);
    }
    return is_array($data) ? $data : [];
  }

  /**
   * Sends the STK Push request, retrying once if the cached token was rejected.
   *
   * @throws \Drupal\athari_donate\Exception\DarajaException
   */
  protected function postStkPush(array $payload, bool $is_retry): array {
    $token = $this->getAccessToken($is_retry);
    try {
      $response = $this->httpClient->request('POST', $this->getBaseUrl() . '/mpesa/stkpush/v1/processrequest', [
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
        'json' => $payload,
        'timeout' => 30,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (ClientException $e) {
      // An expired or revoked token returns 401: fetch a fresh one once.
      if (!$is_retry && $e->getResponse()->getStatusCode() === 401) {
        return $this->postStkPush($payload, TRUE);
      }
      $this->logger->error('Daraja STK Push failed: @message', ['@message' => $this->describe($e)]);
      throw new DarajaException('M-Pesa could not start this payment. Please check the phone number and try again.', 0, $e);
    }
    catch (GuzzleException $e) {
      $this->logger->error('Daraja STK Push failed: @message', ['@message' => $this->describe($e)]);
      throw new DarajaException('We could not reach M-Pesa. Please try again in a few minutes.', 0, $e);
    }
    return is_array($data) ? $data : [];
  }

  /**
   * Describes a Guzzle exception without leaking request headers or secrets.
   */
  protected function describe(\Throwable $e): string {
    $text = get_class($e);
    if ($e instanceof RequestException && $e->getResponse()) {
      $text .= ' HTTP ' . $e->getResponse()->getStatusCode() . ' ' . $this->truncate((string) $e->getResponse()->getBody());
    }
    return $text;
  }

  /**
   * Removes anything credential-like from a response before it is logged.
   */
  protected function redact(array $data): array {
    unset($data['access_token'], $data['Password']);
    return $data;
  }

  /**
   * Shortens text for log messages.
   */
  protected function truncate(string|false $text, int $length = 500): string {
    return substr((string) $text, 0, $length);
  }

}
