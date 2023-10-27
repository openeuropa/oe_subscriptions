<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Checks access for a given subscription id.
 */
class SubscriptionAccessCheck implements AccessInterface {

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected FlagServiceInterface $flagService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    FlagServiceInterface $flagService) {
    $this->flagService = $flagService;
  }

  /**
   * Anonymous subscription access check.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route matched.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to subscribe to.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match, FlagInterface $flag = NULL): AccessResultInterface {
    $entity_id = $route_match->getParameter('entity_id');
    // No value.
    if (empty($flag) || empty($entity_id)) {
      return AccessResult::forbidden();
    }
    // Disabled flag or not starting with.
    if (!$flag->status() || !str_starts_with($flag->id(), 'subscribe_')) {
      return AccessResult::forbidden()->addCacheableDependency($flag);
    }
    // Get data from flag to load entity.
    $flaggable = $this->flagService->getFlaggableById($flag, $entity_id);
    if (empty($flaggable)) {
      return AccessResult::forbidden()->addCacheableDependency($flag);
    }
    $view_access = $flaggable->access('view', NULL, TRUE);
    // We rely in entity view access adding flag cacheability.
    return $view_access->addCacheableDependency($flag);
  }

}
