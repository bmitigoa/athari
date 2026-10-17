<?php

/**
 * @file
 * Day 4: simulates the Safaricom callback for a donation, for local demos.
 *
 * The sandbox cannot approve a prompt on a real phone, so use this to see the
 * success, cancelled and failed paths. It builds the same JSON Safaricom sends
 * and passes it to DonationManager::processCallback(), exactly like the real
 * callback route does.
 *
 * Usage (arguments after --): [donation id] [success|cancelled|failed]
 *   ddev drush php:script scripts/day4-05-simulate-callback.php -- 12 success
 * Use 0 (or omit the id) for the latest pending donation.
 */

$storage = \Drupal::entityTypeManager()->getStorage('athari_donation');
$id = $extra[0] ?? NULL;
$outcome = $extra[1] ?? 'success';

if ($id === NULL || $id === '0' || $id === 'latest') {
  $ids = $storage->getQuery()->accessCheck(FALSE)->condition('status', 'pending')->sort('id', 'DESC')->range(0, 1)->execute();
  $id = reset($ids);
}
$donation = $id ? $storage->load($id) : NULL;
if (!$donation || $donation->getCheckoutRequestId() === '') {
  print "No suitable donation found (it needs a CheckoutRequestID).\n";
  return;
}

$callback = [
  'MerchantRequestID' => (string) $donation->get('merchant_request_id')->value,
  'CheckoutRequestID' => $donation->getCheckoutRequestId(),
];
switch ($outcome) {
  case 'success':
    $callback += [
      'ResultCode' => 0,
      'ResultDesc' => 'The service request is processed successfully.',
      'CallbackMetadata' => ['Item' => [
        ['Name' => 'Amount', 'Value' => (float) $donation->getAmount()],
        ['Name' => 'MpesaReceiptNumber', 'Value' => 'SIM' . strtoupper(substr(md5((string) microtime(TRUE)), 0, 7))],
        ['Name' => 'TransactionDate', 'Value' => (int) date('YmdHis')],
        ['Name' => 'PhoneNumber', 'Value' => (int) $donation->getPhone()],
      ]],
    ];
    break;

  case 'cancelled':
    $callback += ['ResultCode' => 1032, 'ResultDesc' => 'Request cancelled by user'];
    break;

  default:
    $callback += ['ResultCode' => 1, 'ResultDesc' => 'The balance is insufficient for the transaction'];
}

$result = \Drupal::service('athari_donate.donation_manager')->processCallback(['Body' => ['stkCallback' => $callback]]);
print 'Donation ' . $donation->id() . ' is now: ' . ($result ? $result->getStatus() : 'unchanged') . "\n";
