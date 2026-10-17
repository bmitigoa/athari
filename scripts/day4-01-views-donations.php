<?php

/**
 * @file
 * Day 4: donation views, project-page donation blocks, homepage stat card and
 * the real Donate links.
 *
 * Views created: donations_admin (/admin/content/donations), donation_totals
 * (home and project blocks) and project_donate_button. Existing Day 3 views
 * impact_totals, home_hero and home_cta are updated in place.
 *
 * Requires the athari_donate module to be enabled.
 * Run with: ddev drush php:script scripts/day4-01-views-donations.php
 */

require_once __DIR__ . '/lib-views.php';

use Drupal\block\Entity\Block;
use Drupal\views\Entity\View;

// impact_totals embeds donation_totals. Drupal deletes dependent config when a
// view is deleted, so let go of the embed first or impact_totals (and its
// block) would be deleted along with donation_totals on a re-run.
if ($impact = View::load('impact_totals')) {
  $display = &$impact->getDisplay('default');
  unset($display['display_options']['header']['donations']);
  unset($display);
  $impact->save();
}

foreach (['donations_admin', 'donation_totals', 'project_donate_button'] as $id) {
  if ($view = View::load($id)) {
    $view->delete();
  }
}

/**
 * Field handler for a base field of the donation entity.
 */
function athari_dv(string $id, string $field, array $extra = []): array {
  return athari_vf_base($id, 'athari_donation', $field, 'athari_donation', $extra);
}

$rel = athari_vrel('project_rel', 'athari_donation', 'project');
$project_title = athari_vf_base('title', 'node_field_data', 'title', 'node', ['relationship' => 'project_rel', 'label' => 'Project', 'settings' => ['link_to_entity' => TRUE]]);

$exposed = function (string $identifier, string $label): array {
  return [
    'operator_id' => $identifier . '_op', 'label' => $label, 'description' => '', 'use_operator' => FALSE,
    'operator' => $identifier . '_op', 'operator_limit_selection' => FALSE, 'operator_list' => [],
    'identifier' => $identifier, 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE,
    'remember_roles' => ['authenticated' => 'authenticated'], 'reduce' => FALSE,
  ];
};
$no_expose = ['operator_id' => '', 'label' => '', 'description' => '', 'use_operator' => FALSE, 'operator' => '', 'operator_limit_selection' => FALSE, 'operator_list' => [], 'identifier' => '', 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated']];
$no_group = ['label' => '', 'description' => '', 'identifier' => '', 'optional' => TRUE, 'widget' => 'select', 'multiple' => FALSE, 'remember' => FALSE, 'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => []];

// ---------------------------------------------------------------------------
// Totals of successful donations (Views aggregation).
// ---------------------------------------------------------------------------
$success_filter = [
  'status' => [
    'id' => 'status', 'table' => 'athari_donation', 'field' => 'status', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
    'entity_type' => 'athari_donation', 'entity_field' => 'status', 'plugin_id' => 'string', 'operator' => '=', 'value' => 'success', 'group' => 1,
    'exposed' => FALSE, 'expose' => $no_expose, 'is_grouped' => FALSE, 'group_info' => $no_group,
  ],
];
$plain_int = ['type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE]];
$amount_card = athari_dv('amount', 'amount', athari_stat('Donations received', 'donations') + ['type' => 'number_decimal', 'settings' => ['thousand_separator' => '', 'decimal_separator' => '.', 'scale' => 0, 'prefix_suffix' => FALSE], 'group_type' => 'sum', 'alter' => athari_rewrite('KES {{ amount|number_format(0, ".", ",") }}')]);
$count_card = athari_dv('id', 'id', athari_stat('Donations', 'donations') + $plain_int + ['group_type' => 'count']);
$project_arg = [
  'project' => [
    'id' => 'project', 'table' => 'athari_donation', 'field' => 'project', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
    'entity_type' => 'athari_donation', 'entity_field' => 'project', 'plugin_id' => 'entity_target_id',
    'default_action' => 'default', 'exception' => ['value' => 'all', 'title_enable' => FALSE, 'title' => 'All'], 'title_enable' => FALSE, 'title' => '',
    'default_argument_type' => 'node', 'default_argument_options' => [], 'default_argument_skip_url' => FALSE,
    'summary_options' => ['base_path' => '', 'count' => TRUE, 'override' => FALSE, 'items_per_page' => 25],
    'summary' => ['sort_order' => 'asc', 'number_of_records' => 0, 'format' => 'default_summary'],
    'specify_validation' => FALSE, 'validate' => ['type' => 'none', 'fail' => 'not found'], 'break_phrase' => FALSE, 'not' => FALSE,
  ],
];
$one_row_style = ['type' => 'default', 'options' => ['grouping' => [], 'row_class' => '', 'default_row_class' => FALSE, 'uses_fields' => TRUE]];
$bare_row = ['type' => 'fields', 'options' => ['default_field_elements' => FALSE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]];

View::create([
  'id' => 'donation_totals',
  'label' => 'Donation totals',
  'module' => 'views',
  'description' => 'Sum and count of successful donations (Views aggregation).',
  'tag' => 'athari',
  'base_table' => 'athari_donation',
  'base_field' => 'id',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Donations received',
        'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
        'group_by' => TRUE,
        'fields' => ['amount' => $amount_card],
        'filters' => $success_filter,
        'style' => $one_row_style,
        'row' => $bare_row,
        'css_class' => 'athari-stats athari-stats--1',
      ]),
    ],
    // Homepage card: the total only.
    'block_home' => ['id' => 'block_home', 'display_title' => 'Home card', 'display_plugin' => 'block', 'position' => 1, 'display_options' => ['block_description' => 'Donations total card', 'display_extenders' => []]],
    // Project page: raised so far and number of donations, for one project.
    'block_project' => [
      'id' => 'block_project', 'display_title' => 'Project donations', 'display_plugin' => 'block', 'position' => 2,
      'display_options' => [
        'block_description' => 'Project page: donations',
        'display_extenders' => [],
        'defaults' => ['fields' => FALSE, 'arguments' => FALSE, 'css_class' => FALSE],
        'fields' => ['amount' => $amount_card, 'id' => $count_card],
        'arguments' => $project_arg,
        'css_class' => 'athari-stats athari-stats--2',
      ],
    ],
    // Admin report header: overall total and count, restricted to reviewers.
    'block_admin' => [
      'id' => 'block_admin', 'display_title' => 'Admin totals', 'display_plugin' => 'block', 'position' => 3,
      'display_options' => [
        'block_description' => 'Donation totals for the admin report',
        'display_extenders' => [],
        'access' => ['type' => 'perm', 'options' => ['perm' => 'view athari donations']],
        'defaults' => ['fields' => FALSE, 'access' => FALSE, 'css_class' => FALSE],
        'fields' => ['amount' => $amount_card, 'id' => $count_card],
        'css_class' => 'athari-stats athari-stats--2',
      ],
    ],
  ],
])->save();

// ---------------------------------------------------------------------------
// Admin report: /admin/content/donations.
// ---------------------------------------------------------------------------
$status_items = [];
foreach (['pending' => 'Pending', 'success' => 'Success', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $value => $title) {
  $status_items[count($status_items) + 1] = ['title' => $title, 'operator' => '=', 'value' => $value];
}

$admin_fields = [
  'created' => athari_dv('created', 'created', ['label' => 'Date', 'type' => 'timestamp', 'settings' => ['date_format' => 'custom', 'custom_date_format' => 'j M Y, H:i', 'timezone' => '', 'tooltip' => ['date_format' => '', 'custom_date_format' => '']], 'click_sort_column' => 'value']),
  'title' => $project_title,
  'amount' => athari_dv('amount', 'amount', ['label' => 'Amount (KES)', 'type' => 'number_decimal', 'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 0, 'prefix_suffix' => TRUE]]),
  // Phone numbers are personal data: only the start and the last three digits.
  'phone' => athari_dv('phone', 'phone', ['label' => 'Phone', 'alter' => athari_rewrite('{{ phone__value|slice(0, 5) }}****{{ phone__value|slice(-3, 3) }}')]),
  'status' => athari_dv('status', 'status', ['label' => 'Status', 'type' => 'list_default']),
  'mpesa_receipt' => athari_dv('mpesa_receipt', 'mpesa_receipt', ['label' => 'M-Pesa receipt']),
  'result_desc' => athari_dv('result_desc', 'result_desc', ['label' => 'Result', 'alter' => ['alter_text' => FALSE, 'make_link' => FALSE, 'absolute' => FALSE, 'word_boundary' => TRUE, 'ellipsis' => TRUE, 'strip_tags' => FALSE, 'trim' => TRUE, 'max_length' => 60, 'html' => FALSE]]),
];

View::create([
  'id' => 'donations_admin',
  'label' => 'Donations',
  'module' => 'views',
  'description' => 'Donation records with filters. Phone numbers are masked.',
  'tag' => 'athari',
  'base_table' => 'athari_donation',
  'base_field' => 'id',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Donations',
        'access' => ['type' => 'perm', 'options' => ['perm' => 'view athari donations']],
        'relationships' => ['project_rel' => $rel],
        'fields' => $admin_fields,
        'filters' => [
          'status' => [
            'id' => 'status', 'table' => 'athari_donation', 'field' => 'status', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'entity_type' => 'athari_donation', 'entity_field' => 'status', 'plugin_id' => 'string', 'operator' => '=', 'value' => '', 'group' => 1,
            // A grouped filter must also be exposed, or it filters on an empty value.
            'exposed' => TRUE, 'expose' => $exposed('status_unused', 'Status'), 'is_grouped' => TRUE,
            'group_info' => ['label' => 'Status', 'description' => '', 'identifier' => 'status', 'optional' => TRUE, 'widget' => 'select', 'multiple' => FALSE, 'remember' => FALSE, 'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => $status_items],
          ],
          'title' => [
            'id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'relationship' => 'project_rel', 'group_type' => 'group', 'admin_label' => '',
            'entity_type' => 'node', 'entity_field' => 'title', 'plugin_id' => 'string', 'operator' => 'contains', 'value' => '', 'group' => 1,
            'exposed' => TRUE, 'expose' => $exposed('project', 'Project name'), 'is_grouped' => FALSE, 'group_info' => $no_group,
          ],
          'mpesa_receipt' => [
            'id' => 'mpesa_receipt', 'table' => 'athari_donation', 'field' => 'mpesa_receipt', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'entity_type' => 'athari_donation', 'entity_field' => 'mpesa_receipt', 'plugin_id' => 'string', 'operator' => 'contains', 'value' => '', 'group' => 1,
            'exposed' => TRUE, 'expose' => $exposed('receipt', 'M-Pesa receipt'), 'is_grouped' => FALSE, 'group_info' => $no_group,
          ],
          'created' => [
            'id' => 'created', 'table' => 'athari_donation', 'field' => 'created', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'entity_type' => 'athari_donation', 'entity_field' => 'created', 'plugin_id' => 'date', 'operator' => 'between',
            'value' => ['min' => '', 'max' => '', 'value' => '', 'type' => 'date'], 'group' => 1,
            'exposed' => TRUE, 'expose' => $exposed('date', 'Date between (YYYY-MM-DD)') + ['placeholder' => '', 'min_placeholder' => '2026-01-01', 'max_placeholder' => '2026-12-31'], 'is_grouped' => FALSE, 'group_info' => $no_group,
          ],
        ],
        'sorts' => [
          'created' => ['id' => 'created', 'table' => 'athari_donation', 'field' => 'created', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'order' => 'DESC', 'exposed' => FALSE, 'expose' => ['label' => ''], 'granularity' => 'second', 'entity_type' => 'athari_donation', 'entity_field' => 'created', 'plugin_id' => 'date'],
        ],
        'pager' => ['type' => 'full', 'options' => ['items_per_page' => 50, 'offset' => 0, 'id' => 0, 'total_pages' => NULL, 'tags' => ['previous' => '‹ Previous', 'next' => 'Next ›', 'first' => '« First', 'last' => 'Last »'], 'expose' => ['items_per_page' => FALSE], 'quantity' => 5]],
        'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Filter', 'reset_button' => TRUE, 'reset_button_label' => 'Reset', 'exposed_sorts_label' => 'Sort by', 'expose_sort_order' => TRUE, 'sort_asc_label' => 'Asc', 'sort_desc_label' => 'Desc']],
        'style' => [
          'type' => 'table',
          'options' => [
            'grouping' => [], 'row_class' => '', 'default_row_class' => TRUE,
            'columns' => array_combine(array_keys($admin_fields), array_keys($admin_fields)),
            'default' => 'created',
            'info' => array_map(fn($f) => ['sortable' => TRUE, 'default_sort_order' => 'desc', 'align' => '', 'separator' => '', 'empty_column' => FALSE, 'responsive' => ''], $admin_fields),
            'override' => TRUE, 'sticky' => FALSE, 'summary' => '', 'empty_table' => FALSE, 'caption' => '', 'description' => '',
          ],
        ],
        'row' => ['type' => 'fields', 'options' => ['default_field_elements' => TRUE, 'inline' => [], 'separator' => '', 'hide_empty' => FALSE]],
        'header' => [
          'totals' => athari_varea_view('totals', 'donation_totals:block_admin', FALSE),
        ],
        'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'empty' => TRUE, 'tokenize' => FALSE, 'content' => 'No donations match these filters.', 'plugin_id' => 'text_custom']],
      ]),
    ],
    'page_1' => [
      'id' => 'page_1', 'display_title' => 'Donations', 'display_plugin' => 'page', 'position' => 1,
      'display_options' => [
        'path' => 'admin/content/donations',
        'menu' => ['type' => 'normal', 'title' => 'Donations', 'description' => 'Mobile-money donations and totals', 'weight' => 20, 'menu_name' => 'admin', 'parent' => 'system.admin_content'],
        'display_extenders' => [],
      ],
    ],
  ],
])->save();

// ---------------------------------------------------------------------------
// Project page: "Donate to this project" button.
// ---------------------------------------------------------------------------
View::create([
  'id' => 'project_donate_button',
  'label' => 'Project donate button',
  'module' => 'views',
  'description' => 'A button on a project page linking to the donation form for that project.',
  'tag' => 'athari',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => athari_view_base([
        'title' => 'Support this project',
        'fields' => [
          'nid' => athari_vf_base('nid', 'node_field_data', 'nid', 'node', [
            'type' => 'number_integer', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE],
            'alter' => athari_rewrite('<a class="athari-btn athari-btn--earth" href="/donate/{{ nid }}">Donate to this project</a>'),
          ]),
        ],
        'filters' => athari_vfilters_node('project') + [
          'field_status_value' => ['id' => 'field_status_value', 'table' => 'node__field_status', 'field' => 'field_status_value', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '', 'plugin_id' => 'list_field', 'operator' => 'or', 'value' => ['active' => 'active'], 'group' => 1, 'exposed' => FALSE],
        ],
        'arguments' => [
          'nid' => [
            'id' => 'nid', 'table' => 'node_field_data', 'field' => 'nid', 'relationship' => 'none', 'group_type' => 'group', 'admin_label' => '',
            'entity_type' => 'node', 'entity_field' => 'nid', 'plugin_id' => 'node_nid',
            'default_action' => 'default', 'exception' => ['value' => 'all', 'title_enable' => FALSE, 'title' => 'All'], 'title_enable' => FALSE, 'title' => '',
            'default_argument_type' => 'node', 'default_argument_options' => [], 'default_argument_skip_url' => FALSE,
            'summary_options' => ['base_path' => '', 'count' => TRUE, 'override' => FALSE, 'items_per_page' => 25],
            'summary' => ['sort_order' => 'asc', 'number_of_records' => 0, 'format' => 'default_summary'],
            'specify_validation' => FALSE, 'validate' => ['type' => 'none', 'fail' => 'not found'], 'break_phrase' => FALSE, 'not' => FALSE,
          ],
        ],
        'style' => $one_row_style,
        'row' => $bare_row,
      ]),
    ],
    'block_1' => ['id' => 'block_1', 'display_title' => 'Donate button', 'display_plugin' => 'block', 'position' => 1, 'display_options' => ['block_description' => 'Project page: donate button', 'display_extenders' => []]],
  ],
])->save();

// ---------------------------------------------------------------------------
// Homepage: add the donations card, and make the Donate buttons real.
// ---------------------------------------------------------------------------
if ($impact = View::load('impact_totals')) {
  $display = &$impact->getDisplay('default');
  $display['display_options']['header']['donations'] = athari_varea_view('donations', 'donation_totals:block_home');
  unset($display);
  $impact->save();
}

foreach (['home_hero', 'home_cta'] as $id) {
  if (!($view = View::load($id))) {
    continue;
  }
  $display = &$view->getDisplay('default');
  $html = $display['display_options']['header']['area']['content']['value'];
  $html = preg_replace(
    '#<span class="athari-btn athari-btn--earth athari-btn--disabled"[^>]*>Donate \(coming soon\)</span>#',
    '<a class="athari-btn athari-btn--earth" href="/donate">Donate with M-Pesa</a>',
    $html
  );
  $html = str_replace('Soon you will be able to donate to a project of your choice by M-Pesa.', 'Donate to a project of your choice by M-Pesa.', $html);
  $display['display_options']['header']['area']['content']['value'] = $html;
  unset($display);
  $view->save();
}

// ---------------------------------------------------------------------------
// Place the project-page blocks.
// ---------------------------------------------------------------------------
$project_page = ['entity_bundle:node' => ['id' => 'entity_bundle:node', 'negate' => FALSE, 'context_mapping' => ['node' => '@node.node_route_context:node'], 'bundles' => ['project' => 'project']]];
foreach ([
  ['project_donations', 'donation_totals-block_project', 13, 'Donations received'],
  ['project_donate_button', 'project_donate_button-block_1', 14, NULL],
] as [$suffix, $plugin, $weight, $title]) {
  $id = 'athari_theme_' . $suffix;
  if ($existing = Block::load($id)) {
    $existing->delete();
  }
  Block::create([
    'id' => $id, 'theme' => 'athari_theme', 'region' => 'content', 'weight' => $weight,
    'plugin' => 'views_block:' . $plugin,
    'settings' => ['id' => 'views_block:' . $plugin, 'label' => $title ?? '', 'label_display' => $title ? 'visible' : '0', 'provider' => 'views', 'views_label' => '', 'items_per_page' => 'none'],
    'visibility' => $project_page,
  ])->save();
}

print "Donation views, blocks and Donate links created.\n";
