<?php

/**
 * @file
 * Creates the five Sector terms. Taxonomy terms are content, not configuration,
 * so a fresh install from config/sync starts without them. Safe to run twice.
 *
 * Run: drush php:script deploy/create-sectors.php
 */

use Drupal\taxonomy\Entity\Term;

$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
foreach (['Health', 'Education', 'WASH', 'Livelihoods', 'Protection'] as $weight => $name) {
  if ($storage->loadByProperties(['vid' => 'sector', 'name' => $name])) {
    continue;
  }
  Term::create(['vid' => 'sector', 'name' => $name, 'weight' => $weight])->save();
  print "Created sector $name.\n";
}
