<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Checks access for a given subscription id.
 */
class SubscriptionAccessCheck implements AccessInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\flag\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
    EntityTypeManagerInterface $entityTypeManager,
    FlagServiceInterface $flagService) {
    $this->flagService = $flagService;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Anonymous subscription access check.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route matched.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $route_match): AccessResultInterface {
    $subscription_id = $route_match->getParameter('subscription_id');
    if (empty($subscription_id)) {
      return AccessResult::forbidden();
    }
    // Get values.
    [$flag_id, $entity_id] = explode(':', $subscription_id);
    // Try to load flag.
    $flag = $this->flagService->getFlagById($flag_id);
    if (empty($flag)) {
      return AccessResult::forbidden();
    }
    // Get data from flag to load entity.
    $entity_type = $flag->getFlaggableEntityTypeId();
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    if (empty($entity)) {
      return AccessResult::forbidden();
    }
    // We have met all the conditions.
    return AccessResult::allowed();
  }

}
