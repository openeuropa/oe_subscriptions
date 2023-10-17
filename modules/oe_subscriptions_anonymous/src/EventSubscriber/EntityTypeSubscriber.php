<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeEventSubscriberTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\extra_field\Plugin\ExtraFieldDisplayManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for any config change. Maybe is too much.
 */
class EntityTypeSubscriber implements EventSubscriberInterface {

  use EntityTypeEventSubscriberTrait;

  /**
   * Extra field manager.
   *
   * @var \Drupal\extra_field\Plugin\ExtraFieldDisplayManager
   */
  protected $extraFieldManager;

  /**
   * Constructs a EntityTypeSubscriber.
   *
   * @param \Drupal\extra_field\Plugin\ExtraFieldDisplayManager $extraFieldManager
   *   Extra field manager.
   */
  public function __construct(ExtraFieldDisplayManager $extraFieldManager) {
    $this->extraFieldManager = $extraFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = self::getEntityTypeEvents();
    $events[ConfigEvents::SAVE][] = 'onConfigChange';
    $events[ConfigEvents::DELETE][] = 'onConfigChange';
    return $events;
  }

  /**
   * Flush cache on entity type creation.
   *
   * * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event object.
   */
  public function onConfigChange(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    // Flag config that starts with 'subscribe_'.
    if (!empty($config->get('flag_type')) && str_starts_with($config->get('id'), 'subscribe_')) {
      // Clear extra fields definitions cache.
      $this->extraFieldManager->clearCachedDefinitions();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldableEntityTypeCreate(EntityTypeInterface $entity_type, array $field_storage_definitions) {
    // Clear extra fields definitions cache.
    $this->extraFieldManager->clearCachedDefinitions();
  }

}
