<?php

/**
 * @file
 * Day 3: Projects listing view (/projects as cards, /projects/table as table).
 *
 * Run with: ddev drush php:script scripts/day3-02-view-projects.php
 * Re-running replaces the view, so the script is safe to repeat.
 */

use Drupal\views\Entity\View;

if ($existing = View::load('projects')) {
  $existing->delete();
}

/**
 * Builds a field handler definition for a node field.
 */
function athari_pf(string $field, array $extra = []): array {
  return $extra + [
    'id' => $field,
    'table' => "node__$field",
    'field' => $field,
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'label' => '',
    'exclude' => FALSE,
    'alter' => ['alter_text' => FALSE, 'make_link' => FALSE, 'absolute' => FALSE, 'word_boundary' => FALSE, 'ellipsis' => FALSE, 'strip_tags' => FALSE, 'trim' => FALSE, 'html' => FALSE],
    'element_type' => '',
    'element_class' => '',
    'element_label_type' => '',
    'element_label_class' => '',
    'element_label_colon' => FALSE,
    'element_wrapper_type' => '',
    'element_wrapper_class' => '',
    'element_default_classes' => TRUE,
    'empty' => '',
    'hide_empty' => FALSE,
    'empty_zero' => FALSE,
    'hide_alter_empty' => TRUE,
    'click_sort_column' => 'value',
    'type' => 'string',
    'settings' => [],
    'group_column' => 'value',
    'group_columns' => [],
    'group_rows' => TRUE,
    'delta_limit' => 0,
    'delta_offset' => 0,
    'delta_reversed' => FALSE,
    'delta_first_last' => FALSE,
    'multi_type' => 'separator',
    'separator' => ', ',
    'field_api_classes' => FALSE,
    'plugin_id' => 'field',
  ];
}

$title_field = [
  'id' => 'title',
  'table' => 'node_field_data',
  'field' => 'title',
  'relationship' => 'none',
  'group_type' => 'group',
  'admin_label' => '',
  'entity_type' => 'node',
  'entity_field' => 'title',
  'plugin_id' => 'field',
  'label' => '',
  'exclude' => FALSE,
  'alter' => ['alter_text' => FALSE, 'make_link' => FALSE, 'absolute' => FALSE, 'word_boundary' => FALSE, 'ellipsis' => FALSE, 'strip_tags' => FALSE, 'trim' => FALSE, 'html' => FALSE],
  'element_type' => 'h3',
  'element_class' => 'athari-card__title',
  'element_label_type' => '',
  'element_label_class' => '',
  'element_label_colon' => FALSE,
  'element_wrapper_type' => '',
  'element_wrapper_class' => '',
  'element_default_classes' => FALSE,
  'empty' => '',
  'hide_empty' => FALSE,
  'empty_zero' => FALSE,
  'hide_alter_empty' => TRUE,
  'click_sort_column' => 'value',
  'type' => 'string',
  'settings' => ['link_to_entity' => TRUE],
  'group_column' => 'value',
  'group_columns' => [],
  'group_rows' => TRUE,
  'delta_limit' => 0,
  'delta_offset' => 0,
  'delta_reversed' => FALSE,
  'delta_first_last' => FALSE,
  'multi_type' => 'separator',
  'separator' => ', ',
  'field_api_classes' => FALSE,
];

$label_formatter = ['type' => 'entity_reference_label', 'settings' => ['link' => FALSE]];

$card_fields = [
  'title' => $title_field,
  'field_status' => athari_pf('field_status', ['type' => 'list_default', 'element_type' => 'div', 'element_class' => 'athari-pill', 'element_default_classes' => FALSE]),
  'field_sector' => athari_pf('field_sector', $label_formatter + ['element_type' => 'div', 'element_class' => 'athari-card__meta', 'label' => 'Sector', 'element_label_colon' => TRUE, 'click_sort_column' => 'target_id']),
  'field_region' => athari_pf('field_region', $label_formatter + ['element_type' => 'div', 'element_class' => 'athari-card__meta', 'label' => 'Region', 'element_label_colon' => TRUE, 'click_sort_column' => 'target_id']),
  'field_budget' => athari_pf('field_budget', [
    'type' => 'number_decimal',
    'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 0, 'prefix_suffix' => TRUE],
    'element_type' => 'div', 'element_class' => 'athari-card__meta', 'label' => 'Budget', 'element_label_colon' => TRUE,
  ]),
];

$table_fields = [
  'title' => ['element_type' => '', 'element_class' => ''] + $title_field,
  'field_sector' => athari_pf('field_sector', $label_formatter + ['label' => 'Sector', 'click_sort_column' => 'target_id']),
  'field_region' => athari_pf('field_region', $label_formatter + ['label' => 'Region', 'click_sort_column' => 'target_id']),
  'field_status' => athari_pf('field_status', ['type' => 'list_default', 'label' => 'Status']),
  'field_budget' => athari_pf('field_budget', [
    'type' => 'number_decimal',
    'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 0, 'prefix_suffix' => TRUE],
    'label' => 'Budget',
  ]),
  'field_start_date' => athari_pf('field_start_date', ['type' => 'datetime_default', 'settings' => ['timezone_override' => '', 'format_type' => 'html_date'], 'label' => 'Start', 'click_sort_column' => 'value']),
  'field_end_date' => athari_pf('field_end_date', ['type' => 'datetime_default', 'settings' => ['timezone_override' => '', 'format_type' => 'html_date'], 'label' => 'End', 'click_sort_column' => 'value']),
];
$table_fields['title']['element_type'] = '';
$table_fields['title']['element_class'] = '';
$table_fields['title']['label'] = 'Project';

$exposed = function (string $identifier, string $label): array {
  return [
    'operator_id' => $identifier . '_op',
    'label' => $label,
    'description' => '',
    'use_operator' => FALSE,
    'operator' => $identifier . '_op',
    'operator_limit_selection' => FALSE,
    'operator_list' => [],
    'identifier' => $identifier,
    'required' => FALSE,
    'remember' => FALSE,
    'multiple' => FALSE,
    'remember_roles' => ['authenticated' => 'authenticated'],
    'reduce' => FALSE,
  ];
};

$toggle = function (string $current): string {
  $grid = $current === 'grid' ? ' class="is-current"' : '';
  $table = $current === 'table' ? ' class="is-current"' : '';
  return '<div class="athari-toggle" role="group" aria-label="Display"><a href="/projects"' . $grid . '>Cards</a><a href="/projects/table"' . $table . '>Table</a></div>';
};

$header = function (string $current) use ($toggle): array {
  return [
    'toggle' => [
      'id' => 'area', 'table' => 'views', 'field' => 'area', 'relationship' => 'none', 'group_type' => 'group',
      'admin_label' => '', 'empty' => TRUE, 'tokenize' => FALSE, 'content' => ['value' => $toggle($current), 'format' => 'full_html'],
      'plugin_id' => 'text',
    ],
    'result' => [
      'id' => 'result', 'table' => 'views', 'field' => 'result', 'relationship' => 'none', 'group_type' => 'group',
      'admin_label' => '', 'empty' => FALSE, 'content' => 'Showing @total project(s)', 'plugin_id' => 'result',
    ],
  ];
};

$view = View::create([
  'id' => 'projects',
  'label' => 'Projects',
  'module' => 'views',
  'description' => 'Browse all projects with sector, region and status filters.',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default',
      'display_title' => 'Default',
      'display_plugin' => 'default',
      'position' => 0,
      'display_options' => [
        'title' => 'Projects',
        'fields' => $card_fields,
        'pager' => ['type' => 'full', 'options' => ['items_per_page' => 12, 'offset' => 0, 'id' => 0, 'total_pages' => NULL, 'tags' => ['previous' => 'â€¹ Previous', 'next' => 'Next â€º', 'first' => 'Â« First', 'last' => 'Last Â»'], 'expose' => ['items_per_page' => FALSE], 'quantity' => 5]],
        'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Filter', 'reset_button' => TRUE, 'reset_button_label' => 'Reset', 'exposed_sorts_label' => 'Sort by', 'expose_sort_order' => TRUE, 'sort_asc_label' => 'Asc', 'sort_desc_label' => 'Desc']],
        'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
        'cache' => ['type' => 'tag', 'options' => []],
        'empty' => [
          'area_text_custom' => [
            'id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'relationship' => 'none', 'group_type' => 'group',
            'admin_label' => '', 'empty' => TRUE, 'tokenize' => FALSE, 'content' => 'No projects match these filters.', 'plugin_id' => 'text_custom',
          ],
        ],
        'sorts' => [
          'field_start_date_value' => [
            'id' => 'field_start_date_value', 'table' => 'node__field_start_date', 'field' => 'field_start_date_value',
            'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'order' => 'DESC', 'exposed' => FALSE,
            'expose' => ['label' => ''], 'granularity' => 'second', 'plugin_id' => 'datetime',
          ],
        ],
        'arguments' => [],
        'filters' => [
          'status' => ['id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'entity_type' => 'node', 'entity_field' => 'status', 'plugin_id' => 'boolean', 'value' => '1', 'group' => 1, 'expose' => ['operator' => '']],
          'type' => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'entity_type' => 'node', 'entity_field' => 'type', 'plugin_id' => 'bundle', 'value' => ['project' => 'project'], 'group' => 1, 'expose' => ['operator' => '']],
          'sector' => [
            'id' => 'sector', 'table' => 'node__field_sector', 'field' => 'field_sector_target_id', 'relationship' => 'none',
            'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'taxonomy_index_tid', 'operator' => 'or', 'value' => [],
            'group' => 1, 'exposed' => TRUE, 'expose' => $exposed('sector', 'Sector'), 'is_grouped' => FALSE,
            'type' => 'select', 'limit' => TRUE, 'vid' => 'sector', 'hierarchy' => FALSE, 'error_message' => TRUE,
          ],
          'term_node_tid_depth' => [
            'id' => 'term_node_tid_depth', 'table' => 'node_field_data', 'field' => 'term_node_tid_depth', 'relationship' => 'none',
            'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'taxonomy_index_tid_depth', 'operator' => 'or', 'value' => [],
            'group' => 1, 'exposed' => TRUE, 'expose' => $exposed('region', 'Region / district'), 'is_grouped' => FALSE,
            'type' => 'select', 'limit' => TRUE, 'vid' => 'region', 'hierarchy' => TRUE, 'error_message' => TRUE, 'depth' => 1,
          ],
          'field_status_value' => [
            'id' => 'field_status_value', 'table' => 'node__field_status', 'field' => 'field_status_value', 'relationship' => 'none',
            'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'list_field', 'operator' => 'or', 'value' => [],
            'group' => 1, 'exposed' => TRUE, 'expose' => $exposed('status', 'Status'), 'is_grouped' => FALSE,
          ],
        ],
        'style' => ['type' => 'default', 'options' => ['grouping' => [], 'row_class' => 'athari-card', 'default_row_class' => FALSE, 'uses_fields' => TRUE]],
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => FALSE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
        'query' => ['type' => 'views_query', 'options' => ['query_comment' => '', 'disable_sql_rewrite' => FALSE, 'distinct' => TRUE, 'replica' => FALSE, 'query_tags' => []]],
        'use_ajax' => FALSE,
        'css_class' => 'athari-cards',
        'header' => $header('grid'),
        'display_extenders' => [],
      ],
    ],
    'page_1' => [
      'id' => 'page_1',
      'display_title' => 'Cards',
      'display_plugin' => 'page',
      'position' => 1,
      'display_options' => ['path' => 'projects', 'menu' => ['type' => 'none'], 'display_extenders' => []],
    ],
    'page_2' => [
      'id' => 'page_2',
      'display_title' => 'Table',
      'display_plugin' => 'page',
      'position' => 2,
      'display_options' => [
        'path' => 'projects/table',
        'menu' => ['type' => 'none'],
        'display_extenders' => [],
        'defaults' => ['style' => FALSE, 'row' => FALSE, 'fields' => FALSE, 'header' => FALSE, 'css_class' => FALSE],
        'css_class' => '',
        'header' => $header('table'),
        'fields' => $table_fields,
        'style' => [
          'type' => 'table',
          'options' => [
            'grouping' => [], 'row_class' => '', 'default_row_class' => TRUE, 'columns' => array_combine(array_keys($table_fields), array_keys($table_fields)),
            'default' => 'field_start_date', 'info' => array_map(fn($f) => ['sortable' => $f !== 'title' ? TRUE : TRUE, 'default_sort_order' => 'asc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''], $table_fields),
            'override' => TRUE, 'sticky' => FALSE, 'summary' => '', 'empty_table' => FALSE, 'caption' => '', 'description' => '',
          ],
        ],
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
      ],
    ],
  ],
]);
$view->save();
print "projects view created.\n";
