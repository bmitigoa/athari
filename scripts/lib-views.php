<?php

/**
 * @file
 * Shared helpers for building Athari views from PHP.
 */

/**
 * Builds a Views field handler definition.
 *
 * @param string $id
 *   Handler id (unique within the display).
 * @param string $table
 *   Views table, e.g. node__field_budget or node_field_data.
 * @param string $field
 *   Views field name, e.g. field_budget or title.
 * @param array $extra
 *   Overrides merged over the defaults.
 */
function athari_vf(string $id, string $table, string $field, array $extra = []): array {
  return $extra + [
    'id' => $id,
    'table' => $table,
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
    'separator' => '',
    'field_api_classes' => FALSE,
    'plugin_id' => 'field',
  ];
}

/**
 * Stat-card wrapper options for a field.
 */
function athari_stat(string $label, string $modifier): array {
  return [
    'label' => $label,
    'element_type' => 'div',
    'element_class' => 'athari-stat__value',
    'element_label_type' => 'div',
    'element_label_class' => 'athari-stat__label',
    'element_wrapper_type' => 'div',
    'element_wrapper_class' => 'athari-stat athari-stat--' . $modifier,
    'element_default_classes' => FALSE,
  ];
}

/**
 * Rewrite-results options, so aggregated sums keep their thousands separators.
 */
function athari_rewrite(string $text): array {
  return ['alter_text' => TRUE, 'text' => $text, 'make_link' => FALSE, 'absolute' => FALSE, 'word_boundary' => FALSE, 'ellipsis' => FALSE, 'strip_tags' => FALSE, 'trim' => FALSE, 'html' => FALSE];
}

/**
 * Field handler for a base-table property such as a node title.
 */
function athari_vf_base(string $id, string $table, string $field, string $entity_type, array $extra = []): array {
  return athari_vf($id, $table, $field, $extra + ['entity_type' => $entity_type, 'entity_field' => $field]);
}

/**
 * Filter: published nodes of one bundle.
 */
function athari_vfilters_node(string $bundle): array {
  return [
    'status' => ['id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'entity_type' => 'node', 'entity_field' => 'status', 'plugin_id' => 'boolean', 'value' => '1', 'group' => 1, 'expose' => ['operator' => '']],
    'type' => ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'entity_type' => 'node', 'entity_field' => 'type', 'plugin_id' => 'bundle', 'value' => [$bundle => $bundle], 'group' => 1, 'expose' => ['operator' => '']],
  ];
}

/**
 * Relationship definition for an entity reference field.
 */
function athari_vrel(string $id, string $table, string $field, array $extra = []): array {
  return $extra + [
    'id' => $id,
    'table' => $table,
    'field' => $field,
    'relationship' => 'none',
    'group_type' => 'group',
    'admin_label' => '',
    'required' => TRUE,
    'plugin_id' => 'standard',
  ];
}

/**
 * Default display options shared by every Athari view.
 */
function athari_view_base(array $overrides = []): array {
  return $overrides + [
    'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
    'cache' => ['type' => 'tag', 'options' => []],
    'exposed_form' => ['type' => 'basic', 'options' => []],
    'pager' => ['type' => 'none', 'options' => ['offset' => 0]],
    'query' => ['type' => 'views_query', 'options' => ['query_comment' => '', 'disable_sql_rewrite' => FALSE, 'distinct' => FALSE, 'replica' => FALSE, 'query_tags' => []]],
    'use_ajax' => FALSE,
    'display_extenders' => [],
  ];
}

/**
 * Text area handler (header, footer or empty).
 */
function athari_varea_text(string $html, bool $even_if_empty = TRUE): array {
  return [
    'id' => 'area', 'table' => 'views', 'field' => 'area', 'relationship' => 'none', 'group_type' => 'group',
    'admin_label' => '', 'empty' => $even_if_empty, 'tokenize' => FALSE,
    'content' => ['value' => $html, 'format' => 'full_html'], 'plugin_id' => 'text',
  ];
}

/**
 * Area handler that embeds another view display.
 */
function athari_varea_view(string $id, string $view_display, bool $even_if_empty = TRUE): array {
  return [
    'id' => $id, 'table' => 'views', 'field' => 'view', 'relationship' => 'none', 'group_type' => 'group',
    'admin_label' => '', 'empty' => $even_if_empty, 'view_to_insert' => $view_display, 'inherit_arguments' => FALSE,
    'plugin_id' => 'view',
  ];
}

/**
 * Chart style options for a Charts (Chart.js) view.
 */
function athari_vchart(string $type, string $label_field, string $data_field, string $color, string $title, array $axis = []): array {
  $defaults = \Drupal::config('charts.settings')->get('charts_default_settings');
  $settings = $defaults;
  $settings['library'] = 'chartjs';
  $settings['type'] = $type;
  $settings['display']['title'] = $title;
  $settings['display']['legend'] = in_array($type, ['pie', 'donut'], TRUE);
  $settings['display']['legend_position'] = '';
  $settings['display']['data_labels'] = FALSE;
  $settings['display']['dimensions'] = ['width' => '100', 'width_units' => '%', 'height' => '320', 'height_units' => 'px'];
  $settings['display']['colors'] = ['#2f6b4f', '#c0682f', '#d9a441', '#4f8a67', '#8a5a3c', '#7a9e7e', '#e2a15e'];
  $settings['xaxis'] = ['title' => $axis['x'] ?? '', 'labels_rotation' => '0'];
  $settings['yaxis'] = ['title' => $axis['y'] ?? '', 'min' => '', 'max' => '', 'prefix' => $axis['prefix'] ?? '', 'suffix' => '', 'decimal_count' => '0', 'labels_rotation' => '0'];
  $settings['fields'] = [
    'label' => $label_field,
    'data_providers' => [
      $data_field => ['enabled' => 1, 'weight' => '1', 'color' => $color],
    ],
    'allow_advanced_rendering' => FALSE,
  ];
  return [
    'type' => 'chart',
    'options' => [
      'grouping' => [],
      'chart_settings' => $settings,
      'path' => 'charts',
    ],
  ];
}
