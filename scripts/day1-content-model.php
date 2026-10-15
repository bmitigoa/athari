<?php

/**
 * @file
 * Day 1: builds the Athari content model (vocabularies, terms, content types,
 * fields, form/view displays and Pathauto patterns).
 *
 * Run once with: ddev drush php:script scripts/day1-content-model.php
 * The resulting configuration is exported to config/sync; that export, not
 * this script, is the source of truth.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Creates a field storage + instance and wires it into both displays.
 */
function athari_add_field(string $entity_type, string $bundle, string $name, string $type, string $label, array $opts = []): void {
  $storage_settings = $opts['storage'] ?? [];
  if (!FieldStorageConfig::loadByName($entity_type, $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => $type,
      'cardinality' => $opts['cardinality'] ?? 1,
      'settings' => $storage_settings,
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

/**
 * Helper for entity reference options.
 */
function athari_ref(string $target_type, array $bundles): array {
  return [
    'storage' => ['target_type' => $target_type],
    'settings' => [
      'handler' => "default:$target_type",
      'handler_settings' => [
        'target_bundles' => array_combine($bundles, $bundles),
        'auto_create' => FALSE,
      ],
    ],
  ];
}

// ---------------------------------------------------------------------------
// Vocabularies and terms.
// ---------------------------------------------------------------------------
foreach (['sector' => 'Sector', 'region' => 'Region'] as $vid => $label) {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create(['vid' => $vid, 'name' => $label, 'description' => "$label classification for Athari projects."])->save();
  }
}

$term_ids = [];
$make_term = function (string $vid, string $name, int $parent = 0, int $weight = 0) use (&$term_ids): int {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
  if ($existing) {
    return (int) reset($existing)->id();
  }
  $term = Term::create(['vid' => $vid, 'name' => $name, 'parent' => $parent ? [$parent] : [], 'weight' => $weight]);
  $term->save();
  return (int) $term->id();
};

foreach (['Health', 'Education', 'WASH', 'Livelihoods', 'Protection'] as $i => $name) {
  $make_term('sector', $name, 0, $i);
}

$regions = [
  'Central' => ['Kampala', 'Wakiso', 'Mukono', 'Masaka', 'Mpigi'],
  'Eastern' => ['Jinja', 'Mbale', 'Soroti', 'Tororo', 'Busia'],
  'Northern' => ['Gulu', 'Lira', 'Arua', 'Kitgum', 'Adjumani'],
  'Western' => ['Mbarara', 'Kabarole', 'Kasese', 'Kabale', 'Hoima'],
];
foreach ($regions as $region => $districts) {
  $rid = $make_term('region', $region);
  foreach ($districts as $district) {
    $make_term('region', $district, $rid);
  }
}

// ---------------------------------------------------------------------------
// Content types.
// ---------------------------------------------------------------------------
$types = [
  'project' => ['Project', 'An NGO programme or intervention with a budget, timeline, sector and region.'],
  'location' => ['Location', 'A named project site with map coordinates.'],
  'partner' => ['Partner', 'An implementing, funding or community partner organisation.'],
  'report' => ['Report', 'A field report on a project: beneficiaries reached, funds spent and narrative.'],
];
foreach ($types as $id => [$name, $desc]) {
  if (!NodeType::load($id)) {
    NodeType::create([
      'type' => $id,
      'name' => $name,
      'description' => $desc,
      'new_revision' => TRUE,
      'display_submitted' => FALSE,
      'preview_mode' => 0,
    ])->save();
  }
}

// Partner.
athari_add_field('node', 'partner', 'field_logo', 'image', 'Logo', [
  'widget' => 'image_image', 'formatter' => 'image', 'weight' => 1, 'label_display' => 'hidden',
  'settings' => ['file_extensions' => 'png jpg jpeg svg webp', 'alt_field_required' => FALSE, 'file_directory' => 'partners'],
  'formatter_settings' => ['image_style' => 'medium', 'image_link' => ''],
]);
athari_add_field('node', 'partner', 'field_website', 'link', 'Website', [
  'widget' => 'link_default', 'formatter' => 'link', 'weight' => 2,
  'settings' => ['title' => 0, 'link_type' => 16],
]);

// Location.
athari_add_field('node', 'location', 'field_district', 'entity_reference', 'District', array_merge(athari_ref('taxonomy_term', ['region']), [
  'widget' => 'options_select', 'formatter' => 'entity_reference_label', 'weight' => 1, 'required' => TRUE,
  'formatter_settings' => ['link' => TRUE],
]));
athari_add_field('node', 'location', 'field_coordinates', 'geofield', 'Coordinates', [
  'widget' => 'geofield_latlon', 'formatter' => 'geofield_default', 'weight' => 2, 'required' => TRUE,
  'widget_settings' => ['html5_geolocation' => FALSE],
  'formatter_settings' => ['output_format' => 'string', 'output_escape' => TRUE],
]);

// Project.
athari_add_field('node', 'project', 'body', 'text_with_summary', 'Description', [
  'widget' => 'text_textarea_with_summary', 'formatter' => 'text_default', 'weight' => 1, 'label_display' => 'hidden',
  'settings' => ['display_summary' => TRUE],
]);
athari_add_field('node', 'project', 'field_budget', 'decimal', 'Budget (USD)', array_merge([
  'storage' => ['precision' => 14, 'scale' => 2],
  'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 2,
  'settings' => ['min' => 0, 'prefix' => 'USD '],
  'formatter_settings' => ['scale' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'prefix_suffix' => TRUE],
]));
athari_add_field('node', 'project', 'field_start_date', 'datetime', 'Start date', [
  'storage' => ['datetime_type' => 'date'],
  'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 3,
  'formatter_settings' => ['timezone_override' => '', 'format_type' => 'medium'],
]);
athari_add_field('node', 'project', 'field_end_date', 'datetime', 'End date', [
  'storage' => ['datetime_type' => 'date'],
  'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 4,
  'formatter_settings' => ['timezone_override' => '', 'format_type' => 'medium'],
]);
athari_add_field('node', 'project', 'field_status', 'list_string', 'Status', [
  'storage' => ['allowed_values' => ['planned' => 'Planned', 'active' => 'Active', 'completed' => 'Completed']],
  'widget' => 'options_buttons', 'formatter' => 'list_default', 'weight' => 5, 'required' => TRUE,
]);
athari_add_field('node', 'project', 'field_sector', 'entity_reference', 'Sector', array_merge(athari_ref('taxonomy_term', ['sector']), [
  'widget' => 'options_select', 'formatter' => 'entity_reference_label', 'weight' => 6, 'required' => TRUE,
  'formatter_settings' => ['link' => TRUE],
]));
athari_add_field('node', 'project', 'field_region', 'entity_reference', 'Region', array_merge(athari_ref('taxonomy_term', ['region']), [
  'widget' => 'options_select', 'formatter' => 'entity_reference_label', 'weight' => 7, 'required' => TRUE,
  'description' => 'Region or district where the project operates.',
  'formatter_settings' => ['link' => TRUE],
]));
athari_add_field('node', 'project', 'field_locations', 'entity_reference', 'Locations', array_merge(athari_ref('node', ['location']), [
  'cardinality' => -1,
  'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 8,
  'widget_settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => ''],
  'formatter_settings' => ['link' => TRUE],
]));
athari_add_field('node', 'project', 'field_partners', 'entity_reference', 'Partners', array_merge(athari_ref('node', ['partner']), [
  'cardinality' => -1,
  'widget' => 'entity_reference_autocomplete', 'formatter' => 'entity_reference_label', 'weight' => 9,
  'widget_settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => ''],
  'formatter_settings' => ['link' => TRUE],
]));

// Report.
athari_add_field('node', 'report', 'field_project', 'entity_reference', 'Project', array_merge(athari_ref('node', ['project']), [
  'widget' => 'options_select', 'formatter' => 'entity_reference_label', 'weight' => 1, 'required' => TRUE,
  'formatter_settings' => ['link' => TRUE],
]));
athari_add_field('node', 'report', 'field_report_date', 'datetime', 'Report date', [
  'storage' => ['datetime_type' => 'date'],
  'widget' => 'datetime_default', 'formatter' => 'datetime_default', 'weight' => 2, 'required' => TRUE,
  'formatter_settings' => ['timezone_override' => '', 'format_type' => 'medium'],
]);
athari_add_field('node', 'report', 'field_beneficiaries', 'integer', 'Beneficiaries reached', [
  'widget' => 'number', 'formatter' => 'number_integer', 'weight' => 3, 'required' => TRUE,
  'settings' => ['min' => 0],
  'formatter_settings' => ['thousand_separator' => ',', 'prefix_suffix' => TRUE],
]);
athari_add_field('node', 'report', 'field_funds_spent', 'decimal', 'Funds spent (USD)', [
  'storage' => ['precision' => 14, 'scale' => 2],
  'widget' => 'number', 'formatter' => 'number_decimal', 'weight' => 4, 'required' => TRUE,
  'settings' => ['min' => 0, 'prefix' => 'USD '],
  'formatter_settings' => ['scale' => 2, 'thousand_separator' => ',', 'decimal_separator' => '.', 'prefix_suffix' => TRUE],
]);
athari_add_field('node', 'report', 'field_narrative', 'text_long', 'Narrative', [
  'widget' => 'text_textarea', 'formatter' => 'text_default', 'weight' => 5, 'required' => TRUE,
  'widget_settings' => ['rows' => 8],
]);
athari_add_field('node', 'report', 'field_attachments', 'file', 'Attachments', [
  'cardinality' => -1,
  'storage' => ['display_field' => FALSE],
  'widget' => 'file_generic', 'formatter' => 'file_default', 'weight' => 6,
  'settings' => ['file_extensions' => 'pdf doc docx xls xlsx csv png jpg jpeg', 'file_directory' => 'reports/[date:custom:Y]-[date:custom:m]', 'description_field' => TRUE],
]);

// Hide "promote to front page" and "sticky" on every Athari type.
foreach (array_keys($types) as $bundle) {
  $form = EntityFormDisplay::load("node.$bundle.default");
  foreach (['promote', 'sticky'] as $c) {
    $form->removeComponent($c);
  }
  $form->save();
}

// ---------------------------------------------------------------------------
// Pathauto patterns.
// ---------------------------------------------------------------------------
$patterns = [
  ['project', 'Project', 'node', '/projects/[node:title]', 'project'],
  ['location', 'Location', 'node', '/locations/[node:title]', 'location'],
  ['partner', 'Partner', 'node', '/partners/[node:title]', 'partner'],
  ['report', 'Report', 'node', '/reports/[node:title]', 'report'],
  ['sector', 'Sector', 'taxonomy_term', '/sector/[term:name]', 'sector'],
  ['region', 'Region', 'taxonomy_term', '/region/[term:name]', 'region'],
];
foreach ($patterns as $i => [$id, $label, $entity, $path, $bundle]) {
  if (PathautoPattern::load($id)) {
    continue;
  }
  $pattern = PathautoPattern::create([
    'id' => $id,
    'label' => $label,
    'type' => "canonical_entities:$entity",
    'pattern' => $path,
    'weight' => $i,
  ]);
  $pattern->addSelectionCondition([
    'id' => "entity_bundle:$entity",
    'bundles' => [$bundle => $bundle],
    'negate' => FALSE,
    'context_mapping' => [$entity => $entity],
  ]);
  $pattern->save();
}

print "Athari content model created.\n";
