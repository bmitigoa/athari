<?php

/**
 * @file
 * Day 3: repairs the revision flag on reports seeded on Day 2.
 *
 * The Day 2 seed script saved state-only revisions without marking them as
 * translation-affected, so core's "latest translation affected revision"
 * filter (used by the review queue) skipped them. This sets the flag on every
 * Report revision. It changes no content and is safe to re-run.
 *
 * Run with: ddev drush php:script scripts/day3-09-repair-seed-revisions.php
 */

$db = \Drupal::database();
$total = 0;
foreach (['node_field_revision', 'node_field_data'] as $table) {
  $nids = $db->query("SELECT nid FROM {node} WHERE type = 'report'")->fetchCol();
  $count = $db->update($table)
    ->fields(['revision_translation_affected' => 1])
    ->condition('nid', $nids, 'IN')
    ->isNull('revision_translation_affected')
    ->execute();
  print "$table: $count row(s) repaired\n";
  $total += $count;
}
\Drupal::service('cache_tags.invalidator')->invalidateTags(['node_list']);
print "Done ($total).\n";
