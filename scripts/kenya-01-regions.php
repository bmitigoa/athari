<?php

/**
 * @file
 * Kenya: builds the Region vocabulary from Kenya's 47 counties.
 *
 * The 8 former provinces (Nairobi, Central, Coast, Eastern, North Eastern,
 * Nyanza, Rift Valley, Western) are top-level terms and each county is a child
 * of its province. Safe to re-run: existing terms are left alone.
 *
 * Run with: ddev drush php:script scripts/kenya-01-regions.php
 */

use Drupal\taxonomy\Entity\Term;

$data = require __DIR__ . '/seed/kenya-data.php';
$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

/**
 * Finds or creates a Region term and returns its id.
 */
$ensure = function (string $name, int $parent = 0, int $weight = 0) use ($storage): int {
  $found = $storage->loadByProperties(['vid' => 'region', 'name' => $name]);
  foreach ($found as $term) {
    $parents = array_map('intval', array_column($term->get('parent')->getValue(), 'target_id'));
    if (($parent === 0 && (!$parents || $parents === [0])) || in_array($parent, $parents, TRUE)) {
      return (int) $term->id();
    }
  }
  $term = Term::create(['vid' => 'region', 'name' => $name, 'parent' => $parent ? [$parent] : [], 'weight' => $weight]);
  $term->save();
  return (int) $term->id();
};

$provinces = 0;
$counties = 0;
$weight = 0;
foreach ($data['provinces'] as $province => $list) {
  $province_id = $ensure($province, 0, $weight++);
  $provinces++;
  foreach ($list as $i => $county) {
    $ensure($county, $province_id, $i);
    $counties++;
  }
}

$vocabulary = \Drupal\taxonomy\Entity\Vocabulary::load('region');
$vocabulary->set('description', "Kenya's 47 counties grouped under the 8 former provinces.");
$vocabulary->save();

print "Region vocabulary ready: $provinces provinces, $counties counties.\n";
