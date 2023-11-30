<?php

namespace Drupal\oe_subscriptions_anonymous;

/**
 * Interface to manage anonymous subscriptions storage.
 */
interface AnonymousSubscriptionStorageInterface {

  /**
   * Defines the maximum time for an subscription to be active, one day.
   */
  const EXPIRED_MAX_TIME = 86400;

  /**
   * Defines the subscribe type for scope building.
   */
  const TYPE_SUBSCRIBE = 'subscribe';

  /**
   * Creates an new subscription retrieving the validation hash.
   *
   * If the entry exists updates changed time, and retrieves new hash.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param string $scope
   *   The scope of the subscription.
   *
   * @return string
   *   The hash to do validation with.
   */
  public function get(string $mail, string $scope);

  /**
   * Checks if a subscription exists and is not expired.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param string $scope
   *   The scope of the subscription.
   * @param string $hash
   *   Hash to check agaisnt.
   *
   * @return string
   *   The time the subscription was changed in Unix format.
   */
  public function isValid(string $mail, string $scope, string $hash);

  /**
   * Deletes a subscription.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param string $scope
   *   The scope of the subscription.
   *
   * @return bool
   *   Operation result.
   */
  public function delete(string $mail, string $scope);

  /**
   * Delete all expired subscriptions.
   *
   * @return void
   *   No return.
   */
  public function deleteExpired();

  /**
   * Builds string with subscription scope information to be stored.
   *
   * @param string $type
   *   The type of the scope.
   * @param array $entity_ids
   *   Entities related to the scope.
   *
   * @return string
   *   The scope of the subscription.
   */
  public static function buildScope(string $type, array $entity_ids = []);

}
