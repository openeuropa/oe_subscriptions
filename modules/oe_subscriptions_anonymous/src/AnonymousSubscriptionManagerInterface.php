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
   * Given Flag module requires a user to do the flag,
   * a decoupled user is created on the fly.
   *
   * Access check it is done throu routing and AccessCheck service.
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
  public function createSubscription(string $mail, FlagInterface $flag, string $entity_id);

  /**
   * Validates an unconfirmed subscription with a given hash.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag used for subscribing.
   * @param string $entity_id
   *   The entity to subscribe to.
   * @param string $hash
   *   The hash used for validation.
   *
   * @return bool
   *   Operation result.
   */
  public function confirmSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash);

  /**
   * Cancels a subscription with a given hash.
   *
   * Deletes the database entry for unconfirmed, and the flagging for confirmed.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag used for subscribing.
   * @param string $entity_id
   *   The entity to subscribe to.
   * @param string $hash
   *   The hash used for validation.
   *
   * @return bool
   *   Operation result.
   */
  public function cancelSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash);

  /**
   * Checks wether a subscription exists.
   *
   * This function looks for unconfirmed subscriptions.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag used for subscribing.
   * @param string $entity_id
   *   The entity to subscribe to.
   *
   * @return bool
   *   If the subscription exists.
   */
  public function subscriptionExists(string $mail, FlagInterface $flag, string $entity_id);

}
