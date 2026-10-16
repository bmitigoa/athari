<?php

/**
 * @file
 * Day 3: installs athari_theme, makes it the default and places its blocks.
 *
 * Run with: ddev drush php:script scripts/day3-01-theme.php
 */

use Drupal\block\Entity\Block;

\Drupal::service('theme_installer')->install(['athari_theme']);

\Drupal::configFactory()->getEditable('system.theme')->set('default', 'athari_theme')->save();

// Logo and favicon (SVG files shipped inside the theme).
\Drupal::configFactory()->getEditable('athari_theme.settings')
  ->set('logo.use_default', FALSE)
  ->set('logo.path', 'themes/custom/athari_theme/logo.svg')
  ->set('favicon.use_default', FALSE)
  ->set('favicon.path', 'themes/custom/athari_theme/favicon.svg')
  // Olivero reads this and writes its colour custom properties inline.
  ->set('base_primary_color', '#2f6b4f')
  ->set('site_branding_bg_color', 'white')
  ->save();

\Drupal::configFactory()->getEditable('system.site')
  ->set('name', 'Athari')
  ->set('slogan', 'Impact you can see')
  ->save();

// Copy every Olivero block placement to the new theme (once).
foreach (Block::loadMultiple() as $block) {
  if ($block->getTheme() !== 'olivero') {
    continue;
  }
  $new_id = str_replace('olivero_', 'athari_theme_', $block->id());
  if (Block::load($new_id)) {
    continue;
  }
  $copy = $block->createDuplicate();
  $copy->set('id', $new_id);
  $copy->setTheme('athari_theme');
  $copy->save();
}

print "athari_theme installed and set as default.\n";
