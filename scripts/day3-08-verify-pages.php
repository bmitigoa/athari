<?php

/**
 * @file
 * Day 3: renders every public and restricted page as each test user.
 *
 * The script switches the current account and dispatches a real sub-request
 * through Drupal's HTTP kernel, so routing, access checks, Views, blocks and
 * the theme all run. Output lines: PAGE|role|path|expected|actual|PASS/FAIL.
 *
 * Run with: ddev drush php:script scripts/day3-08-verify-pages.php
 */

use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$switcher = \Drupal::service('account_switcher');
$kernel = \Drupal::service('http_kernel');

$accounts = [
  'anonymous' => new AnonymousUserSession(),
  'fieldofficer' => user_load_by_name('fieldofficer'),
  'reviewer' => user_load_by_name('reviewer'),
  'ngoadmin' => user_load_by_name('ngoadmin'),
];

// Path => expected status per role (anonymous, fieldofficer, reviewer, ngoadmin).
$pages = [
  '/' => [200, 200, 200, 200],
  '/home' => [200, 200, 200, 200],
  '/projects' => [200, 200, 200, 200],
  '/projects/table' => [200, 200, 200, 200],
  '/projects?sector=1' => [200, 200, 200, 200],
  '/impact' => [200, 200, 200, 200],
  '/map' => [200, 200, 200, 200],
  '/about' => [200, 200, 200, 200],
  '/projects/kibera-maternal-child-health-outreach' => [200, 200, 200, 200],
  '/review-queue' => [403, 403, 200, 200],
  '/node/add/report' => [403, 200, 403, 200],
  '/node/add/project' => [403, 403, 403, 200],
  '/admin/content' => [403, 200, 200, 200],
];

$fails = 0;
$extra = [];
foreach ($pages as $path => $expected) {
  $i = 0;
  foreach ($accounts as $role => $account) {
    $switcher->switchTo($account);
    $response = $kernel->handle(Request::create($path), HttpKernelInterface::SUB_REQUEST, TRUE);
    $status = $response->getStatusCode();
    $body = (string) $response->getContent();
    $switcher->switchBack();
    $ok = $status === $expected[$i];
    $fails += $ok ? 0 : 1;
    print "PAGE|$role|$path|{$expected[$i]}|$status|" . ($ok ? 'PASS' : 'FAIL') . "\n";

    // Extra content checks.
    if ($path === '/review-queue' && $status === 200) {
      $extra[] = "NOTE|$role|/review-queue shows " . max(0, substr_count($body, '<tr') - 1) . ' report row(s)';
    }
    if ($path === '/') {
      $has_review_link = str_contains($body, 'href="/review-queue"');
      $want = in_array($role, ['reviewer', 'ngoadmin'], TRUE);
      $ok2 = $has_review_link === $want;
      $fails += $ok2 ? 0 : 1;
      print "PAGE|$role|menu shows Review queue link|" . ($want ? 'yes' : 'no') . '|' . ($has_review_link ? 'yes' : 'no') . '|' . ($ok2 ? 'PASS' : 'FAIL') . "\n";
    }
    $i++;
  }
}
foreach ($extra as $line) {
  print "$line\n";
}
print 'SUMMARY|' . ($fails === 0 ? 'ALL PAGE CHECKS PASSED' : "$fails PAGE CHECK(S) FAILED") . "\n";
