<?php

/**
 * @file
 * Day 5: security hardening that lives in configuration.
 *
 * - Errors are hidden from visitors (the DDEV settings turn them back on for
 *   local work only).
 * - Cron: a 3-hour fallback; production also runs a real system cron.
 * - Anonymous pages may be cached for 5 minutes.
 * - The unused "Content editor" role from the Standard profile is removed.
 * - Admin can no longer edit permissions. With the Role Delegation module it
 *   may only give the Field Officer and M&E Reviewer roles to people, so it
 *   cannot make itself or anyone else more powerful.
 * - A "dev" config split keeps Devel out of the exported production config.
 *
 * Run with: ddev drush php:script scripts/day5-03-hardening.php
 * Safe to re-run.
 */

use Drupal\config_split\Entity\ConfigSplitEntity;
use Drupal\user\Entity\Role;

$factory = \Drupal::configFactory();

$factory->getEditable('system.logging')->set('error_level', 'hide')->save();
$factory->getEditable('automated_cron.settings')->set('interval', 10800)->save();
$factory->getEditable('system.performance')->set('cache.page.max_age', 300)->save();

// Remove the unused default role, but only if nobody holds it.
if ($role = Role::load('content_editor')) {
  $holders = \Drupal::entityQuery('user')->accessCheck(FALSE)->condition('roles', 'content_editor')->count()->execute();
  if ($holders === 0) {
    $role->delete();
    print "Removed the unused role content_editor.\n";
  }
  else {
    print "Kept content_editor: $holders user(s) hold it.\n";
  }
}

// Admin: delegate only the two staff roles instead of editing permissions.
if (\Drupal::moduleHandler()->moduleExists('role_delegation')) {
  $admin = Role::load('ngo_admin');
  $admin->revokePermission('administer permissions');
  $admin->grantPermission('assign field_officer role');
  $admin->grantPermission('assign me_reviewer role');
  $admin->save();
  print "ngo_admin: administer permissions removed; may assign Field Officer and M&E Reviewer only.\n";
}

// The dev split: Devel and Devel Generate live outside the main config.
if (!ConfigSplitEntity::load('dev')) {
  ConfigSplitEntity::create([
    'id' => 'dev',
    'label' => 'Development',
    'description' => 'Developer tools (Devel, Devel Generate). Active only on DDEV; never on a live site.',
    'folder' => '../config/dev',
    'module' => ['devel' => 0, 'devel_generate' => 0],
    'theme' => [],
    'blacklist' => [],
    'graylist' => [],
    'graylist_dependents' => TRUE,
    'graylist_skip_equal' => TRUE,
    'weight' => 0,
    // Off in the exported config; DDEV turns it on in settings.php.
    'status' => FALSE,
  ])->save();
  print "Created the 'dev' config split.\n";
}
print "Hardening config applied.\n";
