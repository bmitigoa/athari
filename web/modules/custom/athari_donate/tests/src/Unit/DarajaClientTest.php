<?php

declare(strict_types=1);

namespace Drupal\Tests\athari_donate\Unit;

use Drupal\athari_donate\Exception\DarajaException;
use Drupal\athari_donate\Service\DarajaClient;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests the Daraja client against a mocked HTTP layer (no network).
 */
#[Group('athari_donate')]
class DarajaClientTest extends UnitTestCase {

  /**
   * Requests captured by the history middleware.
   *
   * @var array
   */
  protected array $history = [];

  /**
   * Builds a client whose HTTP responses are the given list.
   */
  protected function client(array $responses, array $settings = []): DarajaClient {
    $this->history = [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($this->history));
    $time = $this->createMock(TimeInterface::class);
    // 2026-01-17 07:15:30 UTC, which is 10:15:30 in East Africa Time.
    $time->method('getRequestTime')->willReturn(1768634130);
    $defaults = [
      'environment' => 'sandbox',
      'shortcode' => '174379',
      'consumer_key' => 'test-key',
      'consumer_secret' => 'test-secret',
      'passkey' => 'test-passkey',
      'api_base_url' => '',
      'transaction_type' => 'CustomerPayBillOnline',
    ];
    return new DarajaClient(
      new Client(['handler' => $stack]),
      $this->getConfigFactoryStub(['athari_donate.settings' => $settings + $defaults]),
      new MemoryBackend($time),
      $time,
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Builds a JSON response.
   */
  protected function json(array $data, int $status = 200): Response {
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
  }

  /**
   * Tests that the token is requested once and then served from the cache.
   */
  public function testAccessTokenIsCached(): void {
    $client = $this->client([$this->json(['access_token' => 'tok-1', 'expires_in' => '3599'])]);
    $this->assertSame('tok-1', $client->getAccessToken());
    // The mock has no second response: a second HTTP call would throw.
    $this->assertSame('tok-1', $client->getAccessToken());
    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $this->assertSame('GET', $request->getMethod());
    $this->assertSame('/oauth/v1/generate', $request->getUri()->getPath());
    $this->assertStringContainsString('grant_type=client_credentials', $request->getUri()->getQuery());
    $this->assertSame('Basic ' . base64_encode('test-key:test-secret'), $request->getHeaderLine('Authorization'));
  }

  /**
   * Tests that a forced refresh bypasses the cache.
   */
  public function testRefreshBypassesCache(): void {
    $client = $this->client([
      $this->json(['access_token' => 'tok-1', 'expires_in' => '3599']),
      $this->json(['access_token' => 'tok-2', 'expires_in' => '3599']),
    ]);
    $this->assertSame('tok-1', $client->getAccessToken());
    $this->assertSame('tok-2', $client->getAccessToken(TRUE));
    $this->assertSame('tok-2', $client->getAccessToken());
  }

  /**
   * Tests the environment to base URL mapping and the override.
   */
  public function testBaseUrl(): void {
    $this->assertSame('https://sandbox.safaricom.co.ke', $this->client([])->getBaseUrl());
    $this->assertSame('https://api.safaricom.co.ke', $this->client([], ['environment' => 'production'])->getBaseUrl());
    $this->assertSame('http://mock:8099', $this->client([], ['api_base_url' => 'http://mock:8099/'])->getBaseUrl());
  }

  /**
   * Tests that missing credentials are reported and never sent.
   */
  public function testNotConfigured(): void {
    $client = $this->client([], ['passkey' => '']);
    $this->assertFalse($client->isConfigured());
    $this->expectException(DarajaException::class);
    $client->initiatePayment('254712345678', 100, 'REF', 'Donation', 'https://example.org/cb');
  }

  /**
   * Tests that the STK Push request has the shape Daraja requires.
   */
  public function testStkPushPayload(): void {
    $client = $this->client([
      $this->json(['access_token' => 'tok-1', 'expires_in' => '3599']),
      $this->json([
        'MerchantRequestID' => 'm-1',
        'CheckoutRequestID' => 'ws_CO_1',
        'ResponseCode' => '0',
        'CustomerMessage' => 'Success. Request accepted for processing',
      ]),
    ]);
    $result = $client->initiatePayment('254712345678', 250, 'ATHARI12345678901', 'Donation to project', 'https://example.org/cb/abc');
    $this->assertSame([
      'merchant_request_id' => 'm-1',
      'checkout_request_id' => 'ws_CO_1',
      'customer_message' => 'Success. Request accepted for processing',
    ], $result);

    $request = $this->history[1]['request'];
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame('/mpesa/stkpush/v1/processrequest', $request->getUri()->getPath());
    $this->assertSame('Bearer tok-1', $request->getHeaderLine('Authorization'));

    $body = json_decode((string) $request->getBody(), TRUE);
    $this->assertSame('174379', $body['BusinessShortCode']);
    $this->assertSame('20260117101530', $body['Timestamp']);
    $this->assertSame(base64_encode('174379test-passkey20260117101530'), $body['Password']);
    $this->assertSame('CustomerPayBillOnline', $body['TransactionType']);
    $this->assertSame(250, $body['Amount']);
    $this->assertSame('254712345678', $body['PartyA']);
    $this->assertSame('174379', $body['PartyB']);
    $this->assertSame('254712345678', $body['PhoneNumber']);
    $this->assertSame('https://example.org/cb/abc', $body['CallBackURL']);
    $this->assertSame('ATHARI123456', $body['AccountReference'], 'The reference is cut to 12 characters.');
    $this->assertLessThanOrEqual(13, strlen($body['TransactionDesc']));
  }

  /**
   * Tests that a Daraja rejection becomes a donor-safe exception.
   */
  public function testStkPushRejected(): void {
    $client = $this->client([
      $this->json(['access_token' => 'tok-1', 'expires_in' => '3599']),
      $this->json(['ResponseCode' => '1', 'ResponseDescription' => 'Rejected']),
    ]);
    $this->expectException(DarajaException::class);
    $this->expectExceptionMessage('M-Pesa could not start this payment');
    $client->initiatePayment('254712345678', 250, 'REF', 'Donation', 'https://example.org/cb');
  }

  /**
   * Tests that an expired cached token is replaced once and the call retried.
   */
  public function testExpiredTokenIsRefreshedOnce(): void {
    $client = $this->client([
      $this->json(['access_token' => 'old', 'expires_in' => '3599']),
      $this->json(['errorMessage' => 'Invalid Access Token'], 401),
      $this->json(['access_token' => 'new', 'expires_in' => '3599']),
      $this->json(['MerchantRequestID' => 'm-1', 'CheckoutRequestID' => 'ws_CO_1', 'ResponseCode' => '0']),
    ]);
    $result = $client->initiatePayment('254712345678', 100, 'REF', 'Donation', 'https://example.org/cb');
    $this->assertSame('ws_CO_1', $result['checkout_request_id']);
    $this->assertSame('Bearer new', $this->history[3]['request']->getHeaderLine('Authorization'));
  }

  /**
   * Result codes from the STK Push Query and the state each one maps to.
   */
  public static function queryProvider(): array {
    return [
      'paid' => [['ResultCode' => '0', 'ResultDesc' => 'The service request is processed successfully.'], 'success', 0],
      'cancelled' => [['ResultCode' => '1032', 'ResultDesc' => 'Request cancelled by user'], 'cancelled', 1032],
      'insufficient funds' => [['ResultCode' => '1', 'ResultDesc' => 'The balance is insufficient'], 'failed', 1],
      'wrong pin' => [['ResultCode' => '2001', 'ResultDesc' => 'The initiator information is invalid.'], 'failed', 2001],
      'timeout' => [['ResultCode' => '1037', 'ResultDesc' => 'DS timeout user cannot be reached'], 'failed', 1037],
      // Seen against the real sandbox: a 200 answer that is not a result yet.
      'still under processing' => [
        ['ResultCode' => '4999', 'ResultDesc' => 'The transaction is still under processing'],
        'processing',
        4999,
      ],
    ];
  }

  /**
   * Tests mapping of STK Push Query answers.
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('queryProvider')]
  public function testQueryMapsResultCodes(array $answer, string $state, int $code): void {
    $client = $this->client([
      $this->json(['access_token' => 'tok-1', 'expires_in' => '3599']),
      $this->json($answer + ['ResponseCode' => '0', 'CheckoutRequestID' => 'ws_CO_1']),
    ]);
    $result = $client->queryPayment('ws_CO_1');
    $this->assertSame($state, $result['state']);
    $this->assertSame($code, $result['result_code']);
    $this->assertSame('/mpesa/stkpushquery/v1/query', $this->history[1]['request']->getUri()->getPath());
    $body = json_decode((string) $this->history[1]['request']->getBody(), TRUE);
    $this->assertSame('ws_CO_1', $body['CheckoutRequestID']);
  }

  /**
   * Tests that "still being processed" is not treated as a failure.
   */
  public function testQueryStillProcessing(): void {
    $client = $this->client([
      $this->json(['access_token' => 'tok-1', 'expires_in' => '3599']),
      $this->json([
        'requestId' => 'x',
        'errorCode' => '500.001.1001',
        'errorMessage' => 'The transaction is being processed',
      ], 500),
    ]);
    $result = $client->queryPayment('ws_CO_1');
    $this->assertSame('processing', $result['state']);
    $this->assertNull($result['result_code']);
  }

}
