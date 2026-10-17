<?php

/**
 * @file
 * Kenya: DESTRUCTIVE reset of the sample content before re-seeding.
 *
 * Deletes, in this order: all donations; all Report, Project, Location and
 * Partner nodes; all terms of the Region vocabulary. It does NOT touch
 * configuration (views, blocks, fields, roles, the theme), users, menu links,
 * the About page or the Sector vocabulary. Deleting content does not delete
 * configuration, so the dependent-config cascade seen on Day 4 cannot happen.
 *
 * It refuses to run unless the first argument is exactly CONFIRM.
 *
 * Run with: ddev drush php:script scripts/kenya-02-reset-content.php -- CONFIRM
 */

if (($extra[0] ?? '') !== 'CONFIRM') {
  print "Refusing to run. This deletes content. Pass CONFIRM as the first argument.\n";
  return;
}

$tm = \Drupal::entityTypeManager();

/**
 * Deletes every entity of a type (optionally one bundle), in batches.
 */
$wipe = function (string $entity_type, ?string $bundle_key = NULL, ?string $bundle = NULL) use ($tm): int {
  $storage = $tm->getStorage($entity_type);
  $query = $storage->getQuery()->accessCheck(FALSE);
  if ($bundle_key && $bundle) {
    $query->condition($bundle_key, $bundle);
  }
  $count = 0;
  foreach (array_chunk($query->execute(), 25) as $ids) {
    $storage->delete($storage->loadMultiple($ids));
    $count += count($ids);
  }
  return $count;
};

printf("Donations deleted: %d\n", $wipe('athari_donation'));
foreach (['report', 'project', 'location', 'partner'] as $type) {
  printf("%s nodes deleted: %d\n", ucfirst($type), $wipe('node', 'type', $type));
}
printf("Region terms deleted: %d\n", $wipe('taxonomy_term', 'vid', 'region'));

// Forget any throttle markers left by status queries on deleted donations.
\Drupal::database()->delete('key_value')->condition('collection', 'state')->condition('name', 'athari_donate.last_query.%', 'LIKE')->execute();
print "Reset complete. Now run kenya-01-regions.php, then kenya-03-seed.php.\n";
