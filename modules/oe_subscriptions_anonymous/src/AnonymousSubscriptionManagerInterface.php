<?php

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\flag\FlagInterface;

/**
 * Interface to manage anonymous subscriptions.
 */
interface AnonymousSubscriptionManagerInterface {

  /**
   * Creates an unconfirmed subscription.
   *
   * The function is aimed to be used with anonymous users.
   *
   * Access check it is done through routing, not at this level.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag used for subscribing.
   * @param string $entity_id
   *   The entity to subscribe to.
   *
   * @return string
   *   The hash to do validation with.
   */
  public function subscribe(string $mail, FlagInterface $flag, string $entity_id);

}
