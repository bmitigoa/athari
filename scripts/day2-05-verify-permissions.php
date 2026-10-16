<?php

/**
 * @file
 * Day 2: server-side permission and workflow verification.
 *
 * Switches to each test user and asks Drupal's access and moderation systems
 * what that user may do. Nothing is saved, so no content changes. Output is
 * one line per check: CHECK|role|description|expected|actual|PASS or FAIL.
 *
 * Run with: ddev drush php:script scripts/day2-05-verify-permissions.php
 */

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\node\Entity\Node;

$tm = \Drupal::entityTypeManager();
$switcher = \Drupal::service('account_switcher');
$validation = \Drupal::service('content_moderation.state_transition_validation');
$db = \Drupal::database();

$accounts = [
  'anonymous' => new AnonymousUserSession(),
  'fieldofficer' => user_load_by_name('fieldofficer'),
  'reviewer' => user_load_by_name('reviewer'),
  'ngoadmin' => user_load_by_name('ngoadmin'),
];

/**
 * Returns a report node in the given moderation state by the given author.
 */
function athari_verify_report(string $state, string $author): Node {
  $uid = user_load_by_name($author)->id();
  $nid = \Drupal::database()->query(
    "SELECT n.nid FROM {node_field_data} n JOIN {content_moderation_state_field_data} m
     ON m.content_entity_id = n.nid AND m.content_entity_type_id = 'node'
     WHERE n.type = 'report' AND m.moderation_state = :s AND n.uid = :u ORDER BY n.nid LIMIT 1",
    [':s' => $state, ':u' => $uid]
  )->fetchField();
  return Node::load($nid);
}

$officer_draft = athari_verify_report('draft', 'fieldofficer');
$officer_review = athari_verify_report('in_review', 'fieldofficer');
$officer_published = athari_verify_report('published', 'fieldofficer');
$admin_archived = athari_verify_report('archived', 'ngoadmin');
$admin_published = athari_verify_report('published', 'ngoadmin');
$project = $tm->getStorage('node')->loadByProperties(['type' => 'project']);
$project = reset($project);

$fails = 0;
$report = function (string $role, string $what, $expected, $actual) use (&$fails) {
  $e = is_array($expected) ? implode(',', $expected) : ($expected ? 'yes' : 'no');
  $a = is_array($actual) ? implode(',', $actual) : ($actual ? 'yes' : 'no');
  $ok = $e === $a;
  $fails += $ok ? 0 : 1;
  print "CHECK|$role|$what|$e|$a|" . ($ok ? 'PASS' : 'FAIL') . "\n";
};

// Role => expected result per access check.
$access = [
  'View published report' => [fn($a) => $officer_published->access('view', $a), ['anonymous' => 1, 'fieldofficer' => 1, 'reviewer' => 1, 'ngoadmin' => 1]],
  'View own draft report' => [fn($a) => $officer_draft->access('view', $a), ['anonymous' => 0, 'fieldofficer' => 1, 'reviewer' => 1, 'ngoadmin' => 1]],
  "View someone else's archived report" => [fn($a) => $admin_archived->access('view', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 1, 'ngoadmin' => 1]],
  'Create report' => [fn($a) => $tm->getAccessControlHandler('node')->createAccess('report', $a), ['anonymous' => 0, 'fieldofficer' => 1, 'reviewer' => 0, 'ngoadmin' => 1]],
  'Create project' => [fn($a) => $tm->getAccessControlHandler('node')->createAccess('project', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 0, 'ngoadmin' => 1]],
  'Create location' => [fn($a) => $tm->getAccessControlHandler('node')->createAccess('location', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 0, 'ngoadmin' => 1]],
  'Create partner' => [fn($a) => $tm->getAccessControlHandler('node')->createAccess('partner', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 0, 'ngoadmin' => 1]],
  'Edit a field officer draft report' => [fn($a) => $officer_draft->access('update', $a), ['anonymous' => 0, 'fieldofficer' => 1, 'reviewer' => 0, 'ngoadmin' => 1]],
  "Edit someone else's published report" => [fn($a) => $admin_published->access('update', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 1, 'ngoadmin' => 1]],
  'Edit project' => [fn($a) => $project->access('update', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 0, 'ngoadmin' => 1]],
  'Delete a draft report' => [fn($a) => $officer_draft->access('delete', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 0, 'ngoadmin' => 1]],
  'Delete project' => [fn($a) => $project->access('delete', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 0, 'ngoadmin' => 1]],
  'Create Sector term' => [fn($a) => $tm->getAccessControlHandler('taxonomy_term')->createAccess('sector', $a), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 0, 'ngoadmin' => 1]],
  'See content overview (/admin/content)' => [fn($a) => $a->hasPermission('access content overview'), ['anonymous' => 0, 'fieldofficer' => 1, 'reviewer' => 1, 'ngoadmin' => 1]],
  'Administer users' => [fn($a) => $a->hasPermission('administer users'), ['anonymous' => 0, 'fieldofficer' => 0, 'reviewer' => 0, 'ngoadmin' => 1]],
];
foreach ($access as $what => [$check, $expected]) {
  foreach ($accounts as $role => $account) {
    $report($role, $what, (bool) $expected[$role], (bool) $check($account));
  }
}

// Allowed transitions from each state, per role.
$transitions = [
  'Transitions from Draft' => [$officer_draft, ['fieldofficer' => ['create_draft', 'submit_for_review'], 'reviewer' => [], 'ngoadmin' => ['create_draft', 'submit_for_review']]],
  'Transitions from In Review' => [$officer_review, ['fieldofficer' => [], 'reviewer' => ['return_to_draft', 'publish'], 'ngoadmin' => ['return_to_draft', 'publish']]],
  'Transitions from Published' => [$officer_published, ['fieldofficer' => [], 'reviewer' => ['republish', 'archive'], 'ngoadmin' => ['republish', 'archive']]],
  'Transitions from Archived' => [$admin_archived, ['fieldofficer' => [], 'reviewer' => [], 'ngoadmin' => ['restore']]],
];
foreach ($transitions as $what => [$node, $expected]) {
  foreach ($expected as $role => $ids) {
    $actual = array_keys($validation->getValidTransitions($node, $accounts[$role]));
    sort($actual);
    sort($ids);
    $report($role, $what, $ids, $actual);
  }
}

// Attempted state changes (validated, never saved).
$attempts = [
  ['fieldofficer', $officer_draft, 'in_review', TRUE, 'Field officer submits own draft for review'],
  ['fieldofficer', $officer_draft, 'published', FALSE, 'Field officer tries to publish a draft directly'],
  ['fieldofficer', $officer_review, 'published', FALSE, 'Field officer tries to publish a report in review'],
  ['fieldofficer', $officer_review, 'draft', FALSE, 'Field officer tries to pull a report back from review'],
  ['reviewer', $officer_review, 'published', TRUE, 'Reviewer approves a report in review'],
  ['reviewer', $officer_review, 'draft', TRUE, 'Reviewer rejects a report in review'],
  ['reviewer', $officer_draft, 'published', FALSE, 'Reviewer tries to publish a draft that was never submitted'],
  ['reviewer', $officer_published, 'archived', TRUE, 'Reviewer archives a published report'],
  ['reviewer', $admin_archived, 'draft', FALSE, 'Reviewer tries to restore an archived report'],
  ['ngoadmin', $admin_archived, 'draft', TRUE, 'Admin restores an archived report'],
  ['ngoadmin', $officer_draft, 'published', FALSE, 'Admin tries to skip review (draft straight to published)'],
];
foreach ($attempts as [$role, $node, $to, $expect_ok, $what]) {
  $switcher->switchTo($accounts[$role]);
  $copy = Node::load($node->id());
  $copy->set('moderation_state', $to);
  $violations = $copy->validate();
  $switcher->switchBack();
  $report($role, $what, $expect_ok, count($violations) === 0);
}

print "SUMMARY|" . ($fails === 0 ? 'ALL CHECKS PASSED' : "$fails CHECK(S) FAILED") . "\n";
