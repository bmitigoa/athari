<?php

/**
 * @file
 * Sample data for Athari, set in Kenya. Returned as one array so that the seed
 * script, the tests and the reports all read the same source.
 *
 * Everything here is FICTIONAL: the organisations do not exist, the people
 * reached and the money spent are illustrative, and phone numbers are made up.
 * Only the places and their coordinates are real. Money is in Kenyan shillings.
 */

/**
 * Converts the earlier USD sample figures to KES (about 130 KES per USD),
 * rounded to the nearest 500 shillings.
 */
$kes = static fn(int $usd): int => (int) (round($usd * 130 / 500) * 500);

return [

  // Kenya's 47 counties grouped under the 8 former provinces.
  'provinces' => [
    'Nairobi' => ['Nairobi'],
    'Central' => ['Kiambu', 'Kirinyaga', "Murang'a", 'Nyandarua', 'Nyeri'],
    'Coast' => ['Mombasa', 'Kwale', 'Kilifi', 'Tana River', 'Lamu', 'Taita-Taveta'],
    'Eastern' => ['Marsabit', 'Isiolo', 'Meru', 'Tharaka-Nithi', 'Embu', 'Kitui', 'Machakos', 'Makueni'],
    'North Eastern' => ['Garissa', 'Wajir', 'Mandera'],
    'Nyanza' => ['Siaya', 'Kisumu', 'Homa Bay', 'Migori', 'Kisii', 'Nyamira'],
    'Rift Valley' => [
      'Turkana', 'West Pokot', 'Samburu', 'Trans-Nzoia', 'Uasin Gishu', 'Elgeyo-Marakwet', 'Nandi',
      'Baringo', 'Laikipia', 'Nakuru', 'Narok', 'Kajiado', 'Kericho', 'Bomet',
    ],
    'Western' => ['Kakamega', 'Vihiga', 'Bungoma', 'Busia'],
  ],

  'sectors' => ['Health', 'Education', 'WASH', 'Livelihoods', 'Protection'],

  // Name => [website, street, town].
  'partners' => [
    'Afya Bora Community Trust' => ['https://afyabora.example.org', 'Ngong Road, Kilimani', 'Nairobi'],
    'Elimu Mkono Foundation' => ['https://elimumkono.example.org', 'Oginga Odinga Street', 'Kisumu'],
    'Maji Safi Initiative' => ['https://majisafi.example.org', 'Lodwar-Kakuma Road', 'Lodwar'],
    'Mavuno Livelihoods Cooperative' => ['https://mavuno.example.org', 'Kenyatta Avenue', 'Nakuru'],
    'Ulinzi wa Mtoto Network' => ['https://ulinzi.example.org', 'Moi Avenue', 'Mombasa'],
  ],

  // Name => [county, latitude, longitude]. Real places, real coordinates.
  'locations' => [
    'Kibera South Health Centre' => ['Nairobi', -1.3133, 36.7889],
    'Likoni Community Clinic' => ['Mombasa', -4.0833, 39.6667],
    'Garissa Town Primary School' => ['Garissa', -0.4532, 39.6461],
    'Homa Bay Primary School Cluster' => ['Homa Bay', -0.5273, 34.4571],
    'Lodwar Water Scheme' => ['Turkana', 3.1191, 35.5973],
    'Kakuma Water Point' => ['Turkana', 3.7122, 34.8588],
    'Makueni Sand Dam Site' => ['Makueni', -1.7806, 37.6275],
    'Kitui Borehole and Savings Hub' => ['Kitui', -1.3667, 38.0106],
    "Kilifi Girls' Safe Space" => ['Kilifi', -3.6305, 39.8499],
    'Marsabit Town Water Point' => ['Marsabit', 2.3284, 37.9899],
    'Kakamega Dairy Farmers Hub' => ['Kakamega', 0.2827, 34.7519],
    'Nyeri Coffee Outgrowers Hub' => ['Nyeri', -0.4201, 36.9476],
    'Kisumu Youth Skills Centre' => ['Kisumu', -0.0917, 34.7680],
    'Nakuru Youth Skills Annex' => ['Nakuru', -0.3031, 36.0800],
    'Garsen Flood Recovery Camp' => ['Tana River', -2.2736, 40.1136],
  ],

  // Title => details. Budgets are in KES.
  'projects' => [
    'Kibera Maternal & Child Health Outreach' => [
      'short' => 'Kibera Maternal Health',
      'sector' => 'Health', 'county' => 'Nairobi', 'budget' => 18500000, 'start' => '2025-03-01', 'end' => '2027-02-28', 'status' => 'active',
      'locations' => ['Kibera South Health Centre', 'Likoni Community Clinic'],
      'partners' => ['Afya Bora Community Trust'],
      'body' => 'Community-based antenatal, postnatal and child immunisation outreach in Kibera and Likoni, run through health centres and trained community health volunteers.',
    ],
    'Garissa and Homa Bay Learning Recovery Programme' => [
      'short' => 'Garissa Learning Recovery',
      'sector' => 'Education', 'county' => 'Garissa', 'budget' => 12000000, 'start' => '2025-01-15', 'end' => '2026-12-15', 'status' => 'active',
      'locations' => ['Garissa Town Primary School', 'Homa Bay Primary School Cluster'],
      'partners' => ['Elimu Mkono Foundation'],
      'body' => 'Remedial literacy and numeracy classes, teacher coaching and school feeding support for upper-primary pupils in Garissa and Homa Bay.',
    ],
    'Turkana Rural Water Points Rehabilitation' => [
      'short' => 'Turkana Water Points',
      'sector' => 'WASH', 'county' => 'Turkana', 'budget' => 32000000, 'start' => '2024-06-01', 'end' => '2026-05-31', 'status' => 'completed',
      'locations' => ['Lodwar Water Scheme', 'Kakuma Water Point'],
      'partners' => ['Maji Safi Initiative'],
      'body' => 'Rehabilitation of broken boreholes and protected water points, with water user committees trained to collect fees and maintain them.',
    ],
    'Makueni Sand Dams & Savings Groups' => [
      'short' => 'Makueni Sand Dams',
      'sector' => 'Livelihoods', 'county' => 'Makueni', 'budget' => 9850000, 'start' => '2025-09-01', 'end' => '2027-08-31', 'status' => 'active',
      'locations' => ['Makueni Sand Dam Site', 'Kitui Borehole and Savings Hub'],
      'partners' => ['Mavuno Livelihoods Cooperative', 'Maji Safi Initiative'],
      'body' => 'Sand dams for dry-season water, village savings and loan groups, and start-up kits for young people in Makueni and Kitui.',
    ],
    "Kilifi Girls' Safe Spaces & Child Protection" => [
      'short' => 'Kilifi Girls Safe Spaces',
      'sector' => 'Protection', 'county' => 'Kilifi', 'budget' => 7600000, 'start' => '2025-04-01', 'end' => '2026-09-30', 'status' => 'completed',
      'locations' => ["Kilifi Girls' Safe Space"],
      'partners' => ['Ulinzi wa Mtoto Network'],
      'body' => 'Safe spaces offering mentorship, life skills and case referral for adolescent girls, plus training for community child protection committees.',
    ],
    'Marsabit Borehole & Hygiene Promotion' => [
      'short' => 'Marsabit Boreholes',
      'sector' => 'WASH', 'county' => 'Marsabit', 'budget' => 15000000, 'start' => '2026-11-01', 'end' => '2028-04-30', 'status' => 'planned',
      'locations' => ['Marsabit Town Water Point'],
      'partners' => ['Maji Safi Initiative'],
      'body' => 'Planned drilling of new boreholes and a hygiene promotion campaign for pastoralist settlements around Marsabit. A baseline survey is scheduled for the start phase.',
    ],
    'Kakamega Dairy Value Chain Strengthening' => [
      'short' => 'Kakamega Dairy',
      'sector' => 'Livelihoods', 'county' => 'Kakamega', 'budget' => 21000000, 'start' => '2025-02-01', 'end' => '2027-01-31', 'status' => 'active',
      'locations' => ['Kakamega Dairy Farmers Hub'],
      'partners' => ['Mavuno Livelihoods Cooperative'],
      'body' => 'Milk cooling plants, farmer cooperative training and market linkages to raise incomes for smallholder dairy farmers in Kakamega.',
    ],
    'Nyeri Coffee Outgrowers Training' => [
      'short' => 'Nyeri Coffee Outgrowers',
      'sector' => 'Livelihoods', 'county' => 'Nyeri', 'budget' => 6400000, 'start' => '2026-01-15', 'end' => '2026-12-31', 'status' => 'active',
      'locations' => ['Nyeri Coffee Outgrowers Hub'],
      'partners' => ['Mavuno Livelihoods Cooperative'],
      'body' => 'Good agricultural practice training, input financing and group marketing for smallholder coffee growers around Nyeri.',
    ],
    'Kisumu Youth Skills & Employment' => [
      'short' => 'Kisumu Youth Skills',
      'sector' => 'Education', 'county' => 'Kisumu', 'budget' => 13200000, 'start' => '2024-09-01', 'end' => '2026-08-31', 'status' => 'completed',
      'locations' => ['Kisumu Youth Skills Centre', 'Nakuru Youth Skills Annex'],
      'partners' => ['Elimu Mkono Foundation'],
      'body' => 'Practical skills training, apprenticeships and job placement support for out-of-school youth in Kisumu and Nakuru.',
    ],
    'Tana River Flood Response & Recovery' => [
      'short' => 'Tana River Flood Response',
      'sector' => 'Protection', 'county' => 'Tana River', 'budget' => 31000000, 'start' => '2026-05-01', 'end' => '2027-04-30', 'status' => 'active',
      'locations' => ['Garsen Flood Recovery Camp'],
      'partners' => ['Ulinzi wa Mtoto Network', 'Afya Bora Community Trust'],
      'body' => 'Emergency shelter, protection services, health outreach and cash assistance for households displaced by the Tana River flooding.',
    ],
  ],

  // Project title => reports: [date, beneficiaries, funds spent in KES, moderation state, author (o = field officer, a = admin), narrative].
  // Mix: 16 published, 5 in review, 6 draft, 3 archived.
  'reports' => [
    'Kibera Maternal & Child Health Outreach' => [
      ['2025-06-30', 1240, $kes(14200), 'published', 'o', 'Opened three outreach clinics in Kibera and trained 24 community health volunteers. 412 women attended their first antenatal visit.'],
      ['2025-12-31', 2860, $kes(31500), 'published', 'o', 'Immunisation days reached 1,900 children under five. A referral arrangement with local boda boda riders now covers all four villages.'],
      ['2026-06-30', 3410, $kes(38900), 'published', 'o', 'Postnatal home visits were scaled up. Skilled birth attendance among enrolled mothers rose from 61% to 78%.'],
      ['2026-09-30', 1180, $kes(12600), 'in_review', 'o', 'Quarterly outreach continued despite stock-outs of some vaccines in September; stock has now been restored.'],
    ],
    'Garissa and Homa Bay Learning Recovery Programme' => [
      ['2025-07-31', 1850, $kes(16800), 'published', 'o', 'Baseline reading assessments completed in 12 schools in Garissa and Homa Bay. Remedial classes started for 1,850 pupils.'],
      ['2026-02-28', 2400, $kes(22100), 'published', 'o', 'Teacher coaching visits completed in all schools; 71% of pupils now read at grade level, up from 44%.'],
      ['2026-09-30', 1975, $kes(17400), 'draft', 'o', 'Term three report in preparation. Attendance figures are still being collected from two schools.'],
      ['2026-10-08', 640, $kes(5900), 'draft', 'o', 'Supplementary note on school feeding delays caused by late delivery of maize and beans.'],
    ],
    'Turkana Rural Water Points Rehabilitation' => [
      ['2024-12-15', 6200, $kes(58000), 'archived', 'a', 'Inception phase: surveyed 64 water points in Turkana and prioritised 38 for rehabilitation. Superseded by later reports.'],
      ['2025-09-30', 9800, $kes(71500), 'published', 'o', '27 boreholes rehabilitated and water user committees formed. Average collection time cut from 55 to 20 minutes.'],
      ['2026-05-31', 11400, $kes(64300), 'published', 'o', 'Final report: all 38 water points functional. Committees collect monthly fees to cover repairs.'],
    ],
    'Makueni Sand Dams & Savings Groups' => [
      ['2025-12-31', 420, $kes(9800), 'published', 'o', 'Formed 14 savings groups with 420 members. Combined savings reached KES 1.4 million.'],
      ['2026-06-30', 780, $kes(15200), 'published', 'o', 'Two sand dams completed in Makueni and Kitui. 96 young people received start-up kits for small businesses.'],
      ['2026-09-30', 640, $kes(8900), 'in_review', 'o', 'Savings groups issued their first loans; repayment rate stands at 94%.'],
      ['2026-10-10', 310, $kes(2100), 'draft', 'o', 'Field visit notes from Kitui, awaiting figures from the group secretaries.'],
    ],
    "Kilifi Girls' Safe Spaces & Child Protection" => [
      ['2025-09-30', 560, $kes(11800), 'archived', 'a', 'Early-phase safe space attendance figures. Replaced by corrected totals in later reports.'],
      ['2026-03-31', 720, $kes(14900), 'published', 'o', "Mentorship sessions held weekly. 38 cases referred to children's officers and the police gender desk."],
      ['2026-09-30', 905, $kes(16700), 'in_review', 'o', 'Final report: 905 girls reached across the programme and 22 community child protection committees trained.'],
    ],
    'Kakamega Dairy Value Chain Strengthening' => [
      ['2025-08-31', 310, $kes(22400), 'published', 'o', 'Two milk cooling plants installed. 310 farmers registered in cooperatives.'],
      ['2026-02-28', 540, $kes(31800), 'published', 'o', 'Average daily milk sales per farmer increased by 38% after bulk contracts were signed with a processor.'],
      ['2026-08-31', 465, $kes(25300), 'in_review', 'o', 'Animal health training and artificial insemination service days held in four sub-counties.'],
      ['2026-10-05', 220, $kes(6400), 'draft', 'o', 'Draft figures from the October cooperative meetings.'],
    ],
    'Nyeri Coffee Outgrowers Training' => [
      ['2026-04-30', 380, $kes(9100), 'published', 'o', 'Good agricultural practice training delivered to 380 coffee growers in 19 groups.'],
      ['2026-09-15', 520, $kes(11700), 'draft', 'o', 'Input financing round two disbursed; yield monitoring data still to be consolidated.'],
    ],
    'Kisumu Youth Skills & Employment' => [
      ['2025-03-31', 410, $kes(21800), 'published', 'o', 'First cohort of 410 youth enrolled in carpentry, hairdressing, ICT and motor repair.'],
      ['2025-11-30', 560, $kes(30400), 'archived', 'a', 'Mid-term figures later restated in the final report.'],
      ['2026-08-31', 690, $kes(28500), 'published', 'a', 'Final report: 690 youth trained and 61% in paid work or self-employment six months after graduation.'],
    ],
    'Tana River Flood Response & Recovery' => [
      ['2026-07-31', 2150, $kes(48200), 'published', 'o', 'Emergency shelter, hygiene kits and cash assistance delivered to 2,150 displaced people in Garsen.'],
      ['2026-09-30', 3300, $kes(61700), 'in_review', 'o', 'Protection desk opened at the camp; 74 unaccompanied children registered and placed in family-based care.'],
      ['2026-10-12', 1450, $kes(17300), 'draft', 'o', 'Health outreach figures for the first fortnight of October, pending confirmation.'],
    ],
  ],

  // Sample donations: project title => [amount KES, status, days ago].
  'donations' => [
    'Kibera Maternal & Child Health Outreach' => [[5000, 'success', 52], [1000, 'success', 31], [20000, 'success', 12], [2500, 'failed', 9]],
    'Garissa and Homa Bay Learning Recovery Programme' => [[1500, 'success', 47], [10000, 'success', 26], [500, 'cancelled', 20]],
    'Makueni Sand Dams & Savings Groups' => [[2500, 'success', 40], [5000, 'success', 18]],
    'Kakamega Dairy Value Chain Strengthening' => [[7500, 'success', 35], [1000, 'success', 15], [3000, 'failed', 6]],
    'Nyeri Coffee Outgrowers Training' => [[2000, 'success', 29], [500, 'success', 10]],
    'Tana River Flood Response & Recovery' => [[10000, 'success', 24], [20000, 'success', 14], [50000, 'success', 5], [1000, 'cancelled', 3], [2500, 'success', 1]],
  ],

];
