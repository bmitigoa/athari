<?php

/**
 * @file
 * Day 3: impact dashboard views (stat cards and Chart.js charts).
 *
 * Views created: impact_active_projects, impact_totals (block + /impact page),
 * impact_chart_sector, impact_chart_region, impact_chart_time. All totals count
 * PUBLISHED reports only (node status = 1).
 *
 * Run with: ddev drush php:script scripts/day3-03-views-impact.php
 */

require_once __DIR__ . '/lib-views.php';

use Drupal\views\Entity\View;

// Make Chart.js the default charting library and use the Athari palette.
\Drupal::configFactory()->getEditable('charts.settings')
  ->set('charts_default_settings.library', 'chartjs')
  ->set('charts_default_settings.display.colors', ['#2f6b4f', '#c0682f', '#d9a441', '#4f8a67', '#8a5a3c', '#7a9e7e', '#e2a15e'])
  ->save();

foreach (['impact_active_projects', 'impact_totals', 'impact_chart_sector', 'impact_chart_region', 'impact_chart_time'] as $id) {
  if ($view = View::load($id)) {
    $view->delete();
  }
}

$int_format = ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE]];
$usd_format = ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE]];
$chart_int = ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE]];
$chart_dec = ['type' => 'number_decimal', 'settings' => ['thousand_separator' => '', 'decimal_separator' => '.', 'scale' => 0, 'prefix_suffix' => FALSE]];

// ---------------------------------------------------------------------------
// 1. Active project count.
// ---------------------------------------------------------------------------
View::create([
  'id' => 'impact_active_projects',
  'label' => 'Impact: active projects',
  'module' => 'views',
  'description' => 'Count of projects whose status is Active.',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'group_by' => TRUE,
        'fields' => [
          'nid' => athari_vf_base('nid', 'node_field_data', 'nid', 'node', athari_stat('Active projects', 'projects') + $int_format + ['group_type' => 'count', 'group_column' => 'value', 'click_sort_column' => 'value']),
        ],
        'filters' => athari_vfilters_node('project') + [
          'field_status_value' => ['id' => 'field_status_value', 'table' => 'node__field_status', 'field' => 'field_status_value', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'list_field', 'operator' => 'or', 'value' => ['active' => 'active'], 'group' => 1, 'exposed' => FALSE],
        ],
        'style' => ['type' => 'default', 'options' => ['grouping' => [], 'row_class' => '', 'default_row_class' => FALSE, 'uses_fields' => TRUE]],
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => FALSE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
      ]),
    ],
    'block_1' => ['id' => 'block_1', 'display_title' => 'Active projects', 'display_plugin' => 'block', 'position' => 1, 'display_options' => ['block_description' => 'Impact: active projects', 'display_extenders' => []]],
  ],
])->save();

// ---------------------------------------------------------------------------
// 3. Charts. Each is an aggregated view of published reports.
// ---------------------------------------------------------------------------
$project_rel = athari_vrel('project_rel', 'node__field_project', 'field_project');
$term_name = fn(string $relationship) => athari_vf_base('name', 'taxonomy_term_field_data', 'name', 'taxonomy_term', ['relationship' => $relationship, 'label' => 'Name', 'settings' => ['link_to_entity' => FALSE]]);
$name_sort = fn(string $relationship) => [
  'name' => ['id' => 'name', 'table' => 'taxonomy_term_field_data', 'field' => 'name', 'relationship' => $relationship, 'group_type' => 'group', 'admin_label' => '', 'order' => 'ASC', 'exposed' => FALSE, 'expose' => ['label' => ''], 'entity_type' => 'taxonomy_term', 'entity_field' => 'name', 'plugin_id' => 'standard'],
];

$chart_view = function (string $id, string $label, string $description, array $relationships, array $fields, array $sorts, array $style, string $title): void {
  View::create([
    'id' => $id,
    'label' => $label,
    'module' => 'views',
    'description' => $description,
    'tag' => 'athari',
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'display' => [
      'default' => [
        'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
        'display_options' => athari_view_base([
          'title' => $title,
          'group_by' => TRUE,
          'relationships' => array_column($relationships, NULL, 'id'),
          'fields' => $fields,
          'sorts' => $sorts,
          'filters' => athari_vfilters_node('report'),
          'style' => $style,
          'row' => ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
          'css_class' => 'athari-chart',
        ]),
      ],
      'block_1' => ['id' => 'block_1', 'display_title' => $title, 'display_plugin' => 'block', 'position' => 1, 'display_options' => ['block_description' => $label, 'display_extenders' => []]],
    ],
  ])->save();
};

// Beneficiaries reached by sector: report -> project -> sector term.
$chart_view(
  'impact_chart_sector',
  'Impact chart: beneficiaries by sector',
  'Sum of beneficiaries on published reports, grouped by the project sector.',
  [$project_rel, athari_vrel('sector_rel', 'node__field_sector', 'field_sector', ['relationship' => 'project_rel'])],
  [
    'name' => $term_name('sector_rel'),
    'field_beneficiaries' => athari_vf('field_beneficiaries', 'node__field_beneficiaries', 'field_beneficiaries', $chart_int + ['label' => 'Beneficiaries reached', 'group_type' => 'sum']),
  ],
  $name_sort('sector_rel'),
  athari_vchart('column', 'name', 'field_beneficiaries', '#2f6b4f', 'Beneficiaries reached by sector', ['y' => 'People']),
  'Beneficiaries reached by sector'
);

// Funds spent by region: report -> project -> district -> parent region.
$chart_view(
  'impact_chart_region',
  'Impact chart: funds spent by region',
  'Sum of funds spent on published reports, grouped by the parent region of the project district.',
  [
    $project_rel,
    athari_vrel('district_rel', 'node__field_region', 'field_region', ['relationship' => 'project_rel']),
    athari_vrel('region_rel', 'taxonomy_term__parent', 'parent_target_id', ['relationship' => 'district_rel']),
  ],
  [
    'name' => $term_name('region_rel'),
    'field_funds_spent' => athari_vf('field_funds_spent', 'node__field_funds_spent', 'field_funds_spent', $chart_dec + ['label' => 'Funds spent (USD)', 'group_type' => 'sum']),
  ],
  $name_sort('region_rel'),
  athari_vchart('bar', 'name', 'field_funds_spent', '#c0682f', 'Funds spent by region (USD)', ['y' => 'Region', 'x' => 'USD']),
  'Funds spent by region'
);

// Beneficiaries over time: grouped by report date, oldest first.
$chart_view(
  'impact_chart_time',
  'Impact chart: reports over time',
  'Beneficiaries reached on published reports, grouped by report date.',
  [],
  [
    'field_report_date' => athari_vf('field_report_date', 'node__field_report_date', 'field_report_date', ['label' => 'Report date', 'type' => 'datetime_custom', 'settings' => ['timezone_override' => '', 'date_format' => 'M Y'], 'group_type' => 'group', 'click_sort_column' => 'value']),
    'field_beneficiaries' => athari_vf('field_beneficiaries', 'node__field_beneficiaries', 'field_beneficiaries', $chart_int + ['label' => 'Beneficiaries reached', 'group_type' => 'sum']),
  ],
  [
    'field_report_date_value' => ['id' => 'field_report_date_value', 'table' => 'node__field_report_date', 'field' => 'field_report_date_value', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'order' => 'ASC', 'exposed' => FALSE, 'expose' => ['label' => ''], 'granularity' => 'second', 'plugin_id' => 'datetime'],
  ],
  athari_vchart('line', 'field_report_date', 'field_beneficiaries', '#2f6b4f', 'Beneficiaries reached over time', ['y' => 'People']),
  'Beneficiaries reached over time'
);


// ---------------------------------------------------------------------------
// 2. Totals (stat cards) as a block and as the /impact page.
// ---------------------------------------------------------------------------
$totals_fields = [
  'field_beneficiaries' => athari_vf('field_beneficiaries', 'node__field_beneficiaries', 'field_beneficiaries', athari_stat('Beneficiaries reached', 'beneficiaries') + $int_format + ['group_type' => 'sum', 'click_sort_column' => 'value', 'alter' => athari_rewrite('{{ field_beneficiaries|number_format(0, ".", ",") }}')]),
  'field_funds_spent' => athari_vf('field_funds_spent', 'node__field_funds_spent', 'field_funds_spent', athari_stat('Funds spent', 'funds') + $usd_format + ['group_type' => 'sum', 'click_sort_column' => 'value', 'alter' => athari_rewrite('USD {{ field_funds_spent|number_format(0, ".", ",") }}')]),
];

View::create([
  'id' => 'impact_totals',
  'label' => 'Impact totals',
  'module' => 'views',
  'description' => 'Beneficiaries reached and funds spent across published reports (Views aggregation).',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Impact at a glance',
        'group_by' => TRUE,
        'fields' => $totals_fields,
        'filters' => athari_vfilters_node('report'),
        'style' => ['type' => 'default', 'options' => ['grouping' => [], 'row_class' => '', 'default_row_class' => FALSE, 'uses_fields' => TRUE]],
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => FALSE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
        'css_class' => 'athari-stats',
        'header' => ['active' => athari_varea_view('active', 'impact_active_projects:block_1')],
      ]),
    ],
    'block_1' => ['id' => 'block_1', 'display_title' => 'Impact at a glance', 'display_plugin' => 'block', 'position' => 1, 'display_options' => ['block_description' => 'Impact stat cards', 'display_extenders' => []]],
    'page_1' => [
      'id' => 'page_1', 'display_title' => 'Impact dashboard', 'display_plugin' => 'page', 'position' => 2,
      'display_options' => [
        'path' => 'impact',
        'menu' => ['type' => 'none'],
        'title' => 'Impact dashboard',
        'defaults' => ['title' => FALSE, 'footer' => FALSE],
        'footer' => [
          'sector' => athari_varea_view('sector', 'impact_chart_sector:block_1'),
          'region' => athari_varea_view('region', 'impact_chart_region:block_1'),
          'time' => athari_varea_view('time', 'impact_chart_time:block_1'),
        ],
        'display_extenders' => [],
      ],
    ],
  ],
])->save();

print "Impact views created.\n";
