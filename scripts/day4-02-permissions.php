<?php

/**
 * @file
 * Day 4: grants the donation permissions to roles.
 *
 * - Everyone (anonymous and authenticated) may open the donation form.
 * - Admin may see the donations report and change the Donate settings.
 *
 * Run with: ddev drush php:script scripts/day4-02-permissions.php
 */

use Drupal\user\Entity\Role;

$grants = [
  'anonymous' => ['make donations'],
  'authenticated' => ['make donations'],
  'ngo_admin' => ['make donations', 'view athari donations', 'administer athari donate'],
];
foreach ($grants as $rid => $permissions) {
  $role = Role::load($rid);
  foreach ($permissions as $permission) {
    $role->grantPermission($permission);
  }
  $role->save();
}
print "Donation permissions granted.\n";
