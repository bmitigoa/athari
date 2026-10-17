<?php

/**
 * @file
 * Day 3: project-page views, homepage views and their block placements.
 *
 * Views: project_totals, project_locations_map, project_reports (blocks shown
 * on project pages), home_hero, home_featured (front page at /home) and
 * home_cta. Also places the blocks in athari_theme and sets /home as front.
 *
 * Run with: ddev drush php:script scripts/day3-05-views-home-project.php
 */

require_once __DIR__ . '/lib-views.php';

use Drupal\block\Entity\Block;
use Drupal\views\Entity\View;

foreach (['project_totals', 'project_locations_map', 'project_reports', 'home_hero', 'home_featured', 'home_cta'] as $id) {
  if ($view = View::load($id)) {
    $view->delete();
  }
}

/**
 * Contextual filter taking its value from the node on the current page.
 */
function athari_varg_node(string $id, string $table, string $field, string $plugin, string $entity_field = ''): array {
  return [
    $id => [
      'id' => $id, 'table' => $table, 'field' => $field, 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
      'default_action' => 'default', 'exception' => ['value' => 'all', 'title_enable' => FALSE, 'title' => 'All'],
      'title_enable' => FALSE, 'title' => '', 'default_argument_type' => 'node', 'default_argument_options' => [],
      'default_argument_skip_url' => FALSE,
      'summary_options' => ['base_path' => '', 'count' => TRUE, 'override' => FALSE, 'items_per_page' => 25],
      'summary' => ['sort_order' => 'asc', 'number_of_records' => 0, 'format' => 'default_summary'],
      'specify_validation' => FALSE, 'validate' => ['type' => 'none', 'fail' => 'not found'],
      'break_phrase' => FALSE, 'not' => FALSE, 'plugin_id' => $plugin,
    ] + ($entity_field ? ['entity_type' => 'node', 'entity_field' => $entity_field] : []),
  ];
}

$fields_row = ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]];
$plain_style = ['type' => 'default', 'options' => ['grouping' => [], 'row_class' => '', 'default_row_class' => FALSE, 'uses_fields' => TRUE]];
$block_display = fn(string $title, string $description) => ['id' => 'block_1', 'display_title' => $title, 'display_plugin' => 'block', 'position' => 1, 'display_options' => ['block_description' => $description, 'display_extenders' => []]];

// ---------------------------------------------------------------------------
// Project page: per-project totals (published reports only).
// ---------------------------------------------------------------------------
View::create([
  'id' => 'project_totals',
  'label' => 'Project totals',
  'module' => 'views',
  'description' => 'Report count, beneficiaries and funds spent for the project being viewed.',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Results to date',
        'group_by' => TRUE,
        'fields' => [
          'nid' => athari_vf_base('nid', 'node_field_data', 'nid', 'node', athari_stat('Published reports', 'projects') + ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE], 'group_type' => 'count']),
          'field_beneficiaries' => athari_vf('field_beneficiaries', 'node__field_beneficiaries', 'field_beneficiaries', athari_stat('Beneficiaries reached', 'beneficiaries') + ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE], 'group_type' => 'sum', 'alter' => athari_rewrite('{{ field_beneficiaries|number_format(0, ".", ",") }}')]),
          'field_funds_spent' => athari_vf('field_funds_spent', 'node__field_funds_spent', 'field_funds_spent', athari_stat('Funds spent', 'funds') + ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE], 'group_type' => 'sum', 'alter' => athari_rewrite('USD {{ field_funds_spent|number_format(0, ".", ",") }}')]),
        ],
        'filters' => athari_vfilters_node('report'),
        'arguments' => athari_varg_node('field_project_target_id', 'node__field_project', 'field_project_target_id', 'numeric'),
        'style' => $plain_style,
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => FALSE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
        'css_class' => 'athari-stats athari-stats--3',
      ]),
    ],
    'block_1' => $block_display('Results to date', 'Project page: totals'),
  ],
])->save();

// ---------------------------------------------------------------------------
// Project page: small map of the project's locations.
// ---------------------------------------------------------------------------
View::create([
  'id' => 'project_locations_map',
  'label' => 'Project locations map',
  'module' => 'views',
  'description' => 'Small Leaflet map of the locations of the project being viewed.',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Project locations',
        'relationships' => ['locations_rel' => athari_vrel('locations_rel', 'node__field_locations', 'field_locations')],
        'fields' => athari_vmap_fields(),
        'filters' => athari_vfilters_node('project'),
        'arguments' => athari_varg_node('nid', 'node_field_data', 'nid', 'node_nid', 'nid'),
        'style' => athari_vleaflet(300, FALSE),
        'row' => $fields_row,
        'css_class' => 'athari-map athari-map--small',
      ]),
    ],
    'block_1' => $block_display('Project locations', 'Project page: locations map'),
  ],
])->save();

// ---------------------------------------------------------------------------
// Project page: published reports table.
// ---------------------------------------------------------------------------
$report_fields = [
  'field_report_date' => athari_vf('field_report_date', 'node__field_report_date', 'field_report_date', ['label' => 'Date', 'type' => 'datetime_custom', 'settings' => ['timezone_override' => '', 'date_format' => 'j M Y']]),
  'title' => athari_vf_base('title', 'node_field_data', 'title', 'node', ['label' => 'Report', 'settings' => ['link_to_entity' => TRUE]]),
  'field_beneficiaries' => athari_vf('field_beneficiaries', 'node__field_beneficiaries', 'field_beneficiaries', ['label' => 'Beneficiaries', 'type' => 'number_integer', 'settings' => ['thousand_separator' => ',', 'prefix_suffix' => FALSE]]),
  'field_funds_spent' => athari_vf('field_funds_spent', 'node__field_funds_spent', 'field_funds_spent', ['label' => 'Funds spent', 'type' => 'number_decimal', 'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 0, 'prefix_suffix' => TRUE]]),
];
View::create([
  'id' => 'project_reports',
  'label' => 'Project reports',
  'module' => 'views',
  'description' => 'Published field reports for the project being viewed.',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Published reports',
        'fields' => $report_fields,
        'filters' => athari_vfilters_node('report'),
        'arguments' => athari_varg_node('field_project_target_id', 'node__field_project', 'field_project_target_id', 'numeric'),
        'sorts' => [
          'field_report_date_value' => ['id' => 'field_report_date_value', 'table' => 'node__field_report_date', 'field' => 'field_report_date_value', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'order' => 'DESC', 'exposed' => FALSE, 'expose' => ['label' => ''], 'granularity' => 'second', 'plugin_id' => 'datetime'],
        ],
        'style' => [
          'type' => 'table',
          'options' => [
            'grouping' => [], 'row_class' => '', 'default_row_class' => TRUE,
            'columns' => array_combine(array_keys($report_fields), array_keys($report_fields)),
            'default' => '-1', 'info' => array_map(fn($f) => ['sortable' => FALSE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''], $report_fields),
            'override' => TRUE, 'sticky' => FALSE, 'summary' => '', 'empty_table' => FALSE, 'caption' => '', 'description' => '',
          ],
        ],
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
        'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'empty' => TRUE, 'tokenize' => FALSE, 'content' => 'No reports have been published for this project yet.', 'plugin_id' => 'text_custom']],
      ]),
    ],
    'block_1' => $block_display('Published reports', 'Project page: published reports'),
  ],
])->save();

// ---------------------------------------------------------------------------
// Homepage: hero and call to action are text areas in views with no rows.
// ---------------------------------------------------------------------------
$no_rows = ['nid' => ['id' => 'nid', 'table' => 'node_field_data', 'field' => 'nid', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'entity_type' => 'node', 'entity_field' => 'nid', 'plugin_id' => 'numeric', 'operator' => '=', 'value' => ['min' => '', 'max' => '', 'value' => '0'], 'group' => 1, 'exposed' => FALSE]];
$text_view = function (string $id, string $label, string $html) use ($no_rows, $block_display): void {
  View::create([
    'id' => $id,
    'label' => $label,
    'module' => 'views',
    'description' => 'Static homepage content kept in configuration.',
    'tag' => 'athari',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
        'display_options' => athari_view_base([
          'filters' => $no_rows,
          'fields' => ['nid' => athari_vf_base('nid', 'node_field_data', 'nid', 'node', ['type' => 'number_integer'])],
          'style' => ['type' => 'default', 'options' => ['grouping' => [], 'row_class' => '', 'default_row_class' => FALSE, 'uses_fields' => TRUE]],
          'row' => ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
          'header' => ['area' => athari_varea_text($html)],
        ]),
      ],
      'block_1' => $block_display($label, $label),
    ],
  ])->save();
};

$text_view('home_hero', 'Homepage hero', <<<'HTML'
<div class="athari-hero">
  <div class="athari-hero__inner">
    <p class="athari-hero__eyebrow">NGO Project &amp; Impact Portal · Uganda</p>
    <h1 class="athari-hero__title">Tracking change, one community at a time</h1>
    <p class="athari-hero__lead">Athari shows where our projects work, who they reach and what every shilling achieves, from the first field report to the final result.</p>
    <div class="athari-hero__actions">
      <a class="athari-btn" href="/projects">Explore projects</a>
      <a class="athari-btn athari-btn--ghost" href="/impact">See our impact</a>
      <span class="athari-btn athari-btn--earth athari-btn--disabled" role="button" aria-disabled="true" title="Mobile-money donations are coming soon">Donate (coming soon)</span>
    </div>
  </div>
</div>
HTML);

$text_view('home_cta', 'Homepage call to action', <<<'HTML'
<div class="athari-cta">
  <h2>Help us reach more communities</h2>
  <p>Soon you will be able to donate to a project of your choice by M-Pesa. Every contribution is tracked from the first payment to the field report that shows what it achieved.</p>
  <span class="athari-btn athari-btn--earth athari-btn--disabled" role="button" aria-disabled="true" title="Mobile-money donations are coming soon">Donate (coming soon)</span>
</div>
HTML);

// ---------------------------------------------------------------------------
// Homepage main content: featured active projects, served at /home.
// ---------------------------------------------------------------------------
$label_formatter = ['type' => 'entity_reference_label', 'settings' => ['link' => FALSE]];
$featured_fields = [
  'title' => athari_vf_base('title', 'node_field_data', 'title', 'node', ['element_type' => 'h3', 'element_class' => 'athari-card__title', 'element_default_classes' => FALSE, 'settings' => ['link_to_entity' => TRUE]]),
  'field_sector' => athari_vf('field_sector', 'node__field_sector', 'field_sector', $label_formatter + ['label' => 'Sector', 'element_type' => 'span', 'element_wrapper_type' => 'div', 'element_wrapper_class' => 'athari-card__meta', 'element_label_colon' => TRUE, 'click_sort_column' => 'target_id']),
  'field_region' => athari_vf('field_region', 'node__field_region', 'field_region', $label_formatter + ['label' => 'Region', 'element_type' => 'span', 'element_wrapper_type' => 'div', 'element_wrapper_class' => 'athari-card__meta', 'element_label_colon' => TRUE, 'click_sort_column' => 'target_id']),
  'field_budget' => athari_vf('field_budget', 'node__field_budget', 'field_budget', ['label' => 'Budget', 'type' => 'number_decimal', 'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 0, 'prefix_suffix' => TRUE], 'element_type' => 'span', 'element_wrapper_type' => 'div', 'element_wrapper_class' => 'athari-card__meta', 'element_label_colon' => TRUE]),
];
View::create([
  'id' => 'home_featured',
  'label' => 'Homepage: featured projects',
  'module' => 'views',
  'description' => 'The largest active projects, shown on the front page.',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Featured projects',
        'fields' => $featured_fields,
        'filters' => athari_vfilters_node('project') + [
          'field_status_value' => ['id' => 'field_status_value', 'table' => 'node__field_status', 'field' => 'field_status_value', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'list_field', 'operator' => 'or', 'value' => ['active' => 'active'], 'group' => 1, 'exposed' => FALSE],
        ],
        'sorts' => [
          'field_budget_value' => ['id' => 'field_budget_value', 'table' => 'node__field_budget', 'field' => 'field_budget_value', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'order' => 'DESC', 'exposed' => FALSE, 'expose' => ['label' => ''], 'plugin_id' => 'standard'],
        ],
        'pager' => ['type' => 'some', 'options' => ['items_per_page' => 6, 'offset' => 0]],
        'style' => ['type' => 'default', 'options' => ['grouping' => [], 'row_class' => 'athari-card', 'default_row_class' => FALSE, 'uses_fields' => TRUE]],
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => FALSE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
        'css_class' => 'athari-cards',
        'header' => ['area' => athari_varea_text('<h2 class="athari-section-title">Featured projects</h2>')],
        'footer' => ['area' => athari_varea_text('<p><a class="athari-btn" href="/projects">See all projects</a></p>')],
      ]),
    ],
    'page_1' => ['id' => 'page_1', 'display_title' => 'Home', 'display_plugin' => 'page', 'position' => 1, 'display_options' => ['path' => 'home', 'menu' => ['type' => 'none'], 'display_extenders' => []]],
  ],
])->save();

\Drupal::configFactory()->getEditable('system.site')->set('page.front', '/home')->save();

// ---------------------------------------------------------------------------
// Block placements in athari_theme.
// ---------------------------------------------------------------------------
$front = ['request_path' => ['id' => 'request_path', 'negate' => FALSE, 'pages' => '<front>']];
$project_page = ['entity_bundle:node' => ['id' => 'entity_bundle:node', 'negate' => FALSE, 'context_mapping' => ['node' => '@node.node_route_context:node'], 'bundles' => ['project' => 'project']]];

// Suffix, view block, region, weight, visible block title (NULL hides it), visibility.
$placements = [
  ['home_hero', 'home_hero-block_1', 'hero', 0, NULL, $front],
  ['impact_stats', 'impact_totals-block_1', 'content_above', -10, 'Impact at a glance', $front],
  ['home_map', 'project_map-block_1', 'content_below', 0, 'Where we work', $front],
  ['home_cta', 'home_cta-block_1', 'content_below', 5, NULL, $front],
  ['project_totals', 'project_totals-block_1', 'content', 10, 'Results to date', $project_page],
  ['project_locations', 'project_locations_map-block_1', 'content', 11, 'Project locations', $project_page],
  ['project_reports', 'project_reports-block_1', 'content', 12, 'Published reports', $project_page],
];
foreach ($placements as [$suffix, $plugin, $region, $weight, $block_title, $visibility]) {
  $id = 'athari_theme_' . $suffix;
  if ($existing = Block::load($id)) {
    $existing->delete();
  }
  Block::create([
    'id' => $id,
    'theme' => 'athari_theme',
    'region' => $region,
    'weight' => $weight,
    'plugin' => 'views_block:' . $plugin,
    'settings' => ['id' => 'views_block:' . $plugin, 'label' => $block_title ?? '', 'label_display' => $block_title ? 'visible' : '0', 'provider' => 'views', 'views_label' => '', 'items_per_page' => 'none'],
    'visibility' => $visibility,
  ])->save();
}

// The front page has its own heading in the hero, so hide the page title there.
if ($title = Block::load('athari_theme_page_title')) {
  $title->setVisibilityConfig('request_path', ['id' => 'request_path', 'negate' => TRUE, 'pages' => '<front>']);
  $title->save();
}

print "Homepage and project-page views created; blocks placed.\n";
