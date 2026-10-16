<?php

/**
 * @file
 * Day 2: adds an Address field to the Partner content type.
 *
 * Run with: ddev drush php:script scripts/day2-01-partner-address.php
 */

require_once __DIR__ . '/lib.php';

athari_lib_add_field('node', 'partner', 'field_address', 'address', 'Address', [
  'widget' => 'address_default',
  'formatter' => 'address_default',
  'weight' => 3,
  'settings' => ['available_countries' => ['UG']],
  'widget_settings' => ['default_country' => 'UG'],
  'description' => 'Office or postal address of the partner organisation.',
]);

print "Partner address field created.\n";
