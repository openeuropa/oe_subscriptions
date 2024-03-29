<?php

namespace Drupal\oe_subscriptions_digest\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_digest\DigestFlagHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * React to flagging from anonymous user.
 */
class AnonymousFlaggingEvent implements EventSubscriberInterface {

  /**
   * Constructs the anonymous flagging event subscriber.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   */
  public function __construct(protected AccountProxyInterface $current_user, protected ModuleHandlerInterface $module_handler, protected ConfigFactoryInterface $config_factory, protected FlagServiceInterface $flag_service) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [FlagEvents::ENTITY_FLAGGED => 'onAnonymousFlagging'];
  }

  /**
   * Sets the flagging message_digest from the flagging user message_digest.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function onAnonymousFlagging(FlaggingEvent $event) {
    if ($this->current_user->isAuthenticated() || !$this->module_handler->moduleExists('oe_subscriptions_anonymous')) {
      return;
    }
    $flagging = $event->getFlagging();
    $flagging_owner = $flagging->getOwner();
    if (
      DigestFlagHelper::isDigestFlagging($flagging) &&
      !$flagging->get('message_digest')->isEmpty() &&
      $flagging_owner->get('message_subscribe_email')->value &&
      $flagging_owner->hasField('message_digest')
    ) {
      $flagging->set('message_digest', $flagging_owner->get('message_digest')->value)->save();
    }
  }

}
