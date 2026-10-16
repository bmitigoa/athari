<?php

/**
 * @file
 * Day 3: project map (/map) and the M&E review queue (/review-queue).
 *
 * Run with: ddev drush php:script scripts/day3-04-views-map-review.php
 */

require_once __DIR__ . '/lib-views.php';

use Drupal\views\Entity\View;

foreach (['project_map', 'review_queue'] as $id) {
  if ($view = View::load($id)) {
    $view->delete();
  }
}

// ---------------------------------------------------------------------------
// Project map: one marker per project location, popup links to the project.
// ---------------------------------------------------------------------------
View::create([
  'id' => 'project_map',
  'label' => 'Project map',
  'module' => 'views',
  'description' => 'Leaflet map of project locations. Each popup links to the project.',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Project map',
        'relationships' => ['locations_rel' => athari_vrel('locations_rel', 'node__field_locations', 'field_locations')],
        'fields' => athari_vmap_fields(),
        'filters' => athari_vfilters_node('project'),
        'style' => athari_vleaflet(560, TRUE),
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
        'css_class' => 'athari-map',
      ]),
    ],
    'page_1' => ['id' => 'page_1', 'display_title' => 'Map page', 'display_plugin' => 'page', 'position' => 1, 'display_options' => ['path' => 'map', 'menu' => ['type' => 'none'], 'display_extenders' => []]],
    'block_1' => [
      'id' => 'block_1', 'display_title' => 'Where we work', 'display_plugin' => 'block', 'position' => 2,
      'display_options' => ['block_description' => 'Project map', 'display_extenders' => [], 'defaults' => ['style' => FALSE, 'row' => FALSE], 'style' => athari_vleaflet(420, TRUE), 'row' => ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]]],
    ],
  ],
])->save();

// ---------------------------------------------------------------------------
// Review queue: cloned from core's "Moderated content" view, then narrowed to
// reports in the In Review state and restricted to reviewers and admins.
// ---------------------------------------------------------------------------
$config = \Drupal::config('views.view.moderated_content')->getRawData();
unset($config['uuid'], $config['_core'], $config['dependencies']);
$config['id'] = 'review_queue';
$config['label'] = 'Review queue';
$config['description'] = 'Reports waiting for M&E review.';
$config['tag'] = 'athari';

$default = &$config['display']['default']['display_options'];
$default['title'] = 'Review queue';
$default['access'] = ['type' => 'role', 'options' => ['role' => ['me_reviewer' => 'me_reviewer', 'ngo_admin' => 'ngo_admin', 'administrator' => 'administrator']]];
// Only reports.
$default['filters']['type']['exposed'] = FALSE;
$default['filters']['type']['value'] = ['report' => 'report'];
$default['filters']['type']['expose'] = ['operator_id' => '', 'label' => '', 'description' => '', 'use_operator' => FALSE, 'operator' => '', 'operator_limit_selection' => FALSE, 'operator_list' => [], 'identifier' => '', 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'], 'reduce' => FALSE];
// Only the In Review state.
$default['filters']['moderation_state']['exposed'] = FALSE;
$default['filters']['moderation_state']['value'] = ['report_review-in_review' => 'report_review-in_review'];
$default['filters']['moderation_state']['expose'] = $default['filters']['type']['expose'];
unset($default['filters']['moderation_state_1'], $default['filters']['langcode']);
$default['empty']['area_text_custom']['content'] = 'No reports are waiting for review.';
unset($default);

$page = $config['display']['moderated_content'];
unset($config['display']['moderated_content']);
$page['id'] = 'page_1';
$page['display_title'] = 'Review queue';
$page['display_options']['path'] = 'review-queue';
$page['display_options']['menu'] = ['type' => 'none'];
unset($page['display_options']['tab_options']);
$config['display']['page_1'] = $page;

View::create($config)->save();

print "Map and review queue views created.\n";
