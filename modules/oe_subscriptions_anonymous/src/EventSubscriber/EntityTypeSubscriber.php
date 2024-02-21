<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\EventSubscriber;

use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for any config change.
 */
class EntityTypeSubscriber implements EventSubscriberInterface {

  use EntityTypeEventSubscriberTrait;

  /**
   * Constructs a EntityTypeSubscriber.
   *
   * @param \Drupal\extra_field\Plugin\ExtraFieldDisplayManager $extraFieldManager
   *   Extra field manager.
   */
  public function __construct(protected ExtraFieldDisplayManager $extraFieldManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = self::getEntityTypeEvents();
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldableEntityTypeCreate(EntityTypeInterface $entity_type, array $field_storage_definitions) {
    // Clear extra fields definitions cache.
    $this->extraFieldManager->clearCachedDefinitions();
  }

}
