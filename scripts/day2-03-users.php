<?php

/**
 * @file
 * Day 2: creates one local test user per role.
 *
 * LOCAL DEVELOPMENT ONLY. These passwords are deliberately simple and must
 * never be used on a deployed site.
 *
 * Run with: ddev drush php:script scripts/day2-03-users.php
 */

use Drupal\user\Entity\User;

$users = [
  'fieldofficer' => ['fieldofficer123', 'field_officer'],
  'reviewer' => ['reviewer123', 'me_reviewer'],
  'ngoadmin' => ['ngoadmin123', 'ngo_admin'],
];

foreach ($users as $name => [$pass, $role]) {
  $existing = user_load_by_name($name);
  if ($existing) {
    print "User $name already exists.\n";
    continue;
  }
  User::create([
    'name' => $name,
    'mail' => "$name@athari.test",
    'pass' => $pass,
    'status' => 1,
    'roles' => [$role],
  ])->save();
  print "Created user $name ($role).\n";
}
