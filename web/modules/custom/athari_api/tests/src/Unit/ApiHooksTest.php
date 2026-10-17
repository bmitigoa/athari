<?php

declare(strict_types=1);

namespace Drupal\Tests\athari_api\Unit;

use Drupal\athari_api\Hook\ApiHooks;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests that the public API never serves unpublished nodes.
 */
#[Group('athari_api')]
class ApiHooksTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Access results merge cache metadata, which needs a contexts manager.
    $manager = $this->prophesize(CacheContextsManager::class);
    $manager->assertValidTokens(Argument::any())->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $manager->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Builds the hooks object for a given current route.
   */
  protected function hooks(?string $route): ApiHooks {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn($route);
    return new ApiHooks($route_match);
  }

  /**
   * Builds a node that is or is not published.
   */
  protected function node(bool $published): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('isPublished')->willReturn($published);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn(['node:1']);
    $node->method('getCacheMaxAge')->willReturn(-1);
    return $node;
  }

  /**
   * Cases: route, published, operation, whether access must be forbidden.
   */
  public static function cases(): array {
    return [
      'draft on a JSON:API collection' => ['jsonapi.node--report.collection', FALSE, 'view', TRUE],
      'draft on a JSON:API individual' => ['jsonapi.node--report.individual', FALSE, 'view', TRUE],
      'draft on a related route' => ['jsonapi.node--project.locations.related', FALSE, 'view', TRUE],
      'published on JSON:API' => ['jsonapi.node--report.collection', TRUE, 'view', FALSE],
      'draft on the normal site (staff keep access)' => ['entity.node.canonical', FALSE, 'view', FALSE],
      'draft on the edit form' => ['entity.node.edit_form', FALSE, 'view', FALSE],
      'update on JSON:API is not touched' => ['jsonapi.node--report.individual', FALSE, 'update', FALSE],
      'no route at all (drush, cron)' => [NULL, FALSE, 'view', FALSE],
    ];
  }

  /**
   * Tests each case.
   */
  #[DataProvider('cases')]
  public function testNodeAccess(?string $route, bool $published, string $op, bool $forbidden): void {
    $result = $this->hooks($route)->nodeAccess($this->node($published), $op, $this->createMock(AccountInterface::class));
    $this->assertSame($forbidden, $result->isForbidden());
    $this->assertFalse($result->isAllowed(), 'The hook never grants access, it only removes it.');
  }

}
