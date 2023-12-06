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
   * @param string $mail
   *   Subscribing e-mail.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag used for subscribing.
   * @param string $entity_id
   *   The entity to subscribe to.
   *
   * @return bool
   *   The hash to do validation with.
   *
   * @throws \Drupal\oe_subscriptions_anonymous\Exception\RegisteredUserEmailException
   *   Thrown when the e-mail belongs to a coupled user.
   */
  public function subscribe(string $mail, FlagInterface $flag, string $entity_id): bool;

}
