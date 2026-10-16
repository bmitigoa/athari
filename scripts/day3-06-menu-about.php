<?php

/**
 * @file
 * Day 3: main menu links and the About page.
 *
 * Menu links and nodes are content, not configuration, so they are created by
 * this script rather than exported to config/sync. Safe to re-run.
 *
 * Run with: ddev drush php:script scripts/day3-06-menu-about.php
 */

require_once __DIR__ . '/lib.php';

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

// Basic page content type (this install does not ship one).
if (!NodeType::load('page')) {
  // Remove any node created earlier against the missing bundle.
  foreach (\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'page']) as $stray) {
    $stray->delete();
  }
  NodeType::create(['type' => 'page', 'name' => 'Basic page', 'description' => 'Static pages such as About.', 'new_revision' => TRUE, 'display_submitted' => FALSE])->save();
  athari_lib_add_field('node', 'page', 'body', 'text_with_summary', 'Body', ['widget' => 'text_textarea_with_summary', 'formatter' => 'text_default', 'weight' => 1, 'label_display' => 'hidden', 'settings' => ['display_summary' => FALSE]]);
}

// About page at /about.
$existing = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'page', 'title' => 'About Athari']);
if (!$existing) {
  Node::create([
    'type' => 'page',
    'title' => 'About Athari',
    'uid' => 1,
    'status' => 1,
    'path' => ['alias' => '/about'],
    'body' => [
      'format' => 'basic_html',
      'value' => '<p><strong>Athari</strong> is the Swahili word for <em>impact</em>. This portal helps a non-governmental organisation show, in one place, what its projects set out to do, where they work and what they have achieved.</p>'
        . '<h2>What you can do here</h2>'
        . '<ul><li><a href="/projects">Browse projects</a> and filter them by sector, region and status.</li>'
        . '<li>See the <a href="/impact">impact dashboard</a> for the people reached and funds spent, based only on field reports that have been reviewed and published.</li>'
        . '<li>Explore the <a href="/map">project map</a> to see where the work happens.</li></ul>'
        . '<h2>How reports are checked</h2>'
        . '<p>Field officers write reports from the field. Every report is reviewed by the monitoring and evaluation team before it is published, so the totals you see are checked figures.</p>'
        . '<h2>About the data</h2>'
        . '<p>This is a demonstration site. The projects, partners and figures shown are sample data created for a portfolio project and do not describe real organisations.</p>',
    ],
  ])->save();
  print "Created About page.\n";
}

// Main menu. Drupal's Standard profile already provides "Home".
$links = [
  ['Projects', 'internal:/projects', 1],
  ['Impact', 'internal:/impact', 2],
  ['Map', 'internal:/map', 3],
  ['About', 'internal:/about', 4],
  ['Review queue', 'internal:/review-queue', 5],
];
foreach ($links as [$title, $uri, $weight]) {
  $found = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main', 'title' => $title]);
  if ($found) {
    continue;
  }
  MenuLinkContent::create([
    'title' => $title,
    'link' => ['uri' => $uri],
    'menu_name' => 'main',
    'weight' => $weight,
    'expanded' => FALSE,
  ])->save();
  print "Created menu link: $title\n";
}
