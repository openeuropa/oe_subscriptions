<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\extra_field\Plugin\ExtraFieldDisplayManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for any config change. Maybe is too much.
 */
class EntityTypeSubscriber implements EventSubscriberInterface {

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
    return [
      ConfigEvents::SAVE => 'onConfigChange',
      ConfigEvents::DELETE => 'onConfigChange',
    ];
  }

  /**
   * Flush cache on entity type creation.
   *
   * * @param \Drupal\Core\Entity\EntityTypeEvent $event
   *   The event object.
   */
  public function onConfigChange(ConfigCrudEvent $event): void {
    // Clear extra fields definitions cache.
    $this->extraFieldManager->clearCachedDefinitions();
  }

}
