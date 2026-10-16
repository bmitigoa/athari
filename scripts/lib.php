<?php

/**
 * @file
 * Shared helpers for the Athari build scripts.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Creates a field storage + instance and wires it into both displays.
 */
function athari_lib_add_field(string $entity_type, string $bundle, string $name, string $type, string $label, array $opts = []): void {
  if (!FieldStorageConfig::loadByName($entity_type, $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => $type,
      'cardinality' => $opts['cardinality'] ?? 1,
      'settings' => $opts['storage'] ?? [],
    ])->save();
  }
  if (!FieldConfig::loadByName($entity_type, $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $label,
      'required' => $opts['required'] ?? FALSE,
      'description' => $opts['description'] ?? '',
      'settings' => $opts['settings'] ?? [],
    ])->save();
  }
  $form = EntityFormDisplay::load("$entity_type.$bundle.default")
    ?? EntityFormDisplay::create(['targetEntityType' => $entity_type, 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $form->setComponent($name, [
    'type' => $opts['widget'],
    'weight' => $opts['weight'] ?? 0,
    'settings' => $opts['widget_settings'] ?? [],
  ])->save();
  $view = EntityViewDisplay::load("$entity_type.$bundle.default")
    ?? EntityViewDisplay::create(['targetEntityType' => $entity_type, 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $view->setComponent($name, [
    'type' => $opts['formatter'],
    'label' => $opts['label_display'] ?? 'above',
    'weight' => $opts['weight'] ?? 0,
    'settings' => $opts['formatter_settings'] ?? [],
  ])->save();
}
