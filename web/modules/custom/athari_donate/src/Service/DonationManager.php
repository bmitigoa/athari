<?php

declare(strict_types=1);

namespace Drupal\athari_donate\Service;

use Drupal\athari_donate\DonationInterface;
use Drupal\athari_donate\Exception\DarajaException;
use Drupal\athari_donate\PaymentProviderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates donations, starts payments and applies Daraja's callback results.
 *
 * The provider (Daraja) only talks to the network. This class owns the rules:
 * what a donation looks like at each stage and how a callback changes it.
 */
class DonationManager {

  /**
   * State key holding the secret path segment of the callback URL.
   */
  public const STATE_CALLBACK_SECRET = 'athari_donate.callback_secret';

  /**
   * Result code Daraja sends when the donor cancels the prompt.
   */
  public const RESULT_CANCELLED = 1032;

  /**
   * Seconds after which a pending donation is treated as timed out.
   */
  public const PENDING_TIMEOUT = 1800;

  /**
   * Seconds to wait for the callback before asking Daraja directly.
   */
  public const QUERY_AFTER = 15;

  /**
   * Minimum seconds between two status queries for the same donation.
   */
  public const QUERY_INTERVAL = 8;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PaymentProviderInterface $provider,
    protected ConfigFactoryInterface $configFactory,
    protected StateInterface $state,
    protected UrlGeneratorInterface $urlGenerator,
    protected TimeInterface $time,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Returns the donation entity storage.
   */
  protected function storage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('athari_donation');
  }

  /**
   * Returns the secret that makes the callback URL unguessable.
   *
   * It is generated on first use and kept in state, so it is never exported.
   */
  public function getCallbackSecret(): string {
    $secret = (string) $this->state->get(self::STATE_CALLBACK_SECRET, '');
    if (!preg_match('/^[0-9a-f]{32}$/', $secret)) {
      $secret = $this->regenerateCallbackSecret();
    }
    return $secret;
  }

  /**
   * Replaces the callback secret and returns the new value.
   */
  public function regenerateCallbackSecret(): string {
    $secret = bin2hex(random_bytes(16));
    $this->state->set(self::STATE_CALLBACK_SECRET, $secret);
    return $secret;
  }

  /**
   * Returns the full callback URL that Daraja should call.
   *
   * If a public base URL is configured (for example a tunnel to a local site)
   * it is used instead of the site's own address.
   */
  public function getCallbackUrl(): string {
    $base = rtrim(trim((string) $this->configFactory->get('athari_donate.settings')->get('callback_base_url')), '/');
    $parameters = ['token' => $this->getCallbackSecret()];
    if ($base !== '') {
      return $base . $this->urlGenerator->generateFromRoute('athari_donate.callback', $parameters, ['absolute' => FALSE]);
    }
    return $this->urlGenerator->generateFromRoute('athari_donate.callback', $parameters, ['absolute' => TRUE]);
  }

  /**
   * Creates and saves a pending donation.
   */
  public function createPending(NodeInterface $project, int $amount, string $phone): DonationInterface {
    /** @var \Drupal\athari_donate\DonationInterface $donation */
    $donation = $this->storage()->create([
      'project' => $project->id(),
      'amount' => $amount,
      'phone' => $phone,
      'status' => DonationInterface::STATUS_PENDING,
    ]);
    $donation->save();
    return $donation;
  }

  /**
   * Asks the donor's phone to approve the payment.
   *
   * On success the donation stores Daraja's request identifiers. On failure it
   * is marked failed and the exception is passed on to the caller.
   *
   * @throws \Drupal\athari_donate\Exception\DarajaException
   */
  public function initiate(DonationInterface $donation): void {
    $project = $donation->getProject();
    try {
      $result = $this->provider->initiatePayment(
        $donation->getPhone(),
        $donation->getAmount(),
        'ATHARI' . $donation->id(),
        'Donation',
        $this->getCallbackUrl(),
      );
    }
    catch (DarajaException $e) {
      $donation->setStatus(DonationInterface::STATUS_FAILED);
      $donation->set('result_desc', substr($e->getMessage(), 0, 255));
      $donation->save();
      throw $e;
    }

    $donation->set('merchant_request_id', $result['merchant_request_id']);
    $donation->set('checkout_request_id', $result['checkout_request_id']);
    $donation->save();
    $this->logger->info('Donation @id sent to M-Pesa for project @project.', [
      '@id' => $donation->id(),
      '@project' => $project ? $project->id() : '?',
    ]);
  }

  /**
   * Loads a donation by UUID, or NULL.
   */
  public function loadByUuid(string $uuid): ?DonationInterface {
    $found = $this->storage()->loadByProperties(['uuid' => $uuid]);
    return $found ? reset($found) : NULL;
  }

  /**
   * Applies a Daraja STK callback payload to the matching donation.
   *
   * Safe to call more than once with the same payload: only a pending donation
   * is changed, so duplicate deliveries are ignored.
   *
   * @param array $payload
   *   The decoded JSON body Daraja posted.
   *
   * @return \Drupal\athari_donate\DonationInterface|null
   *   The matching donation, or NULL if the payload is malformed or unknown.
   */
  public function processCallback(array $payload): ?DonationInterface {
    $callback = $payload['Body']['stkCallback'] ?? NULL;
    if (!is_array($callback) || empty($callback['CheckoutRequestID']) || !isset($callback['ResultCode'])) {
      $this->logger->warning('Ignored a Daraja callback with an unexpected shape.');
      return NULL;
    }

    $found = $this->storage()->loadByProperties(['checkout_request_id' => (string) $callback['CheckoutRequestID']]);
    if (!$found) {
      $this->logger->warning('Daraja callback for an unknown CheckoutRequestID.');
      return NULL;
    }
    /** @var \Drupal\athari_donate\DonationInterface $donation */
    $donation = reset($found);

    $code = (int) $callback['ResultCode'];
    $meta = [];
    foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
      if (is_array($item) && isset($item['Name'])) {
        $meta[(string) $item['Name']] = $item['Value'] ?? NULL;
      }
    }

    if ($donation->getStatus() !== DonationInterface::STATUS_PENDING) {
      // A status query may already have marked the donation a success without
      // knowing the receipt number. The first successful callback adds it.
      if ($donation->getStatus() === DonationInterface::STATUS_SUCCESS && $donation->getReceipt() === '' && $code === 0 && !empty($meta['MpesaReceiptNumber'])) {
        $this->applyPaymentDetails($donation, $meta);
        $donation->save();
        $this->logger->info('Receipt added to donation @id from the callback.', ['@id' => $donation->id()]);
        return $donation;
      }
      $this->logger->info('Duplicate Daraja callback for donation @id ignored.', ['@id' => $donation->id()]);
      return $donation;
    }

    if ($code === 0) {
      $donation->setStatus(DonationInterface::STATUS_SUCCESS);
      $this->applyPaymentDetails($donation, $meta);
      $paid_amount = isset($meta['Amount']) ? (float) $meta['Amount'] : NULL;
      // Money arrived. If the amount differs from the request, keep the
      // payment as a success but note it, so a person can reconcile it.
      if ($paid_amount === NULL || abs($paid_amount - $donation->getAmount()) >= 0.01) {
        $callback['ResultDesc'] = trim(($callback['ResultDesc'] ?? '') . ' Amount note: paid ' . ($paid_amount ?? 'unknown') . ', expected ' . $donation->getAmount() . '.');
        $this->logger->warning('Donation @id: paid amount differs from the requested amount.', ['@id' => $donation->id()]);
      }
    }
    elseif ($code === self::RESULT_CANCELLED) {
      $donation->setStatus(DonationInterface::STATUS_CANCELLED);
    }
    else {
      $donation->setStatus(DonationInterface::STATUS_FAILED);
    }

    $donation->set('result_code', $code);
    $donation->set('result_desc', substr((string) ($callback['ResultDesc'] ?? ''), 0, 255));
    $donation->save();
    $this->logger->info('Donation @id is now @status (result code @code).', [
      '@id' => $donation->id(),
      '@status' => $donation->getStatus(),
      '@code' => $code,
    ]);
    return $donation;
  }

  /**
   * Copies the receipt number and payment time from callback metadata.
   *
   * @param \Drupal\athari_donate\DonationInterface $donation
   *   The donation to update (not saved here).
   * @param array $meta
   *   The callback metadata items keyed by Name.
   */
  protected function applyPaymentDetails(DonationInterface $donation, array $meta): void {
    $donation->set('mpesa_receipt', substr((string) ($meta['MpesaReceiptNumber'] ?? ''), 0, 32));
    if (!empty($meta['TransactionDate'])) {
      $paid = \DateTimeImmutable::createFromFormat('YmdHis', (string) $meta['TransactionDate'], new \DateTimeZone('Africa/Nairobi'));
      if ($paid) {
        $donation->set('paid', $paid->getTimestamp());
      }
    }
  }

  /**
   * Asks Daraja (STK Push Query) for the result of a still-pending donation.
   *
   * This is the fallback for when the callback cannot reach the site, for
   * example on a local development machine. It is throttled so a status page
   * that polls every few seconds does not flood Daraja, and it never throws:
   * on any error the donation simply stays pending.
   */
  public function refreshPending(DonationInterface $donation): DonationInterface {
    if ($donation->getStatus() !== DonationInterface::STATUS_PENDING || $donation->getCheckoutRequestId() === '') {
      return $donation;
    }
    $now = $this->time->getRequestTime();
    if ($now - (int) $donation->get('created')->value < self::QUERY_AFTER) {
      return $donation;
    }
    $key = 'athari_donate.last_query.' . $donation->id();
    if ($now - (int) $this->state->get($key, 0) < self::QUERY_INTERVAL) {
      return $donation;
    }
    $this->state->set($key, $now);

    try {
      $result = $this->provider->queryPayment($donation->getCheckoutRequestId());
    }
    catch (DarajaException $e) {
      return $donation;
    }

    $state = $result['state'] ?? 'processing';
    if ($state === 'processing') {
      return $donation;
    }
    $status = match ($state) {
      'success' => DonationInterface::STATUS_SUCCESS,
      'cancelled' => DonationInterface::STATUS_CANCELLED,
      default => DonationInterface::STATUS_FAILED,
    };
    $donation->setStatus($status);
    if ($result['result_code'] !== NULL) {
      $donation->set('result_code', (int) $result['result_code']);
    }
    $donation->set('result_desc', substr((string) ($result['result_desc'] ?? ''), 0, 255));
    $donation->save();
    $this->state->delete($key);
    $this->logger->info('Donation @id resolved by status query: @status.', [
      '@id' => $donation->id(),
      '@status' => $status,
    ]);
    return $donation;
  }

  /**
   * Marks donations that never received a callback as failed.
   *
   * @return int
   *   How many donations were timed out.
   */
  public function expirePending(): int {
    $cutoff = $this->time->getRequestTime() - self::PENDING_TIMEOUT;
    $ids = $this->storage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', DonationInterface::STATUS_PENDING)
      ->condition('created', $cutoff, '<')
      ->execute();
    foreach ($this->storage()->loadMultiple($ids) as $donation) {
      $donation->setStatus(DonationInterface::STATUS_FAILED);
      $donation->set('result_desc', 'No response from M-Pesa in time.');
      $donation->save();
    }
    return count($ids);
  }

  /**
   * Returns the public, non-sensitive fields of a donation for the status page.
   */
  public function statusData(DonationInterface $donation): array {
    return [
      'status' => $donation->getStatus(),
      'amount' => $donation->getAmount(),
      'receipt' => $donation->getReceipt(),
      'message' => $this->statusMessage($donation),
    ];
  }

  /**
   * A donor-friendly sentence for the donation's current status.
   */
  public function statusMessage(DonationInterface $donation): string {
    return match ($donation->getStatus()) {
      DonationInterface::STATUS_SUCCESS => 'Thank you! Your donation was received.',
      DonationInterface::STATUS_CANCELLED => 'The payment was cancelled on your phone. No money was taken.',
      DonationInterface::STATUS_FAILED => 'The payment did not go through. No money was taken. You are welcome to try again.',
      default => 'Check your phone and enter your M-Pesa PIN to approve the payment.',
    };
  }

}
