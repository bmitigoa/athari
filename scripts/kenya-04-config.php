<?php

/**
 * @file
 * Kenya: switches existing configuration to KES and Kenyan wording, in place.
 *
 * - Project budget and report funds spent: labels and the "KES " prefix.
 * - County wording on Location and Project, and the Partner address country.
 * - Views: every USD becomes KES, Uganda becomes Kenya, the map is centred on
 *   Kenya, and big KES totals on stat cards are shown compactly (KES 61.5M).
 *
 * Views are edited in place and never deleted, so Drupal's dependent-config
 * cascade (which removed the homepage cards on Day 4) cannot be triggered.
 * Safe to re-run.
 *
 * Run with: ddev drush php:script scripts/kenya-04-config.php
 */

require_once __DIR__ . '/lib-views.php';

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\views\Entity\View;

// Field labels and the currency prefix shown by number formatters.
$fields = [
  'node.project.field_budget' => ['label' => 'Budget (KES)', 'prefix' => 'KES '],
  'node.report.field_funds_spent' => ['label' => 'Funds spent (KES)', 'prefix' => 'KES '],
  'node.location.field_district' => ['label' => 'County'],
  'node.project.field_region' => ['label' => 'County', 'description' => 'County where the project operates.'],
];
foreach ($fields as $id => $changes) {
  $field = FieldConfig::load($id);
  if (!$field) {
    print "Missing field $id\n";
    continue;
  }
  if (isset($changes['label'])) {
    $field->setLabel($changes['label']);
  }
  if (isset($changes['description'])) {
    $field->setDescription($changes['description']);
  }
  if (isset($changes['prefix'])) {
    $field->setSetting('prefix', $changes['prefix']);
  }
  $field->save();
}

// Partner addresses are in Kenya.
if ($address = FieldConfig::load('node.partner.field_address')) {
  $address->setSetting('available_countries', ['KE']);
  $address->save();
}
if ($form = EntityFormDisplay::load('node.partner.default')) {
  if ($component = $form->getComponent('field_address')) {
    $component['settings']['default_country'] = 'KE';
    $form->setComponent('field_address', $component)->save();
  }
}

/**
 * Walks a Views display array and applies the Kenya changes.
 */
function athari_kenya_walk(array $node): array {
  foreach ($node as $key => $value) {
    if (is_string($value)) {
      $value = str_replace(['USD', 'Uganda', 'Region / district'], ['KES', 'Kenya', 'Region / county'], $value);
      $node[$key] = $value;
    }
    elseif (is_array($value)) {
      // The project county field is labelled "Region" in listings; call it County.
      if (($value['field'] ?? NULL) === 'field_region' && ($value['label'] ?? NULL) === 'Region') {
        $value['label'] = 'County';
      }
      // Centre the map on Kenya.
      if ($key === 'map_position' && isset($value['center'])) {
        $value['center'] = ['lat' => 0.0236, 'lon' => 37.9062];
        $value['zoom'] = 6;
        $value['minZoom'] = 5;
      }
      // Compact KES totals on the "Funds spent" stat cards.
      if ($key === 'field_funds_spent' && isset($value['alter']['alter_text']) && !empty($value['alter']['alter_text']) && str_contains((string) ($value['element_wrapper_class'] ?? ''), 'athari-stat')) {
        $value['alter']['text'] = athari_kes_rewrite('field_funds_spent')['text'];
      }
      $node[$key] = athari_kenya_walk($value);
    }
  }
  return $node;
}

$changed = [];
foreach (View::loadMultiple() as $view) {
  if ($view->get('tag') !== 'athari') {
    continue;
  }
  $before = serialize($view->get('display'));
  $view->set('display', athari_kenya_walk($view->get('display')));
  if (serialize($view->get('display')) !== $before) {
    $view->save();
    $changed[] = $view->id();
  }
}

print 'Fields updated: ' . count($fields) . " (+ Partner address country).\n";
print 'Views changed in place: ' . ($changed ? implode(', ', $changed) : 'none') . "\n";
