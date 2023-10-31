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
    if (!$flag->status() || !str_starts_with($flag->id(), 'subscribe_')) {
      return AccessResult::forbidden()->addCacheableDependency($flag);
    }

    $entity = $this->flagService->getFlaggableById($flag, $entity_id);
    if (empty($entity)) {
      return AccessResult::forbidden()->addCacheableDependency($flag);
    }

    return $entity->access('view', NULL, TRUE)->addCacheableDependency($flag);
  }

}
