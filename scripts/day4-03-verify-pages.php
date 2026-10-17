<?php

/**
 * @file
 * Day 4: renders the donation pages as each test user and checks access.
 *
 * Output lines: PAGE|role|path|expected|actual|PASS/FAIL.
 * Run with: ddev drush php:script scripts/day4-03-verify-pages.php
 */

use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$switcher = \Drupal::service('account_switcher');
$kernel = \Drupal::service('http_kernel');
$manager = \Drupal::service('athari_donate.donation_manager');

$accounts = [
  'anonymous' => new AnonymousUserSession(),
  'fieldofficer' => user_load_by_name('fieldofficer'),
  'reviewer' => user_load_by_name('reviewer'),
  'ngoadmin' => user_load_by_name('ngoadmin'),
];

// A throw-away donation so the status page can be requested.
$storage = \Drupal::entityTypeManager()->getStorage('node');
$project = reset($storage->loadByProperties(['type' => 'project', 'title' => 'Kibera Maternal & Child Health Outreach']));
$donation = $manager->createPending($project, 5, '254708374149');
$uuid = $donation->uuid();
$token = $manager->getCallbackSecret();

// Path => [method, expected status per role: anonymous, officer, reviewer, admin].
// The POST behaviour of the callback is covered by the kernel test and by real
// HTTP requests (see the Day 4 report); here only the GET refusal is checked.
$pages = [
  '/donate' => ['GET', [200, 200, 200, 200]],
  '/donate/' . $project->id() => ['GET', [200, 200, 200, 200]],
  '/donate/status/' . $uuid => ['GET', [200, 200, 200, 200]],
  '/donate/status/' . $uuid . '/json' => ['GET', [200, 200, 200, 200]],
  '/donate/status/00000000-0000-0000-0000-000000000000' => ['GET', [404, 404, 404, 404]],
  '/admin/content/donations' => ['GET', [403, 403, 403, 200]],
  '/admin/config/athari/donate' => ['GET', [403, 403, 403, 200]],
  '/athari/mpesa/callback/' . $token => ['GET', [405, 405, 405, 405]],
  '/projects/' . 'kibera-maternal-child-health-outreach' => ['GET', [200, 200, 200, 200]],
  '/' => ['GET', [200, 200, 200, 200]],
];

$fails = 0;
$checks = 0;
foreach ($pages as $path => [$method, $expected]) {
  $i = 0;
  foreach ($accounts as $role => $account) {
    $switcher->switchTo($account);
    $request = Request::create($path, $method, [], [], [], [], $method === 'POST' ? '{}' : NULL);
    $response = $kernel->handle($request, HttpKernelInterface::SUB_REQUEST, TRUE);
    $status = $response->getStatusCode();
    $switcher->switchBack();
    $ok = $status === $expected[$i];
    $fails += $ok ? 0 : 1;
    $checks++;
    $label = str_replace([$uuid, $token], ['<uuid>', '<token>'], $path);
    print "PAGE|$role|$method $label|{$expected[$i]}|$status|" . ($ok ? 'PASS' : 'FAIL') . "\n";
    $i++;
  }
}

// The donation form must show as available only when Daraja is configured.
$switcher->switchTo($accounts['anonymous']);
$response = $kernel->handle(Request::create('/donate'), HttpKernelInterface::SUB_REQUEST, TRUE);
$body = (string) $response->getContent();
$switcher->switchBack();
foreach ([
  'form has phone field' => str_contains($body, 'name="phone"'),
  'form has amount field' => str_contains($body, 'name="amount"'),
  'form has project select' => str_contains($body, 'name="project"'),
] as $what => $found) {
  $checks++;
  $fails += $found ? 0 : 1;
  print "PAGE|anonymous|$what|yes|" . ($found ? 'yes' : 'no') . '|' . ($found ? 'PASS' : 'FAIL') . "\n";
}

$donation->delete();
print "SUMMARY|" . ($fails === 0 ? "ALL $checks DONATION PAGE CHECKS PASSED" : "$fails of $checks CHECK(S) FAILED") . "\n";
