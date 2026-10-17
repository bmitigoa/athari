<?php

declare(strict_types=1);

namespace Drupal\Tests\athari\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Checks the Kenya sample data file for shape, realism and leftovers.
 *
 * Run with:
 * vendor/bin/phpunit -c web/core tests/KenyaSeedDataTest.php
 */
#[Group('athari')]
class KenyaSeedDataTest extends UnitTestCase {

  /**
   * The seed data.
   */
  protected array $data;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->data = require dirname(__DIR__) . '/scripts/seed/kenya-data.php';
  }

  /**
   * All county names in the data.
   */
  protected function counties(): array {
    return array_merge(...array_values($this->data['provinces']));
  }

  /**
   * Tests the 8 former provinces and 47 counties.
   */
  public function testProvincesAndCounties(): void {
    $this->assertSame(
      ['Nairobi', 'Central', 'Coast', 'Eastern', 'North Eastern', 'Nyanza', 'Rift Valley', 'Western'],
      array_keys($this->data['provinces'])
    );
    $this->assertCount(47, $this->counties(), 'Kenya has 47 counties.');
    $this->assertSame($this->counties(), array_values(array_unique($this->counties())), 'County names are unique.');
    foreach (['Nairobi', 'Turkana', 'Kilifi', 'Kisumu', 'Garissa', 'Marsabit', 'Kakamega', 'Makueni', 'Mombasa', "Murang'a"] as $county) {
      $this->assertContains($county, $this->counties());
    }
  }

  /**
   * Tests that locations are real-looking Kenyan places.
   */
  public function testLocations(): void {
    $this->assertCount(15, $this->data['locations']);
    $counties_used = [];
    foreach ($this->data['locations'] as $name => [$county, $lat, $lon]) {
      $this->assertContains($county, $this->counties(), "$name is in a real county.");
      // Kenya spans roughly 4.7 S to 5.1 N and 33.9 E to 41.9 E.
      $this->assertGreaterThanOrEqual(-4.7, $lat, $name);
      $this->assertLessThanOrEqual(5.1, $lat, $name);
      $this->assertGreaterThanOrEqual(33.9, $lon, $name);
      $this->assertLessThanOrEqual(41.95, $lon, $name);
      $counties_used[$county] = TRUE;
    }
    $this->assertGreaterThanOrEqual(12, count($counties_used), 'Locations are spread across counties.');
  }

  /**
   * Tests projects: counts, references, dates and KES budgets.
   */
  public function testProjects(): void {
    $this->assertCount(10, $this->data['projects']);
    $this->assertCount(5, $this->data['partners']);
    $statuses = [];
    foreach ($this->data['projects'] as $title => $p) {
      $this->assertContains($p['sector'], $this->data['sectors'], $title);
      $this->assertContains($p['county'], $this->counties(), $title);
      $this->assertContains($p['status'], ['planned', 'active', 'completed'], $title);
      $this->assertIsInt($p['budget']);
      $this->assertGreaterThanOrEqual(1000000, $p['budget'], "$title: budgets are in KES, so millions.");
      $this->assertLessThanOrEqual(100000000, $p['budget'], $title);
      $this->assertLessThan(strtotime($p['end']), strtotime($p['start']), "$title starts before it ends.");
      foreach ($p['locations'] as $location) {
        $this->assertArrayHasKey($location, $this->data['locations'], $title);
      }
      foreach ($p['partners'] as $partner) {
        $this->assertArrayHasKey($partner, $this->data['partners'], $title);
      }
      $statuses[$p['status']] = ($statuses[$p['status']] ?? 0) + 1;
    }
    $this->assertSame(['active' => 6, 'completed' => 3, 'planned' => 1], $statuses);
  }

  /**
   * Tests the 30 reports: states, references and amounts.
   */
  public function testReports(): void {
    $states = [];
    $total = 0;
    foreach ($this->data['reports'] as $project => $rows) {
      $this->assertArrayHasKey($project, $this->data['projects']);
      $this->assertNotSame('planned', $this->data['projects'][$project]['status'], 'A planned project has no reports.');
      $spent = 0;
      foreach ($rows as [$date, $beneficiaries, $funds, $state, $author, $text]) {
        $total++;
        $this->assertNotFalse(strtotime($date), $date);
        $this->assertGreaterThan(0, $beneficiaries);
        $this->assertSame(0, $funds % 500, 'Funds are rounded to 500 shillings.');
        $this->assertContains($author, ['o', 'a']);
        $this->assertNotSame('', trim($text));
        $states[$state] = ($states[$state] ?? 0) + 1;
        $spent += $funds;
      }
      $this->assertLessThanOrEqual($this->data['projects'][$project]['budget'], $spent, "$project spends within its budget.");
    }
    $this->assertSame(30, $total);
    ksort($states);
    $this->assertSame(['archived' => 3, 'draft' => 6, 'in_review' => 5, 'published' => 16], $states);
  }

  /**
   * Tests the sample donations.
   */
  public function testDonations(): void {
    $count = 0;
    foreach ($this->data['donations'] as $project => $rows) {
      $this->assertArrayHasKey($project, $this->data['projects']);
      foreach ($rows as [$amount, $status, $days]) {
        $count++;
        $this->assertGreaterThan(0, $amount);
        $this->assertContains($status, ['success', 'failed', 'cancelled']);
        $this->assertGreaterThan(0, $days);
      }
    }
    $this->assertSame(19, $count);
  }

  /**
   * Tests that no Ugandan place name, shilling code or USD amount is left.
   */
  public function testNoUgandaLeftovers(): void {
    $forbidden = '/\b(Uganda|UGX|USD|Kampala|Wakiso|Mukono|Masaka|Jinja|Mbale|Tororo|Soroti|Gulu|Kitgum|Lira|Arua|Mbarara|Kabarole|Kasese|Hoima|Kabale|Adjumani|MTN|MoMo)\b/i';
    $found = [];
    array_walk_recursive($this->data, function ($value) use ($forbidden, &$found) {
      if (is_string($value) && preg_match($forbidden, $value)) {
        $found[] = $value;
      }
    });
    $this->assertSame([], $found);
    array_walk_recursive($this->data['provinces'], function ($value) {
      $this->assertDoesNotMatchRegularExpression('/Uganda/i', $value);
    });
  }

}
