<?php

/**
 * @file
 * Kenya: seeds partners, locations, projects, reports and sample donations.
 *
 * Reads scripts/seed/kenya-data.php. Reusable and idempotent: anything whose
 * title (or receipt) already exists is skipped, and nothing is ever deleted, so
 * it can be run again at any time. Needs the Region terms from
 * kenya-01-regions.php and the test users from day2-03-users.php.
 *
 * Reports are saved as drafts and then moved through the real moderation
 * transitions, so each one has a genuine revision history. Money is in KES.
 *
 * Run with: ddev drush php:script scripts/kenya-03-seed.php
 */

use Drupal\athari_donate\DonationInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

$data = require __DIR__ . '/seed/kenya-data.php';
$tm = \Drupal::entityTypeManager();

/**
 * Finds a node by type and title.
 */
function athari_kenya_find(string $type, string $title): ?Node {
  $found = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => $type, 'title' => $title]);
  return $found ? reset($found) : NULL;
}

/**
 * Returns the id of a Region term (a county) by name.
 */
function athari_kenya_county(string $name): int {
  $found = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'region', 'name' => $name]);
  // Nairobi is both a province and a county. Counties are the terms that have
  // a parent, so prefer those.
  foreach ($found as $term) {
    if (array_filter(array_column($term->get('parent')->getValue(), 'target_id'))) {
      return (int) $term->id();
    }
  }
  if (!$found) {
    throw new \RuntimeException("Missing Region term: $name. Run scripts/kenya-01-regions.php first.");
  }
  return (int) reset($found)->id();
}

/**
 * Returns the id of a Sector term by name.
 */
function athari_kenya_sector(string $name): int {
  $found = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'sector', 'name' => $name]);
  if (!$found) {
    throw new \RuntimeException("Missing Sector term: $name");
  }
  return (int) reset($found)->id();
}

// Run as user 1 so every moderation transition is allowed while seeding.
\Drupal::service('account_switcher')->switchTo(User::load(1));
$officer = user_load_by_name('fieldofficer');
$admin = user_load_by_name('ngoadmin');
if (!$officer || !$admin) {
  throw new \RuntimeException('Run scripts/day2-03-users.php first.');
}

$counts = ['partner' => 0, 'location' => 0, 'project' => 0, 'report' => 0, 'donation' => 0, 'repaired' => 0];

// Partners.
$partner_ids = [];
foreach ($data['partners'] as $name => [$url, $street, $town]) {
  $node = athari_kenya_find('partner', $name);
  if (!$node) {
    $node = Node::create([
      'type' => 'partner', 'title' => $name, 'uid' => $admin->id(), 'status' => 1,
      'field_website' => ['uri' => $url, 'title' => ''],
      'field_address' => ['country_code' => 'KE', 'locality' => $town, 'address_line1' => $street],
    ]);
    $node->save();
    $counts['partner']++;
  }
  $partner_ids[$name] = $node->id();
}

// Locations.
$location_ids = [];
foreach ($data['locations'] as $name => [$county, $lat, $lon]) {
  $node = athari_kenya_find('location', $name);
  if (!$node) {
    $node = Node::create([
      'type' => 'location', 'title' => $name, 'uid' => $admin->id(), 'status' => 1,
      'field_district' => athari_kenya_county($county),
      'field_coordinates' => sprintf('POINT (%.4f %.4f)', $lon, $lat),
    ]);
    $node->save();
    $counts['location']++;
  }
  elseif ((int) $node->get('field_district')->target_id !== athari_kenya_county($county)) {
    // Self-heal: point an existing location at the right county term.
    $node->set('field_district', athari_kenya_county($county));
    $node->save();
    $counts['repaired']++;
  }
  $location_ids[$name] = $node->id();
}

// Projects.
$project_ids = [];
foreach ($data['projects'] as $title => $p) {
  $node = athari_kenya_find('project', $title);
  if (!$node) {
    $node = Node::create([
      'type' => 'project', 'title' => $title, 'uid' => $admin->id(), 'status' => 1,
      'body' => ['value' => '<p>' . $p['body'] . '</p>', 'format' => 'basic_html'],
      'field_budget' => $p['budget'],
      'field_start_date' => $p['start'],
      'field_end_date' => $p['end'],
      'field_status' => $p['status'],
      'field_sector' => athari_kenya_sector($p['sector']),
      'field_region' => athari_kenya_county($p['county']),
      'field_locations' => array_map(fn($l) => ['target_id' => $location_ids[$l]], $p['locations']),
      'field_partners' => array_map(fn($n) => ['target_id' => $partner_ids[$n]], $p['partners']),
    ]);
    $node->save();
    $counts['project']++;
  }
  elseif ((int) $node->get('field_region')->target_id !== athari_kenya_county($p['county'])) {
    $node->set('field_region', athari_kenya_county($p['county']));
    $node->save();
    $counts['repaired']++;
  }
  $project_ids[$title] = $node->id();
}

// Reports, moved through the moderation workflow.
$path = [
  'draft' => [],
  'in_review' => ['in_review'],
  'published' => ['in_review', 'published'],
  'archived' => ['in_review', 'published', 'archived'],
];
foreach ($data['reports'] as $project_title => $rows) {
  $short = $data['projects'][$project_title]['short'];
  foreach ($rows as [$date, $beneficiaries, $funds, $state, $who, $text]) {
    $title = $short . ' field report, ' . date('F Y', strtotime($date));
    if (athari_kenya_find('report', $title)) {
      continue;
    }
    $author = $who === 'a' ? $admin : $officer;
    $node = Node::create([
      'type' => 'report', 'title' => $title, 'uid' => $author->id(),
      'created' => strtotime($date . ' 12:00:00'),
      'field_project' => $project_ids[$project_title],
      'field_report_date' => $date,
      'field_beneficiaries' => $beneficiaries,
      'field_funds_spent' => $funds,
      'field_narrative' => ['value' => '<p>' . $text . '</p>', 'format' => 'basic_html'],
      'moderation_state' => 'draft',
    ]);
    $node->setRevisionUserId($author->id());
    $node->setRevisionLogMessage('Seeded sample report (draft).');
    $node->save();
    foreach ($path[$state] as $step) {
      $node->set('moderation_state', $step);
      $node->setNewRevision(TRUE);
      // A state-only change touches no field; the review queue relies on this flag.
      $node->setRevisionTranslationAffected(TRUE);
      $node->setRevisionLogMessage("Seeded sample report ($step).");
      $node->save();
    }
    $counts['report']++;
  }
}

// Sample donations. Fictional numbers 2547000000xx; receipts start with SEED.
$donations = $tm->getStorage('athari_donation');
$now = \Drupal::time()->getRequestTime();
$seq = 0;
foreach ($data['donations'] as $project_title => $rows) {
  foreach ($rows as [$amount, $status, $days]) {
    $seq++;
    $receipt = sprintf('SEED%06d', $seq);
    if ($donations->loadByProperties(['checkout_request_id' => 'seed_' . $receipt])) {
      continue;
    }
    $success = $status === DonationInterface::STATUS_SUCCESS;
    $when = $now - ($days * 86400) - random_int(0, 40000);
    $donations->create([
      'project' => $project_ids[$project_title],
      'amount' => $amount,
      'phone' => sprintf('2547000000%02d', ($seq % 90) + 10),
      'status' => $status,
      'mpesa_receipt' => $success ? $receipt : '',
      'merchant_request_id' => 'seed_m_' . $seq,
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
    ])->save();
    $counts['donation']++;
  }
}

\Drupal::service('account_switcher')->switchBack();
print 'Created this run: ' . implode(', ', array_map(fn($k, $v) => "$v $k", array_keys($counts), $counts)) . "\n";
