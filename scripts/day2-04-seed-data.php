<?php

/**
 * @file
 * Day 2: seeds realistic sample content for Athari.
 *
 * Creates 5 partners, 15 locations (real Ugandan coordinates), 10 projects and
 * 30 reports in mixed moderation states. Idempotent: anything whose title
 * already exists is skipped, so it is safe to re-run.
 *
 * All organisations are fictional (example.org addresses). Figures are
 * illustrative sample data, not real programme results.
 *
 * Run with: ddev drush php:script scripts/day2-04-seed-data.php
 */

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

$tm = \Drupal::entityTypeManager();

/**
 * Finds a node by type and title.
 */
function athari_seed_find(string $type, string $title): ?Node {
  $found = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => $type, 'title' => $title]);
  return $found ? reset($found) : NULL;
}

/**
 * Finds a taxonomy term id by vocabulary and name.
 */
function athari_seed_term(string $vid, string $name): int {
  $found = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
  if (!$found) {
    throw new \RuntimeException("Missing $vid term: $name");
  }
  return (int) reset($found)->id();
}

// Run as user 1 so every moderation transition is permitted while seeding.
\Drupal::service('account_switcher')->switchTo(User::load(1));

$officer = user_load_by_name('fieldofficer');
$admin = user_load_by_name('ngoadmin');
if (!$officer || !$admin) {
  throw new \RuntimeException('Run scripts/day2-03-users.php first.');
}

// ---------------------------------------------------------------------------
// Partners (fictional organisations).
// ---------------------------------------------------------------------------
$partners = [
  'Mwangaza Community Health Trust' => ['https://mwangaza.example.org', 'Plot 14, Kawempe-Mulago Road', 'Kampala'],
  'Bright Futures Education Foundation' => ['https://brightfutures.example.org', 'Republic Street, Mbale Municipality', 'Mbale'],
  'Pearl Water & Sanitation Initiative' => ['https://pearlwash.example.org', 'Gulu-Juba Road, Pece Division', 'Gulu'],
  'Harvest Hope Livelihoods Cooperative' => ['https://harvesthope.example.org', 'High Street, Mbarara City', 'Mbarara'],
  'Safe Haven Child Protection Network' => ['https://safehaven.example.org', 'Obote Avenue, Lira City', 'Lira'],
];
$partner_ids = [];
foreach ($partners as $name => [$url, $street, $city]) {
  $node = athari_seed_find('partner', $name) ?? Node::create([
    'type' => 'partner',
    'title' => $name,
    'uid' => $admin->id(),
    'status' => 1,
    'field_website' => ['uri' => $url, 'title' => ''],
    'field_address' => ['country_code' => 'UG', 'locality' => $city, 'address_line1' => $street],
  ]);
  if ($node->isNew()) {
    $node->save();
  }
  $partner_ids[$name] = $node->id();
}

// ---------------------------------------------------------------------------
// Locations: name => [district term, latitude, longitude].
// ---------------------------------------------------------------------------
$locations = [
  'Kawempe Health Centre IV' => ['Kampala', 0.3790, 32.5560],
  'Wakiso Town Community Centre' => ['Wakiso', 0.4044, 32.4594],
  'Masaka Referral Hospital Outreach' => ['Masaka', -0.3382, 31.7345],
  'Mbale Municipality Primary School' => ['Mbale', 1.0821, 34.1750],
  'Tororo Primary School Cluster' => ['Tororo', 0.6928, 34.1810],
  'Soroti Rural Water Scheme' => ['Soroti', 1.7147, 33.6111],
  'Gulu Returnee Resource Centre' => ['Gulu', 2.7746, 32.2990],
  'Kitgum Town Vocational Centre' => ['Kitgum', 3.2889, 32.8858],
  "Lira Girls' Safe Space" => ['Lira', 2.2499, 32.8998],
  'Arua Town Water Point' => ['Arua', 3.0201, 30.9110],
  'Mbarara Dairy Farmers Hub' => ['Mbarara', -0.6072, 30.6545],
  'Fort Portal Tea Outgrowers Hub' => ['Kabarole', 0.6710, 30.2750],
  'Jinja Youth Skills Centre' => ['Jinja', 0.4244, 33.2041],
  'Mukono Town Skills Annex' => ['Mukono', 0.3533, 32.7553],
  'Kasese Flood Recovery Camp' => ['Kasese', 0.1833, 30.0833],
];
$location_ids = [];
foreach ($locations as $name => [$district, $lat, $lon]) {
  $node = athari_seed_find('location', $name) ?? Node::create([
    'type' => 'location',
    'title' => $name,
    'uid' => $admin->id(),
    'status' => 1,
    'field_district' => athari_seed_term('region', $district),
    'field_coordinates' => sprintf('POINT (%.4f %.4f)', $lon, $lat),
  ]);
  if ($node->isNew()) {
    $node->save();
  }
  $location_ids[$name] = $node->id();
}

// ---------------------------------------------------------------------------
// Projects.
// ---------------------------------------------------------------------------
$projects = [
  'Kawempe Maternal & Child Health Outreach' => [
    'sector' => 'Health', 'region' => 'Kampala', 'budget' => 185000, 'start' => '2025-03-01', 'end' => '2027-02-28', 'status' => 'active',
    'locations' => ['Kawempe Health Centre IV', 'Wakiso Town Community Centre', 'Masaka Referral Hospital Outreach'],
    'partners' => ['Mwangaza Community Health Trust'],
    'body' => 'Community-based antenatal, postnatal and child immunisation outreach for peri-urban Kampala and Wakiso, run through health centres and trained village health teams.',
  ],
  'Mbale Schools Learning Recovery Programme' => [
    'sector' => 'Education', 'region' => 'Mbale', 'budget' => 120000, 'start' => '2025-01-15', 'end' => '2026-12-15', 'status' => 'active',
    'locations' => ['Mbale Municipality Primary School', 'Tororo Primary School Cluster'],
    'partners' => ['Bright Futures Education Foundation'],
    'body' => 'Remedial literacy and numeracy classes, teacher coaching and school feeding support for upper-primary pupils in Mbale and Tororo.',
  ],
  'Soroti Rural Water Points Rehabilitation' => [
    'sector' => 'WASH', 'region' => 'Soroti', 'budget' => 240000, 'start' => '2024-06-01', 'end' => '2026-05-31', 'status' => 'completed',
    'locations' => ['Soroti Rural Water Scheme'],
    'partners' => ['Pearl Water & Sanitation Initiative'],
    'body' => 'Rehabilitation of broken boreholes and protected springs, with water user committees trained to collect fees and maintain the points.',
  ],
  'Gulu Returnee Livelihoods & Savings Groups' => [
    'sector' => 'Livelihoods', 'region' => 'Gulu', 'budget' => 98500, 'start' => '2025-09-01', 'end' => '2027-08-31', 'status' => 'active',
    'locations' => ['Gulu Returnee Resource Centre', 'Kitgum Town Vocational Centre'],
    'partners' => ['Harvest Hope Livelihoods Cooperative', 'Pearl Water & Sanitation Initiative'],
    'body' => 'Village savings and loan associations, vocational training and start-up kits for returnee households and young people in Gulu and Kitgum.',
  ],
  "Lira Girls' Safe Spaces & Child Protection" => [
    'sector' => 'Protection', 'region' => 'Lira', 'budget' => 76000, 'start' => '2025-04-01', 'end' => '2026-09-30', 'status' => 'completed',
    'locations' => ["Lira Girls' Safe Space"],
    'partners' => ['Safe Haven Child Protection Network'],
    'body' => 'Safe spaces offering mentorship, life skills and case referral for adolescent girls, plus training for community child protection committees.',
  ],
  'Arua Borehole & Hygiene Promotion' => [
    'sector' => 'WASH', 'region' => 'Arua', 'budget' => 150000, 'start' => '2026-11-01', 'end' => '2028-04-30', 'status' => 'planned',
    'locations' => ['Arua Town Water Point'],
    'partners' => ['Pearl Water & Sanitation Initiative'],
    'body' => 'Planned drilling of new boreholes and a hygiene promotion campaign for peri-urban settlements around Arua. Baseline survey scheduled for the start phase.',
  ],
  'Mbarara Dairy Value Chain Strengthening' => [
    'sector' => 'Livelihoods', 'region' => 'Mbarara', 'budget' => 210000, 'start' => '2025-02-01', 'end' => '2027-01-31', 'status' => 'active',
    'locations' => ['Mbarara Dairy Farmers Hub'],
    'partners' => ['Harvest Hope Livelihoods Cooperative'],
    'body' => 'Milk cooling centres, farmer cooperative training and market linkages to raise incomes for smallholder dairy farmers in Mbarara.',
  ],
  'Fort Portal Tea Outgrowers Training' => [
    'sector' => 'Livelihoods', 'region' => 'Kabarole', 'budget' => 64000, 'start' => '2026-01-15', 'end' => '2026-12-31', 'status' => 'active',
    'locations' => ['Fort Portal Tea Outgrowers Hub'],
    'partners' => ['Harvest Hope Livelihoods Cooperative'],
    'body' => 'Good agricultural practice training, input financing and group marketing for tea outgrowers around Fort Portal.',
  ],
  'Jinja Youth Skills & Employment' => [
    'sector' => 'Education', 'region' => 'Jinja', 'budget' => 132000, 'start' => '2024-09-01', 'end' => '2026-08-31', 'status' => 'completed',
    'locations' => ['Jinja Youth Skills Centre', 'Mukono Town Skills Annex'],
    'partners' => ['Bright Futures Education Foundation'],
    'body' => 'Practical skills training, apprenticeships and job placement support for out-of-school youth in Jinja and Mukono.',
  ],
  'Kasese Flood Response & Recovery' => [
    'sector' => 'Protection', 'region' => 'Kasese', 'budget' => 310000, 'start' => '2026-05-01', 'end' => '2027-04-30', 'status' => 'active',
    'locations' => ['Kasese Flood Recovery Camp'],
    'partners' => ['Safe Haven Child Protection Network', 'Mwangaza Community Health Trust'],
    'body' => 'Emergency shelter, protection services, health outreach and cash assistance for households displaced by the river flooding in Kasese.',
  ],
];
$project_ids = [];
foreach ($projects as $title => $p) {
  $node = athari_seed_find('project', $title) ?? Node::create([
    'type' => 'project',
    'title' => $title,
    'uid' => $admin->id(),
    'status' => 1,
    'body' => ['value' => '<p>' . $p['body'] . '</p>', 'format' => 'basic_html'],
    'field_budget' => $p['budget'],
    'field_start_date' => $p['start'],
    'field_end_date' => $p['end'],
    'field_status' => $p['status'],
    'field_sector' => athari_seed_term('sector', $p['sector']),
    'field_region' => athari_seed_term('region', $p['region']),
    'field_locations' => array_map(fn($l) => ['target_id' => $location_ids[$l]], $p['locations']),
    'field_partners' => array_map(fn($l) => ['target_id' => $partner_ids[$l]], $p['partners']),
  ]);
  if ($node->isNew()) {
    $node->save();
  }
  $project_ids[$title] = $node->id();
}

// ---------------------------------------------------------------------------
// Reports: project => list of [date, beneficiaries, funds, state, author, text].
// ---------------------------------------------------------------------------
$reports = [
  'Kawempe Maternal & Child Health Outreach' => [
    ['2025-06-30', 1240, 14200, 'published', 'o', 'Opened three outreach clinics and trained 24 village health team members. 412 women attended first antenatal visits.'],
    ['2025-12-31', 2860, 31500, 'published', 'o', 'Immunisation drives reached 1,900 children under five. Referral transport arrangements now operate in all three parishes.'],
    ['2026-06-30', 3410, 38900, 'published', 'o', 'Postnatal home visits scaled up. Skilled birth attendance among enrolled mothers rose from 61% to 78%.'],
    ['2026-09-30', 1180, 12600, 'in_review', 'o', 'Quarterly outreach continued despite stock-outs of some vaccines in September; stock now restored.'],
  ],
  'Mbale Schools Learning Recovery Programme' => [
    ['2025-07-31', 1850, 16800, 'published', 'o', 'Baseline reading assessments completed in 12 schools. Remedial classes started for 1,850 pupils.'],
    ['2026-02-28', 2400, 22100, 'published', 'o', 'Teacher coaching visits completed in all schools; 71% of pupils now read at grade level, up from 44%.'],
    ['2026-09-30', 1975, 17400, 'draft', 'o', 'Term three report in preparation. Attendance figures still being collected from two schools.'],
    ['2026-10-08', 640, 5900, 'draft', 'o', 'Supplementary note on school feeding delays caused by late delivery of maize.'],
  ],
  'Soroti Rural Water Points Rehabilitation' => [
    ['2024-12-15', 6200, 58000, 'archived', 'a', 'Inception phase: surveyed 64 water points and prioritised 38 for rehabilitation. Superseded by later reports.'],
    ['2025-09-30', 9800, 71500, 'published', 'o', '27 boreholes rehabilitated and water user committees formed. Average collection time cut from 55 to 20 minutes.'],
    ['2026-05-31', 11400, 64300, 'published', 'o', 'Final report: all 38 water points functional. Committees collect monthly fees to cover repairs.'],
  ],
  'Gulu Returnee Livelihoods & Savings Groups' => [
    ['2025-12-31', 420, 9800, 'published', 'o', 'Formed 14 savings groups with 420 members. Combined savings reached UGX 18 million.'],
    ['2026-06-30', 780, 15200, 'published', 'o', 'Vocational training cohorts started in tailoring, welding and bakery. 96 young people received start-up kits.'],
    ['2026-09-30', 640, 8900, 'in_review', 'o', 'Savings groups issued their first loans; repayment rate stands at 94%.'],
    ['2026-10-10', 310, 2100, 'draft', 'o', 'Field visit notes from Kitgum, awaiting figures from the group secretaries.'],
  ],
  "Lira Girls' Safe Spaces & Child Protection" => [
    ['2025-09-30', 560, 11800, 'archived', 'a', 'Early-phase safe space attendance figures. Replaced by corrected totals in later reports.'],
    ['2026-03-31', 720, 14900, 'published', 'o', 'Mentorship sessions held weekly. 38 cases referred to social services and police family units.'],
    ['2026-09-30', 905, 16700, 'in_review', 'o', 'Final report: 905 girls reached across the programme and 22 child protection committees trained.'],
  ],
  'Mbarara Dairy Value Chain Strengthening' => [
    ['2025-08-31', 310, 22400, 'published', 'o', 'Two milk cooling centres installed. 310 farmers registered in cooperatives.'],
    ['2026-02-28', 540, 31800, 'published', 'o', 'Average daily milk sales per farmer increased by 38% after bulk contracts were signed with a processor.'],
    ['2026-08-31', 465, 25300, 'in_review', 'o', 'Animal health training and artificial insemination service days held in four sub-counties.'],
    ['2026-10-05', 220, 6400, 'draft', 'o', 'Draft figures from the October cooperative meetings.'],
  ],
  'Fort Portal Tea Outgrowers Training' => [
    ['2026-04-30', 380, 9100, 'published', 'o', 'Good agricultural practice training delivered to 380 outgrowers in 19 groups.'],
    ['2026-09-15', 520, 11700, 'draft', 'o', 'Input financing round two disbursed; yield monitoring data still to be consolidated.'],
  ],
  'Jinja Youth Skills & Employment' => [
    ['2025-03-31', 410, 21800, 'published', 'o', 'First cohort of 410 youth enrolled in carpentry, hairdressing, ICT and motor repair.'],
    ['2025-11-30', 560, 30400, 'archived', 'a', 'Mid-term figures later restated in the final report.'],
    ['2026-08-31', 690, 28500, 'published', 'a', 'Final report: 690 youth trained and 61% in paid work or self-employment six months after graduation.'],
  ],
  'Kasese Flood Response & Recovery' => [
    ['2026-07-31', 2150, 48200, 'published', 'o', 'Emergency shelter, hygiene kits and cash assistance delivered to 2,150 displaced people.'],
    ['2026-09-30', 3300, 61700, 'in_review', 'o', 'Protection desk opened at the camp; 74 unaccompanied children registered and placed in family-based care.'],
    ['2026-10-12', 1450, 17300, 'draft', 'o', 'Health outreach figures for the first fortnight of October, pending confirmation.'],
  ],
];

$state_path = [
  'draft' => [],
  'in_review' => ['in_review'],
  'published' => ['in_review', 'published'],
  'archived' => ['in_review', 'published', 'archived'],
];
$created = 0;
foreach ($reports as $project_title => $rows) {
  $short = preg_replace('/ (&|Programme|Training|Strengthening|Rehabilitation|Promotion|Recovery|Response).*$/', '', $project_title);
  foreach ($rows as [$date, $beneficiaries, $funds, $state, $who, $text]) {
    $title = $short . ' field report, ' . date('F Y', strtotime($date));
    if (athari_seed_find('report', $title)) {
      continue;
    }
    $author = $who === 'a' ? $admin : $officer;
    $node = Node::create([
      'type' => 'report',
      'title' => $title,
      'uid' => $author->id(),
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
    foreach ($state_path[$state] as $step) {
      $node->set('moderation_state', $step);
      $node->setNewRevision(TRUE);
      // A state-only change does not touch any field, so mark the revision as
      // translation-affected explicitly. The review queue relies on this flag.
      $node->setRevisionTranslationAffected(TRUE);
      $node->setRevisionLogMessage("Seeded sample report ($step).");
      $node->save();
    }
    $created++;
  }
}

\Drupal::service('account_switcher')->switchBack();
print "Seed complete. Reports created this run: $created.\n";
