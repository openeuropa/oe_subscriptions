<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions\FlagHelper;

/**
 * Checks access for a given subscription id.
 */
class SubscriptionAccessCheck implements AccessInterface {

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   */
  public function __construct(protected FlagServiceInterface $flagService) {}

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
    if (!$flag->status() || !FlagHelper::isSubscribeFlag($flag)) {
      return AccessResult::forbidden()->addCacheableDependency($flag);
    }

    $entity = $this->flagService->getFlaggableById($flag, $entity_id);
    if (empty($entity)) {
      return AccessResult::forbidden('No flaggable entity found.')->addCacheableDependency($flag);
    }

    // The flag module doesn't expose a method to check if an entity can be
    // flagged with a specific flag. This check is done only during the
    // \Drupal\flag\FlagServiceInterface::flag() (or unflag) execution.
    // The flag route is protected anyway because it requires a CSRF token, that
    // is generated when a link to the route is outputted (e.g. by the action
    // link plugins) and said links are outputted only when the entity is
    // flaggable. In our scenario we don't need a CSRF token as there's no
    // direct action when visiting the link, so we need to check the bundles
    // by ourselves.
    // We cannot use the flag access service either, as we are not configuring
    // anonymous users to flag content.
    $bundles = $flag->getBundles();
    if (!empty($bundles) && !in_array($entity->bundle(), $bundles)) {
      return AccessResult::forbidden('The flag does not apply to the bundle of the entity')->addCacheableDependency($flag);
    }

    return $entity->access('view', NULL, TRUE)->addCacheableDependency($flag);
  }

}
