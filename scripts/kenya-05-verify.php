<?php

/**
 * @file
 * Kenya: checks the database, the configuration and the rendered pages.
 *
 * Output lines: CHECK|description|expected|actual|PASS or FAIL.
 * Run with: ddev drush php:script scripts/kenya-05-verify.php
 */

use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$db = \Drupal::database();
$tm = \Drupal::entityTypeManager();
$fails = 0;
$checks = 0;
$check = function (string $what, $expected, $actual) use (&$fails, &$checks) {
  $checks++;
  $ok = $expected === $actual;
  $fails += $ok ? 0 : 1;
  $fmt = fn($v) => is_bool($v) ? ($v ? 'yes' : 'no') : (is_array($v) ? json_encode($v) : (string) $v);
  print "CHECK|$what|" . $fmt($expected) . '|' . $fmt($actual) . '|' . ($ok ? 'PASS' : 'FAIL') . "\n";
};

// --- Regions -----------------------------------------------------------------
$terms = $tm->getStorage('taxonomy_term')->loadByProperties(['vid' => 'region']);
$provinces = $counties = $orphans = 0;
$province_names = [];
foreach ($terms as $term) {
  $parents = array_filter(array_column($term->get('parent')->getValue(), 'target_id'));
  if ($parents) {
    $counties++;
  }
  else {
    $provinces++;
    $province_names[] = $term->label();
  }
}
sort($province_names);
$check('Region vocabulary: top-level provinces', 8, $provinces);
$check('Region vocabulary: counties', 47, $counties);
$check('Province names', ['Central', 'Coast', 'Eastern', 'Nairobi', 'North Eastern', 'Nyanza', 'Rift Valley', 'Western'], $province_names);
$check('Region terms have a URL alias (/region/nairobi)', TRUE, (bool) $db->query("SELECT COUNT(*) FROM {path_alias} WHERE alias = '/region/nairobi'")->fetchField());

// --- Content counts -------------------------------------------------------------
foreach (['partner' => 5, 'location' => 15, 'project' => 10, 'report' => 30] as $type => $expected) {
  $check("Nodes of type $type", $expected, (int) $db->query('SELECT COUNT(*) FROM {node_field_data} WHERE type = :t', [':t' => $type])->fetchField());
}
$states = $db->query("SELECT moderation_state s, COUNT(*) c FROM {content_moderation_state_field_data} WHERE content_entity_type_id = 'node' GROUP BY moderation_state ORDER BY moderation_state")->fetchAllKeyed();
$check('Report moderation states', ['archived' => 3, 'draft' => 6, 'in_review' => 5, 'published' => 16], array_map('intval', $states));
$check('Donations', 19, (int) $db->query('SELECT COUNT(*) FROM {athari_donation}')->fetchField());

// --- Places ----------------------------------------------------------------------
$outside = 0;
$located = 0;
foreach ($tm->getStorage('node')->loadByProperties(['type' => 'location']) as $node) {
  $lat = (float) $node->get('field_coordinates')->lat;
  $lon = (float) $node->get('field_coordinates')->lon;
  $county = $node->get('field_district')->entity;
  $located += ($county && $county->get('parent')->target_id) ? 1 : 0;
  if ($lat < -4.7 || $lat > 5.1 || $lon < 33.9 || $lon > 41.95) {
    $outside++;
  }
}
$check('Locations outside Kenya', 0, $outside);
$check('Locations whose county is a real county term', 15, $located);
$bad_regions = 0;
foreach ($tm->getStorage('node')->loadByProperties(['type' => 'project']) as $node) {
  $term = $node->get('field_region')->entity;
  $bad_regions += ($term && $term->get('parent')->target_id) ? 0 : 1;
}
$check('Projects tagged with a county', 0, $bad_regions);
$budget = (int) $db->query('SELECT SUM(field_budget_value) FROM {node__field_budget}')->fetchField();
$check('Total project budgets (KES)', 166550000, $budget);

// --- Currency labels ---------------------------------------------------------------
$field = fn($id) => \Drupal\field\Entity\FieldConfig::load($id);
$check('Budget field label', 'Budget (KES)', $field('node.project.field_budget')->label());
$check('Funds spent field label', 'Funds spent (KES)', $field('node.report.field_funds_spent')->label());
$check('Budget prefix', 'KES ', $field('node.project.field_budget')->getSetting('prefix'));
$check('Funds prefix', 'KES ', $field('node.report.field_funds_spent')->getSetting('prefix'));
$check('Partner address country', ['KE'], array_values($field('node.partner.field_address')->getSetting('available_countries')));

// --- Configuration scan ------------------------------------------------------------
$words = '/\b(Uganda|Ugandan|UGX|USD|Kampala|Wakiso|Mukono|Masaka|Jinja|Mbale|Tororo|Soroti|Gulu|Kitgum|Lira|Arua|Mbarara|Kabarole|Kasese|Hoima|Kabale|Adjumani|MTN|MoMo)\b/i';
$hits = [];
foreach (\Drupal::configFactory()->listAll() as $name) {
  if (preg_match($words, json_encode(\Drupal::config($name)->getRawData()))) {
    $hits[] = $name;
  }
}
$check('Config objects mentioning Uganda, UGX, USD or MoMo', [], $hits);
$map = \Drupal\views\Entity\View::load('project_map')->get('display');
$center = $map['default']['display_options']['style']['options']['map_position']['center'];
$check('Map centre (Kenya)', ['lat' => 0.0236, 'lon' => 37.9062], ['lat' => (float) $center['lat'], 'lon' => (float) $center['lon']]);
$check('Map zoom', 6, (int) $map['default']['display_options']['style']['options']['map_position']['zoom']);

// --- Content scan ------------------------------------------------------------------
$texts = $db->query("SELECT title FROM {node_field_data} UNION ALL SELECT body_value FROM {node__body} UNION ALL SELECT field_narrative_value FROM {node__field_narrative} UNION ALL SELECT name FROM {taxonomy_term_field_data} UNION ALL SELECT alias FROM {path_alias}")->fetchCol();
$content_hits = array_filter($texts, fn($t) => preg_match($words, (string) $t));
$check('Content (titles, text, terms, aliases) mentioning Uganda or UGX', [], array_values($content_hits));

// --- Rendered pages as an anonymous visitor ----------------------------------------------
$switcher = \Drupal::service('account_switcher');
$kernel = \Drupal::service('http_kernel');
$switcher->switchTo(new AnonymousUserSession());
$render = function (string $path) use ($kernel): array {
  $response = $kernel->handle(Request::create($path), HttpKernelInterface::SUB_REQUEST, TRUE);
  return [$response->getStatusCode(), (string) $response->getContent()];
};
$pb = (int) $db->query("SELECT SUM(b.field_beneficiaries_value) FROM {node__field_beneficiaries} b JOIN {node_field_data} n ON n.nid = b.entity_id WHERE n.type = 'report' AND n.status = 1")->fetchField();
$pf = (int) $db->query("SELECT SUM(f.field_funds_spent_value) FROM {node__field_funds_spent} f JOIN {node_field_data} n ON n.nid = f.entity_id WHERE n.type = 'report' AND n.status = 1")->fetchField();
$compact = $pf >= 1000000 ? 'KES ' . number_format($pf / 1000000, 1) . 'M' : 'KES ' . number_format($pf);
foreach (['/', '/projects', '/projects/table', '/impact', '/map', '/projects/kibera-maternal-child-health-outreach', '/about', '/donate'] as $path) {
  [$status, $html] = $render($path);
  $check("GET $path status", 200, $status);
  $check("GET $path has no USD or Uganda wording", 0, preg_match_all($words, strip_tags($html)));
}
[, $home] = $render('/');
$check('Homepage shows published beneficiaries', TRUE, str_contains($home, number_format($pb)));
$check('Homepage shows funds spent in compact KES', TRUE, str_contains($home, $compact));
[, $table] = $render('/projects/table');
$check('Projects table shows KES budgets', TRUE, str_contains($table, 'KES 18,500,000'));
[, $project] = $render('/projects/kibera-maternal-child-health-outreach');
$check('Project page shows the budget in KES', TRUE, str_contains($project, 'KES 18,500,000'));
$check('Project page shows County label', TRUE, str_contains($project, 'County'));
[, $map_html] = $render('/map');
// Each marker carries its coordinates; count the distinct latitude/longitude pairs.
preg_match_all('/"lat":(-?[0-9.]+),"lon":(-?[0-9.]+)/', str_replace('"', '"', $map_html), $pairs, PREG_SET_ORDER);
$distinct = array_unique(array_map(fn($m) => $m[1] . ',' . $m[2], $pairs));
// The map's own centre point is in the same settings; it is not a marker.
$distinct = array_diff($distinct, ['0.0236,37.9062']);
$check('Map page has 15 distinct markers', 15, count($distinct));
$switcher->switchBack();

print 'SUMMARY|' . ($fails === 0 ? "ALL $checks KENYA CHECKS PASSED" : "$fails of $checks CHECK(S) FAILED") . "\n";
