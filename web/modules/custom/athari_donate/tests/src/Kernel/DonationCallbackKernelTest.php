<?php

declare(strict_types=1);

namespace Drupal\Tests\athari_donate\Kernel;

use Drupal\athari_donate\Controller\CallbackController;
use Drupal\athari_donate\DonationInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests donations and the callback endpoint against a real database.
 */
#[Group('athari_donate')]
#[RunTestsInSeparateProcesses]
class DonationCallbackKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'text', 'filter', 'options', 'node', 'views', 'athari_donate'];

  /**
   * The project that receives the test donations.
   */
  protected Node $project;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('athari_donation');
    $this->installConfig(['system', 'node', 'athari_donate']);
    NodeType::create(['type' => 'project', 'name' => 'Project'])->save();
    $this->project = Node::create(['type' => 'project', 'title' => 'Test project']);
    $this->project->save();
  }

  /**
   * Creates a donation that is waiting for its callback.
   */
  protected function pendingDonation(int $amount = 500): DonationInterface {
    $manager = $this->container->get('athari_donate.donation_manager');
    $donation = $manager->createPending($this->project, $amount, '254712345678');
    $donation->set('checkout_request_id', 'ws_CO_KERNEL_1');
    $donation->set('merchant_request_id', 'm-1');
    $donation->save();
    return $donation;
  }

  /**
   * Builds a successful callback body.
   */
  protected function successBody(float $amount = 500.0): string {
    return json_encode([
      'Body' => [
        'stkCallback' => [
          'MerchantRequestID' => 'm-1',
          'CheckoutRequestID' => 'ws_CO_KERNEL_1',
          'ResultCode' => 0,
          'ResultDesc' => 'The service request is processed successfully.',
          'CallbackMetadata' => [
            'Item' => [
        ['Name' => 'Amount', 'Value' => $amount],
        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
        ['Name' => 'TransactionDate', 'Value' => 20260117101530],
        ['Name' => 'PhoneNumber', 'Value' => 254712345678],
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a new donation is stored as pending with the right values.
   */
  public function testCreatePending(): void {
    $donation = $this->pendingDonation(750);
    $reloaded = $this->container->get('entity_type.manager')->getStorage('athari_donation')->load($donation->id());
    $this->assertSame(DonationInterface::STATUS_PENDING, $reloaded->getStatus());
    $this->assertSame(750, $reloaded->getAmount());
    $this->assertSame('254712345678', $reloaded->getPhone());
    $this->assertSame((int) $this->project->id(), (int) $reloaded->getProject()->id());
    $this->assertNotEmpty($reloaded->uuid());
  }

  /**
   * Tests the endpoint end to end: wrong token, right token, then a repeat.
   */
  public function testCallbackEndpoint(): void {
    $donation = $this->pendingDonation();
    $manager = $this->container->get('athari_donate.donation_manager');
    $controller = CallbackController::create($this->container);
    $storage = $this->container->get('entity_type.manager')->getStorage('athari_donation');

    // A wrong token is answered like any other request but changes nothing.
    $response = $controller->receive(Request::create('/', 'POST', [], [], [], [], $this->successBody()), str_repeat('0', 32));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['ResultCode' => 0, 'ResultDesc' => 'Accepted'], json_decode($response->getContent(), TRUE));
    $storage->resetCache();
    $this->assertSame(DonationInterface::STATUS_PENDING, $storage->load($donation->id())->getStatus());

    // Garbage with the right token is also ignored and still answered 200.
    $token = $manager->getCallbackSecret();
    $response = $controller->receive(Request::create('/', 'POST', [], [], [], [], 'not json'), $token);
    $this->assertSame(200, $response->getStatusCode());
    $storage->resetCache();
    $this->assertSame(DonationInterface::STATUS_PENDING, $storage->load($donation->id())->getStatus());

    // The right token and a valid body complete the donation.
    $response = $controller->receive(Request::create('/', 'POST', [], [], [], [], $this->successBody()), $token);
    $this->assertSame(200, $response->getStatusCode());
    $storage->resetCache();
    $saved = $storage->load($donation->id());
    $this->assertSame(DonationInterface::STATUS_SUCCESS, $saved->getStatus());
    $this->assertSame('NLJ7RT61SV', $saved->getReceipt());
    $changed = $saved->get('changed')->value;

    // Delivering the same callback again changes nothing.
    $controller->receive(Request::create('/', 'POST', [], [], [], [], $this->successBody()), $token);
    $storage->resetCache();
    $again = $storage->load($donation->id());
    $this->assertSame(DonationInterface::STATUS_SUCCESS, $again->getStatus());
    $this->assertSame($changed, $again->get('changed')->value);
  }

  /**
   * Tests that the callback token is stable and long enough to be unguessable.
   */
  public function testCallbackSecret(): void {
    $manager = $this->container->get('athari_donate.donation_manager');
    $secret = $manager->getCallbackSecret();
    $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $secret);
    $this->assertSame($secret, $manager->getCallbackSecret());
    $this->assertNotSame($secret, $manager->regenerateCallbackSecret());
  }

  /**
   * Tests that pending donations with no result eventually time out.
   */
  public function testExpirePending(): void {
    $donation = $this->pendingDonation();
    $donation->set('created', \Drupal::time()->getRequestTime() - 4000)->save();
    $this->assertSame(1, $this->container->get('athari_donate.donation_manager')->expirePending());
    $storage = $this->container->get('entity_type.manager')->getStorage('athari_donation');
    $storage->resetCache();
    $this->assertSame(DonationInterface::STATUS_FAILED, $storage->load($donation->id())->getStatus());
  }

}
