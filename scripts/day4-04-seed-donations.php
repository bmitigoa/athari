<?php

/**
 * @file
 * Day 4: sample donations so the totals and the admin report have data.
 *
 * Every row is fictional: phone numbers are in the 2547000000xx range and the
 * receipts start with SEED. Safe to re-run (receipts are unique).
 *
 * Run with: ddev drush php:script scripts/day4-04-seed-donations.php
 *
 * SUPERSEDED: Athari now targets Kenya. Use scripts/kenya-01-regions.php and
 * scripts/kenya-03-seed.php instead. This script is kept as a record of the
 * original Uganda build and would create Ugandan data if run.
 */

use Drupal\athari_donate\DonationInterface;

$storage = \Drupal::entityTypeManager()->getStorage('athari_donation');
$nodes = \Drupal::entityTypeManager()->getStorage('node');
$now = \Drupal::time()->getRequestTime();

// Project title => list of [amount KES, status, days ago].
$plan = [
  'Kawempe Maternal & Child Health Outreach' => [[5000, 'success', 52], [1000, 'success', 31], [20000, 'success', 12], [2500, 'failed', 9]],
  'Mbale Schools Learning Recovery Programme' => [[1500, 'success', 47], [10000, 'success', 26], [500, 'cancelled', 20]],
  'Gulu Returnee Livelihoods & Savings Groups' => [[2500, 'success', 40], [5000, 'success', 18]],
  'Mbarara Dairy Value Chain Strengthening' => [[7500, 'success', 35], [1000, 'success', 15], [3000, 'failed', 6]],
  'Fort Portal Tea Outgrowers Training' => [[2000, 'success', 29], [500, 'success', 10]],
  'Kasese Flood Response & Recovery' => [[10000, 'success', 24], [20000, 'success', 14], [5000, 'success', 5], [1000, 'cancelled', 3], [2500, 'success', 1]],
];

$created = 0;
$n = 0;
foreach ($plan as $title => $rows) {
  $found = $nodes->loadByProperties(['type' => 'project', 'title' => $title]);
  if (!$found) {
    print "Project not found: $title\n";
    continue;
  }
  $project = reset($found);
  foreach ($rows as [$amount, $status, $days]) {
    $n++;
    $receipt = sprintf('SEED%06d', $n);
    $success = $status === DonationInterface::STATUS_SUCCESS;
    if ($storage->loadByProperties(['checkout_request_id' => 'seed_' . $receipt])) {
      continue;
    }
    $when = $now - ($days * 86400) - random_int(0, 40000);
    $donation = $storage->create([
      'project' => $project->id(),
      'amount' => $amount,
      'phone' => sprintf('2547000000%02d', ($n % 90) + 10),
      'status' => $status,
      'mpesa_receipt' => $success ? $receipt : '',
      'merchant_request_id' => 'seed_m_' . $n,
      'checkout_request_id' => 'seed_' . $receipt,
      'result_code' => $success ? 0 : ($status === 'cancelled' ? 1032 : 1),
      'result_desc' => match ($status) {
        'success' => 'The service request is processed successfully.',
        'cancelled' => 'Request cancelled by user',
        default => 'The balance is insufficient for the transaction',
      },
      'paid' => $success ? $when + 25 : NULL,
      'created' => $when,
      'changed' => $when + 25,
    ]);
    $donation->save();
    $created++;
  }
}
print "Sample donations created: $created\n";
