<?php

/**
 * @file
 * Day 5: locks the JSON:API down to a small, read-only, public data set.
 *
 * Exposed: projects, locations, partners, reports (published only, enforced by
 * athari_api), sectors and regions (provinces and counties).
 * Everything else (users, roles, donations, files, pages, vocabularies, ...)
 * is disabled: "Disabled by default" is switched on, so a resource that is not
 * listed here stays off, including any added in future.
 *
 * Public names are friendlier than the Drupal field names (field_budget is
 * budget) and the base path is /api. Private or internal fields (author,
 * revisions, moderation state, attachments) are switched off.
 *
 * Run with: ddev drush php:script scripts/day5-01-api-config.php
 */

use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;

$extras = \Drupal::configFactory()->getEditable('jsonapi_extras.settings');
// Resource types are listed before the lock-down, so generate field lists first.
$extras->set('default_disabled', FALSE)->set('validate_configuration_integrity', FALSE)->save();
\Drupal::service('router.builder')->rebuild();
\Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
drupal_flush_all_caches();

$repository = \Drupal::service('jsonapi.resource_type.repository');

// Resource id => [public type and path, public field names, extra fields to hide].
$resources = [
  'node--project' => ['projects', [
    'body' => 'description', 'field_budget' => 'budget_kes', 'field_start_date' => 'start_date',
    'field_end_date' => 'end_date', 'field_status' => 'status', 'field_sector' => 'sector',
    'field_region' => 'county', 'field_locations' => 'locations', 'field_partners' => 'partners',
  ]],
  'node--location' => ['locations', [
    'field_district' => 'county', 'field_coordinates' => 'coordinates',
  ]],
  'node--partner' => ['partners', [
    'field_website' => 'website', 'field_logo' => 'logo', 'field_address' => 'address',
  ]],
  'node--report' => ['reports', [
    'field_project' => 'project', 'field_report_date' => 'report_date', 'field_beneficiaries' => 'beneficiaries',
    'field_funds_spent' => 'funds_spent_kes', 'field_narrative' => 'narrative',
  ]],
  'taxonomy_term--sector' => ['sectors', []],
  'taxonomy_term--region' => ['regions', []],
];

// Fields that must never be exposed, wherever they appear.
$hidden = [
  'uid', 'revision_uid', 'revision_user', 'revision_timestamp', 'revision_created', 'revision_log',
  'revision_log_message', 'drupal_internal__vid', 'drupal_internal__revision_id', 'langcode',
  'default_langcode', 'status', 'promote', 'sticky', 'moderation_state', 'field_attachments',
  'content_translation_source', 'content_translation_outdated', 'content_translation_uid',
  'content_translation_created', 'content_translation_changed', 'content_translation_status',
  'weight', 'vid', 'type', 'revision_id', 'revision_translation_affected',
];

foreach ($resources as $id => [$name, $renames]) {
  if ($existing = JsonapiResourceConfig::load($id)) {
    $existing->delete();
  }
  $type = $repository->getByTypeName($id);
  if (!$type) {
    print "No resource type $id\n";
    continue;
  }
  $fields = [];
  foreach ($type->getFields() as $field_name => $field) {
    $internal = $field->getInternalName();
    // The project's own status field is public; the node "published" flag is not.
    $is_hidden = in_array($internal, $hidden, TRUE) && !($id === 'node--project' && $internal === 'field_status');
    $fields[$internal] = [
      'disabled' => $is_hidden,
      'fieldName' => $internal,
      'publicName' => $renames[$internal] ?? $field->getPublicName(),
      'enhancer' => ['id' => ''],
    ];
  }
  JsonapiResourceConfig::create([
    'id' => $id,
    'disabled' => FALSE,
    'path' => $name,
    'resourceType' => $name,
    'resourceFields' => $fields,
  ])->save();
  print "$id -> /api/$name (" . count(array_filter($fields, fn($f) => !$f['disabled'])) . " fields exposed, " . count(array_filter($fields, fn($f) => $f['disabled'])) . " hidden)\n";
}

$extras->set('path_prefix', 'api')
  // A total count would include unpublished reports (it is not access-filtered),
  // which leaks how many drafts exist. Pagination links are enough for clients.
  ->set('include_count', FALSE)
  ->set('default_disabled', TRUE)
  ->set('validate_configuration_integrity', TRUE)
  ->save();

// Read-only is the JSON:API default; make it explicit.
\Drupal::configFactory()->getEditable('jsonapi.settings')->set('read_only', TRUE)->save();

drupal_flush_all_caches();
print "API locked down: read-only, default-disabled, /api prefix.\n";
