<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
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
  public function __construct(FlagServiceInterface $flagService) {
    $this->flagService = $flagService;
  }

  /**
   * Anonymous subscription access check.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to subscribe to.
   * @param string $entity_id
   *   The flaggable entity ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(FlagInterface $flag, string $entity_id): AccessResultInterface {
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
