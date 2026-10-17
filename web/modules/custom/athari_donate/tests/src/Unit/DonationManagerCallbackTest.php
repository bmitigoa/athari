<?php

declare(strict_types=1);

namespace Drupal\Tests\athari_donate\Unit;

use Drupal\athari_donate\DonationInterface;
use Drupal\athari_donate\PaymentProviderInterface;
use Drupal\athari_donate\Service\DonationManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests how Daraja callback payloads change a donation.
 *
 * The donation is an in-memory fake, so these tests need no database.
 */
#[Group('athari_donate')]
class DonationManagerCallbackTest extends UnitTestCase {

  /**
   * Values held by the fake donation.
   *
   * @var array
   */
  protected array $values;

  /**
   * How many times the fake donation was saved.
   */
  protected int $saves = 0;

  /**
   * The manager under test.
   */
  protected DonationManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->saves = 0;
    $this->values = [
      'status' => DonationInterface::STATUS_PENDING,
      'amount' => 500,
      'mpesa_receipt' => '',
      'result_code' => NULL,
      'result_desc' => '',
      'paid' => NULL,
    ];

    $donation = $this->createMock(DonationInterface::class);
    $donation->method('id')->willReturn(7);
    $donation->method('getStatus')->willReturnCallback(fn() => $this->values['status']);
    $donation->method('setStatus')->willReturnCallback(function (string $status) use ($donation) {
      $this->values['status'] = $status;
      return $donation;
    });
    $donation->method('getAmount')->willReturnCallback(fn() => $this->values['amount']);
    $donation->method('getReceipt')->willReturnCallback(fn() => $this->values['mpesa_receipt']);
    $donation->method('set')->willReturnCallback(function (string $field, $value) use ($donation) {
      $this->values[$field] = $value;
      return $donation;
    });
    $donation->method('save')->willReturnCallback(function () {
      $this->saves++;
      return 2;
    });

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturnCallback(
      fn(array $properties) => ($properties['checkout_request_id'] ?? '') === 'ws_CO_TEST_1' ? [7 => $donation] : []
    );
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturn($storage);

    $this->manager = new DonationManager(
      $entity_type_manager,
      $this->createMock(PaymentProviderInterface::class),
      $this->getConfigFactoryStub(['athari_donate.settings' => []]),
      $this->createMock(StateInterface::class),
      $this->createMock(UrlGeneratorInterface::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * Builds a Daraja STK callback body.
   */
  protected function payload(int $code, string $desc, array $items = [], string $checkout = 'ws_CO_TEST_1'): array {
    $callback = [
      'MerchantRequestID' => '29115-34620561-1',
      'CheckoutRequestID' => $checkout,
      'ResultCode' => $code,
      'ResultDesc' => $desc,
    ];
    if ($items) {
      $callback['CallbackMetadata'] = ['Item' => $items];
    }
    return ['Body' => ['stkCallback' => $callback]];
  }

  /**
   * A successful payment as Daraja sends it.
   */
  protected function successItems(float $amount = 500.0): array {
    return [
      ['Name' => 'Amount', 'Value' => $amount],
      ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
      ['Name' => 'TransactionDate', 'Value' => 20260117101530],
      ['Name' => 'PhoneNumber', 'Value' => 254712345678],
    ];
  }

  /**
   * Tests a successful callback.
   */
  public function testSuccess(): void {
    $result = $this->manager->processCallback($this->payload(0, 'The service request is processed successfully.', $this->successItems()));
    $this->assertNotNull($result);
    $this->assertSame(DonationInterface::STATUS_SUCCESS, $this->values['status']);
    $this->assertSame('NLJ7RT61SV', $this->values['mpesa_receipt']);
    $this->assertSame(0, $this->values['result_code']);
    $this->assertSame('The service request is processed successfully.', $this->values['result_desc']);
    // 2026-01-17 10:15:30 East Africa Time is 07:15:30 UTC.
    $this->assertSame((new \DateTimeImmutable('2026-01-17 07:15:30', new \DateTimeZone('UTC')))->getTimestamp(), $this->values['paid']);
    $this->assertSame(1, $this->saves);
  }

  /**
   * Tests that the donor cancelling the prompt is recorded as cancelled.
   */
  public function testCancelled(): void {
    $this->manager->processCallback($this->payload(1032, 'Request cancelled by user'));
    $this->assertSame(DonationInterface::STATUS_CANCELLED, $this->values['status']);
    $this->assertSame(1032, $this->values['result_code']);
    $this->assertSame('', $this->values['mpesa_receipt']);
  }

  /**
   * Tests that other non-zero result codes are recorded as failed.
   */
  public function testFailed(): void {
    $this->manager->processCallback($this->payload(1, 'The balance is insufficient for the transaction'));
    $this->assertSame(DonationInterface::STATUS_FAILED, $this->values['status']);
    $this->assertSame(1, $this->values['result_code']);
    $this->assertSame('The balance is insufficient for the transaction', $this->values['result_desc']);
  }

  /**
   * Tests that a repeated callback does not change a finished donation.
   */
  public function testDuplicateSuccessIsIdempotent(): void {
    $payload = $this->payload(0, 'OK', $this->successItems());
    $this->manager->processCallback($payload);
    $this->assertSame(1, $this->saves);

    $this->manager->processCallback($payload);
    $this->manager->processCallback($payload);
    $this->assertSame(1, $this->saves, 'Duplicate callbacks must not save the donation again.');
    $this->assertSame(DonationInterface::STATUS_SUCCESS, $this->values['status']);
  }

  /**
   * Tests that a late failure cannot overwrite a successful donation.
   */
  public function testLateFailureDoesNotOverwriteSuccess(): void {
    $this->manager->processCallback($this->payload(0, 'OK', $this->successItems()));
    $this->manager->processCallback($this->payload(1, 'Some later failure'));
    $this->assertSame(DonationInterface::STATUS_SUCCESS, $this->values['status']);
    $this->assertSame('NLJ7RT61SV', $this->values['mpesa_receipt']);
  }

  /**
   * Tests that a callback arriving after a status query adds the receipt.
   */
  public function testCallbackAddsReceiptAfterQueryConfirmedSuccess(): void {
    // The STK Query already marked the donation a success, without a receipt.
    $this->values['status'] = DonationInterface::STATUS_SUCCESS;
    $this->manager->processCallback($this->payload(0, 'OK', $this->successItems()));
    $this->assertSame('NLJ7RT61SV', $this->values['mpesa_receipt']);
    $this->assertSame(1, $this->saves);

    // A second delivery changes nothing.
    $this->manager->processCallback($this->payload(0, 'OK', $this->successItems()));
    $this->assertSame(1, $this->saves);
  }

  /**
   * Tests that a different paid amount is noted but still counted as a success.
   */
  public function testAmountMismatchIsNoted(): void {
    $this->manager->processCallback($this->payload(0, 'OK', $this->successItems(300.0)));
    $this->assertSame(DonationInterface::STATUS_SUCCESS, $this->values['status']);
    $this->assertStringContainsString('Amount note', $this->values['result_desc']);
  }

  /**
   * Tests payloads that must be ignored without touching any donation.
   */
  public function testMalformedAndUnknownPayloadsAreIgnored(): void {
    $this->assertNull($this->manager->processCallback([]));
    $this->assertNull($this->manager->processCallback(['Body' => ['stkCallback' => ['ResultCode' => 0]]]));
    $this->assertNull($this->manager->processCallback(['Body' => ['stkCallback' => ['CheckoutRequestID' => 'x']]]));
    $this->assertNull($this->manager->processCallback($this->payload(0, 'OK', $this->successItems(), 'ws_CO_UNKNOWN')));
    $this->assertSame(0, $this->saves);
    $this->assertSame(DonationInterface::STATUS_PENDING, $this->values['status']);
  }

}
