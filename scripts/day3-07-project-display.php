<?php

/**
 * @file
 * Day 3: tidier formats on the Project page (dates without clock time,
 * budget without cents).
 *
 * Run with: ddev drush php:script scripts/day3-07-project-display.php
 */

use Drupal\Core\Entity\Entity\EntityViewDisplay;

$display = EntityViewDisplay::load('node.project.default');
foreach (['field_start_date' => 'Start date', 'field_end_date' => 'End date'] as $field => $label) {
  $display->setComponent($field, [
    'type' => 'datetime_custom',
    'label' => 'above',
    'settings' => ['timezone_override' => '', 'date_format' => 'j F Y'],
    'weight' => $display->getComponent($field)['weight'] ?? 3,
  ]);
}
$display->setComponent('field_budget', [
  'type' => 'number_decimal',
  'label' => 'above',
  'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 0, 'prefix_suffix' => TRUE],
  'weight' => $display->getComponent('field_budget')['weight'] ?? 2,
]);
$display->save();

print "Project display updated.\n";
