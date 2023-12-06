<?php

namespace Drupal\oe_subscriptions_anonymous;

/**
 * Interface for a token manager service.
 */
interface TokenManagerInterface {

  /**
   * Defines the maximum time for a token to be valid, one day.
   */
  const EXPIRED_MAX_TIME = 86400;

  /**
   * Defines the subscribe type for scope building.
   */
  const TYPE_SUBSCRIBE = 'subscribe';

  /**
   * Creates a new token for the given e-mail and scope.
   *
   * If an entry already exists, it generates a new token and refreshed the
   * duration.
   *
   * @param string $mail
   *   The e-mail.
   * @param string $scope
   *   The token scope.
   *
   * @return string
   *   The token.
   */
  public function get(string $mail, string $scope): string;

  /**
   * Checks a token is valid for the given e-mail and scope.
   *
   * @param string $mail
   *   The e-mail.
   * @param string $scope
   *   The token scope.
   * @param string $hash
   *   The token to validate.
   *
   * @return bool
   *   Whether the token is valid or not.
   */
  public function isValid(string $mail, string $scope, string $hash): bool;

  /**
   * Deletes a token.
   *
   * @param string $mail
   *   The e-mail.
   * @param string $scope
   *   The token scope.
   *
   * @return bool
   *   Operation result.
   */
  public function delete(string $mail, string $scope): bool;

  /**
   * Delete all expired subscriptions.
   */
  public function deleteExpired(): void;

  /**
   * Builds a token scope identifier.
   *
   * @param string $type
   *   The type of the scope.
   * @param array $parts
   *   Additional scope parts.
   *
   * @return string
   *   The scope identifier.
   */
  public static function buildScope(string $type, array $parts = []): string;

}
