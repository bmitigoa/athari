<?php

/**
 * @file
 * Day 5: checks the site's security configuration from the inside.
 *
 * It reads the EXPORTED configuration (config/sync) for production-relevant
 * values, so it proves what a new server would get, not just what DDEV shows.
 * Output lines: SEC|check|expected|actual|PASS or FAIL.
 *
 * Run with: ddev drush php:script scripts/day5-04-security-check.php
 */

use Drupal\Core\Site\Settings;
use Drupal\user\Entity\Role;

$fails = 0;
$checks = 0;
$check = function (string $what, $expected, $actual) use (&$fails, &$checks) {
  $checks++;
  $ok = $expected === $actual;
  $fails += $ok ? 0 : 1;
  $fmt = fn($v) => is_bool($v) ? ($v ? 'yes' : 'no') : (is_array($v) ? json_encode($v) : (string) $v);
  print "SEC|$what|" . $fmt($expected) . '|' . $fmt($actual) . '|' . ($ok ? 'PASS' : 'FAIL') . "\n";
};

$sync = \Drupal::service('config.storage.sync');

// --- Production configuration, as exported ---------------------------------------------
$extension = $sync->read('core.extension');
$modules = array_keys($extension['module']);
$check('Exported config: Devel is not enabled', [], array_values(array_intersect($modules, ['devel', 'devel_generate'])));
$check('Exported config: error display is off', 'hide', $sync->read('system.logging')['error_level']);
$check('Exported config: cron fallback every 3 hours', 10800, $sync->read('automated_cron.settings')['interval']);
$check('Exported config: Update Status module is on', TRUE, in_array('update', $modules, TRUE));
$check('Exported config: public registration is admin-only', 'admin_only', $sync->read('user.settings')['register']);
$check('Exported config: JSON:API is read-only', TRUE, $sync->read('jsonapi.settings')['read_only']);
$check('Exported config: new JSON:API resources are off by default', TRUE, $sync->read('jsonapi_extras.settings')['default_disabled']);
$split = $sync->read('config_split.config_split.dev');
$check('Dev split exists and is OFF in the export', FALSE, (bool) $split['status']);
$check('Dev split holds Devel and Devel Generate', ['devel', 'devel_generate'], array_keys($split['module']));
$dev_dir = DRUPAL_ROOT . '/../config/dev';
$check('Dev split folder has the Devel configuration', TRUE, file_exists($dev_dir . '/devel.settings.yml'));

// --- No credentials in exported config ------------------------------------------------------
$donate = $sync->read('athari_donate.settings');
$check('Exported config: Daraja consumer key is empty', '', $donate['consumer_key']);
$check('Exported config: Daraja consumer secret is empty', '', $donate['consumer_secret']);
$check('Exported config: Daraja passkey is empty', '', $donate['passkey']);
$check('Exported config: no callback tunnel URL', '', $donate['callback_base_url']);
$leaks = 0;
foreach ($sync->listAll() as $name) {
  if (preg_match('/trycloudflare|ngrok|[0-9a-f]{32}\b.*callback/i', json_encode($sync->read($name)))) {
    $leaks++;
  }
}
$check('Exported config: no tunnel address or callback token anywhere', 0, $leaks);

// --- Settings ----------------------------------------------------------------------------------
$check('Trusted host patterns are set (not a wildcard)', TRUE, !in_array('.*', (array) Settings::get('trusted_host_patterns'), TRUE) && !empty(Settings::get('trusted_host_patterns')));
$private = (string) Settings::get('file_private_path');
$check('Private file path is set', TRUE, $private !== '');
$check('Private file path is outside the web root', TRUE, $private !== '' && !str_starts_with((string) realpath($private), (string) realpath(DRUPAL_ROOT)));
$check('Private file path exists', TRUE, is_dir($private));
$check('Update access check is on (update_free_access)', FALSE, (bool) Settings::get('update_free_access'));
$check('Config sync directory is outside the web root', TRUE, !str_starts_with((string) realpath(DRUPAL_ROOT . '/' . Settings::get('config_sync_directory')), (string) realpath(DRUPAL_ROOT)));

// --- Roles and permissions: the Day 2 matrix, still true ----------------------------------------
$expected = [
  'anonymous' => ['access content', 'make donations', 'use text format restricted_html'],
  'authenticated' => ['access content', 'delete own files', 'make donations', 'use text format basic_html'],
  'field_officer' => [
    'access content', 'access content overview', 'access toolbar', 'create report content', 'edit own report content',
    'use report_review transition create_draft', 'use report_review transition submit_for_review', 'view latest version',
    'view own unpublished content', 'view the administration theme',
  ],
  'me_reviewer' => [
    'access content', 'access content overview', 'access toolbar', 'edit any report content',
    'use report_review transition archive', 'use report_review transition publish', 'use report_review transition republish',
    'use report_review transition return_to_draft', 'view all revisions', 'view any unpublished content', 'view latest version',
    'view the administration theme',
  ],
];
foreach ($expected as $rid => $perms) {
  $actual = Role::load($rid)->getPermissions();
  sort($perms);
  sort($actual);
  $check("Role $rid has exactly the designed permissions", $perms, $actual);
}
$check('Roles that exist', ['administrator', 'anonymous', 'authenticated', 'field_officer', 'me_reviewer', 'ngo_admin'], (function () {
  $ids = array_keys(Role::loadMultiple());
  sort($ids);
  return $ids;
})());
$admin = Role::load('ngo_admin');
foreach (['administer permissions', 'administer modules', 'administer site configuration', 'administer software updates', 'administer themes', 'administer views', 'administer athari donations', 'use text format full_html', 'bypass node access'] as $perm) {
  $check("Admin role does NOT have '$perm'", FALSE, $admin->hasPermission($perm));
}
foreach (['assign field_officer role', 'assign me_reviewer role', 'administer users', 'view athari donations', 'administer athari donate'] as $perm) {
  $check("Admin role has '$perm'", TRUE, $admin->hasPermission($perm));
}
$check("Admin cannot assign the Administrator or Admin roles", FALSE, $admin->hasPermission('assign administrator role') || $admin->hasPermission('assign ngo_admin role'));
foreach (['anonymous', 'authenticated', 'field_officer', 'me_reviewer'] as $rid) {
  $check("Role $rid cannot see the donations report or settings", FALSE, Role::load($rid)->hasPermission('view athari donations') || Role::load($rid)->hasPermission('administer athari donate'));
}
$full = array_filter(Role::loadMultiple(), fn($r) => $r->hasPermission('use text format full_html'));
$check('Only the Administrator may use the Full HTML text format', ['administrator'], array_keys($full));

print 'SUMMARY|' . ($fails === 0 ? "ALL $checks SECURITY CONFIG CHECKS PASSED" : "$fails of $checks CHECK(S) FAILED") . "\n";
