<?php

/**
 * @file
 * Day 2: roles, permissions and the Report moderation workflow.
 *
 * Prerequisite: drush en content_moderation workflows
 * Run with: ddev drush php:script scripts/day2-02-roles-workflow.php
 */

use Drupal\user\Entity\Role;
use Drupal\workflows\Entity\Workflow;

// ---------------------------------------------------------------------------
// Workflow: Draft -> In Review -> Published -> Archived, on Report nodes.
// ---------------------------------------------------------------------------
if (!Workflow::load('report_review')) {
  $workflow = Workflow::create([
    'id' => 'report_review',
    'label' => 'Report review',
    'type' => 'content_moderation',
  ]);
  $plugin = $workflow->getTypePlugin();
  // Content moderation workflows start with Draft and Published already.
  $plugin->addState('in_review', 'In Review');
  $plugin->addState('archived', 'Archived');
  $plugin->addEntityTypeAndBundle('node', 'report');

  $config = $plugin->getConfiguration();
  $flags = [
    // State => [published, default_revision, weight].
    'draft' => [FALSE, FALSE, 0],
    'in_review' => [FALSE, FALSE, 1],
    'published' => [TRUE, TRUE, 2],
    'archived' => [FALSE, TRUE, 3],
  ];
  foreach ($flags as $id => [$published, $default, $weight]) {
    $config['states'][$id]['published'] = $published;
    $config['states'][$id]['default_revision'] = $default;
    $config['states'][$id]['weight'] = $weight;
  }
  $plugin->setConfiguration($config);

  // Replace the default transitions with a strict review chain.
  foreach (array_keys($plugin->getTransitions()) as $default_transition) {
    $plugin->deleteTransition($default_transition);
  }
  $plugin->addTransition('create_draft', 'Save as draft', ['draft'], 'draft');
  $plugin->addTransition('submit_for_review', 'Submit for review', ['draft'], 'in_review');
  $plugin->addTransition('return_to_draft', 'Reject (return to draft)', ['in_review'], 'draft');
  $plugin->addTransition('publish', 'Approve and publish', ['in_review'], 'published');
  $plugin->addTransition('republish', 'Save published report', ['published'], 'published');
  $plugin->addTransition('archive', 'Archive', ['published'], 'archived');
  $plugin->addTransition('restore', 'Restore archived report to draft', ['archived'], 'draft');
  $workflow->save();
}

// ---------------------------------------------------------------------------
// Roles.
// ---------------------------------------------------------------------------
$types = ['project', 'location', 'partner', 'report'];
$vocabs = ['sector', 'region'];

$admin_perms = [
  'access administration pages', 'access content overview', 'access toolbar',
  'view the administration theme', 'administer nodes', 'view own unpublished content',
  'view any unpublished content', 'view all revisions', 'revert all revisions',
  'delete all revisions', 'view latest version', 'administer users',
  'administer permissions', 'administer taxonomy', 'access taxonomy overview',
  'administer url aliases', 'create url aliases', 'administer pathauto',
  'administer workflows',
  'administer content types', 'administer node fields', 'administer node display',
  'administer node form display', 'access user profiles',
];
foreach ($types as $t) {
  array_push($admin_perms, "create $t content", "edit any $t content", "edit own $t content", "delete any $t content", "delete own $t content");
}
foreach ($vocabs as $v) {
  array_push($admin_perms, "create terms in $v", "edit terms in $v", "delete terms in $v");
}
foreach (['create_draft', 'submit_for_review', 'return_to_draft', 'publish', 'republish', 'archive', 'restore'] as $tr) {
  $admin_perms[] = "use report_review transition $tr";
}

$roles = [
  'field_officer' => [
    'label' => 'Field Officer',
    'weight' => 5,
    'permissions' => [
      'access content', 'access toolbar', 'access content overview',
      'view the administration theme', 'view own unpublished content', 'view latest version',
      'create report content', 'edit own report content',
      'use report_review transition create_draft',
      'use report_review transition submit_for_review',
    ],
  ],
  'me_reviewer' => [
    'label' => 'M&E Reviewer',
    'weight' => 6,
    'permissions' => [
      'access content', 'access toolbar', 'access content overview',
      'view the administration theme', 'view any unpublished content', 'view latest version',
      'view all revisions', 'edit any report content',
      'use report_review transition return_to_draft',
      'use report_review transition publish',
      'use report_review transition republish',
      'use report_review transition archive',
    ],
  ],
  'ngo_admin' => [
    'label' => 'Admin',
    'weight' => 7,
    'permissions' => $admin_perms,
  ],
];

foreach ($roles as $id => $def) {
  $role = Role::load($id) ?? Role::create(['id' => $id, 'label' => $def['label'], 'weight' => $def['weight']]);
  foreach (array_unique($def['permissions']) as $perm) {
    $role->grantPermission($perm);
  }
  $role->save();
}

print "Workflow and roles created.\n";
