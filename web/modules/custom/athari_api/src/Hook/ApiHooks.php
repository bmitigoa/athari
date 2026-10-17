<?php

declare(strict_types=1);

namespace Drupal\athari_api\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Hooks that keep the public API to published content.
 */
class ApiHooks {

  public function __construct(protected RouteMatchInterface $routeMatch) {}

  /**
   * Implements hook_node_access().
   *
   * On any JSON:API request an unpublished node cannot be viewed, whoever is
   * asking. Staff still see drafts and reports in review in the site itself;
   * only the API is restricted. This is on top of Drupal's normal access
   * checks, so anonymous visitors were already limited to published content.
   * Revisions are covered too: a draft revision is an unpublished node.
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $op, AccountInterface $account): AccessResultInterface {
    $route = (string) $this->routeMatch->getRouteName();
    $result = AccessResult::neutral()->addCacheContexts(['route']);
    if ($op === 'view' && !$node->isPublished() && str_starts_with($route, 'jsonapi.')) {
      return AccessResult::forbidden('The public API only exposes published content.')
        ->addCacheContexts(['route'])
        ->addCacheableDependency($node);
    }
    return $result;
  }

}
