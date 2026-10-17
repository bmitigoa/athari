<?php

/**
 * @file
 * Creates (or resets the password of) the Athari staff accounts.
 *
 * Passwords come from environment variables so they never appear in a command
 * line, a log or Git: ATHARI_PW_FIELDOFFICER, ATHARI_PW_REVIEWER, ATHARI_PW_NGOADMIN.
 * The accounts are created with the roles from the Day 2 design. An account
 * whose password variable is empty is skipped.
 *
 * Run inside the app container:
 *   vendor/bin/drush php:script deploy/create-users.php
 */

use Drupal\user\Entity\User;

$accounts = [
  'fieldofficer' => ['ATHARI_PW_FIELDOFFICER', 'field_officer'],
  'reviewer' => ['ATHARI_PW_REVIEWER', 'me_reviewer'],
  'ngoadmin' => ['ATHARI_PW_NGOADMIN', 'ngo_admin'],
];

// The site administrator (user 1): a non-default name and a strong password.
$admin_password = (string) getenv('ATHARI_PW_ADMIN');
$admin_name = (string) getenv('ATHARI_ADMIN_USER');
if ($admin_password !== '' && $admin_name !== '') {
  $admin = User::load(1);
  $admin->setUsername($admin_name)->setPassword($admin_password);
  if (getenv('ATHARI_ADMIN_MAIL')) {
    $admin->setEmail((string) getenv('ATHARI_ADMIN_MAIL'));
  }
  $admin->activate()->save();
  print "Set the administrator account name and password.\n";
}

foreach ($accounts as $name => [$variable, $role]) {
  $password = (string) getenv($variable);
  if ($password === '') {
    print "Skipped $name ($variable is empty).\n";
    continue;
  }
  $user = user_load_by_name($name);
  if ($user) {
    $user->setPassword($password)->activate()->save();
    print "Updated the password of $name.\n";
    continue;
  }
  User::create([
    'name' => $name,
    'mail' => "$name@example.org",
    'pass' => $password,
    'status' => 1,
    'roles' => [$role],
  ])->save();
  print "Created $name with the role $role.\n";
}
