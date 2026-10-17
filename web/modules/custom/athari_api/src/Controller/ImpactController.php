<?php

declare(strict_types=1);

namespace Drupal\athari_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read-only impact summary: totals that the dashboard shows, as JSON.
 *
 * Only aggregates are returned. No donor, phone number or individual donation
 * appears here. Every report figure counts PUBLISHED reports only.
 */
class ImpactController extends ControllerBase {

  public function __construct(protected Connection $database) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * GET /api/impact.
   */
  public function summary(): CacheableJsonResponse {
    $db = $this->database;

    $totals = $db->query(
      "SELECT COUNT(*) AS reports, COALESCE(SUM(b.field_beneficiaries_value), 0) AS beneficiaries,
              COALESCE(SUM(f.field_funds_spent_value), 0) AS funds
       FROM {node_field_data} n
       JOIN {node__field_beneficiaries} b ON b.entity_id = n.nid
       JOIN {node__field_funds_spent} f ON f.entity_id = n.nid
       WHERE n.type = 'report' AND n.status = 1"
    )->fetchObject();

    $active = (int) $db->query(
      "SELECT COUNT(*) FROM {node_field_data} n
       JOIN {node__field_status} s ON s.entity_id = n.nid
       WHERE n.type = 'project' AND n.status = 1 AND s.field_status_value = 'active'"
    )->fetchField();

    $by_sector = [];
    foreach ($db->query(
      "SELECT t.name AS name, COUNT(*) AS reports, SUM(b.field_beneficiaries_value) AS beneficiaries,
              SUM(f.field_funds_spent_value) AS funds
       FROM {node_field_data} n
       JOIN {node__field_beneficiaries} b ON b.entity_id = n.nid
       JOIN {node__field_funds_spent} f ON f.entity_id = n.nid
       JOIN {node__field_project} fp ON fp.entity_id = n.nid
       JOIN {node__field_sector} fs ON fs.entity_id = fp.field_project_target_id
       JOIN {taxonomy_term_field_data} t ON t.tid = fs.field_sector_target_id
       WHERE n.type = 'report' AND n.status = 1
       GROUP BY t.name ORDER BY t.name"
    ) as $row) {
      $by_sector[] = [
        'sector' => $row->name,
        'published_reports' => (int) $row->reports,
        'beneficiaries' => (int) $row->beneficiaries,
        'funds_spent_kes' => (int) round((float) $row->funds),
      ];
    }

    // Counties roll up to their former province (the parent term).
    $by_province = [];
    foreach ($db->query(
      "SELECT p.name AS name, COUNT(*) AS reports, SUM(b.field_beneficiaries_value) AS beneficiaries,
              SUM(f.field_funds_spent_value) AS funds
       FROM {node_field_data} n
       JOIN {node__field_beneficiaries} b ON b.entity_id = n.nid
       JOIN {node__field_funds_spent} f ON f.entity_id = n.nid
       JOIN {node__field_project} fp ON fp.entity_id = n.nid
       JOIN {node__field_region} fr ON fr.entity_id = fp.field_project_target_id
       JOIN {taxonomy_term__parent} tp ON tp.entity_id = fr.field_region_target_id
       JOIN {taxonomy_term_field_data} p ON p.tid = tp.parent_target_id
       WHERE n.type = 'report' AND n.status = 1
       GROUP BY p.name ORDER BY p.name"
    ) as $row) {
      $by_province[] = [
        'province' => $row->name,
        'published_reports' => (int) $row->reports,
        'beneficiaries' => (int) $row->beneficiaries,
        'funds_spent_kes' => (int) round((float) $row->funds),
      ];
    }

    $donations = $db->query(
      "SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total FROM {athari_donation} WHERE status = 'success'"
    )->fetchObject();

    $body = [
      'data' => [
        'type' => 'impact',
        'id' => 'summary',
        'attributes' => [
          'beneficiaries_reached' => (int) $totals->beneficiaries,
          'funds_spent_kes' => (int) round((float) $totals->funds),
          'published_reports' => (int) $totals->reports,
          'active_projects' => $active,
          'donations_received_kes' => (int) round((float) $donations->total),
          'successful_donations' => (int) $donations->n,
          'by_sector' => $by_sector,
          'by_province' => $by_province,
        ],
      ],
      'meta' => [
        'currency' => 'KES',
        'note' => 'Report figures count published reports only. Donation figures are totals of successful donations; no donor details are exposed.',
      ],
    ];

    $response = new CacheableJsonResponse($body);
    $response->headers->set('Content-Type', 'application/vnd.api+json');
    $response->addCacheableDependency((new CacheableMetadata())
      ->setCacheTags(['node_list', 'taxonomy_term_list', 'athari_donation_list'])
      ->setCacheMaxAge(3600));
    return $response;
  }

}
