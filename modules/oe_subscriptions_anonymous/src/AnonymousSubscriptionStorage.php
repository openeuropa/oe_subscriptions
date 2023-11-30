<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;

/**
 * Class to manage anonymous subscriptions storage.
 */
class AnonymousSubscriptionStorage implements AnonymousSubscriptionStorageInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $mail, string $scope): string {

    $hash = Crypt::randomBytesBase64();

    if ($this->exists($mail, $scope)) {
      // In case we have an existing subscription, we update changed and hash.
      $this->connection->update('oe_subscriptions_anonymous_subscriptions')
        ->fields([
          'hash' => $hash,
          'changed' => time(),
        ])
        ->condition('mail', $mail)
        ->condition('scope', $scope)
        ->execute();

      return $hash;
    }

    // Create new entry.
    $this->connection->insert('oe_subscriptions_anonymous_subscriptions')
      ->fields([
        'mail' => $mail,
        'scope' => $scope,
        'hash' => $hash,
        'changed' => time(),
      ])->execute();

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $mail, string $scope): bool {

    $query = $this->connection->delete('oe_subscriptions_anonymous_subscriptions')
      ->condition('mail', $mail)
      ->condition('scope', $scope);

    // If any rows were deleted.
    return !empty($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  private function exists(string $mail, string $scope): bool {
    // The subscription exists and is active.
    $query = $this->connection->select('oe_subscriptions_anonymous_subscriptions', 's')
      ->fields('s', ['mail'])
      ->condition('s.mail', $mail)
      ->condition('s.scope', $scope);

    // If there is a result.
    return !empty($query->execute()->fetchAll());
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(string $mail, string $scope, string $hash): bool {
    // The subscription exists and is not expired.
    $query = $this->connection->select('oe_subscriptions_anonymous_subscriptions', 's')
      ->fields('s', ['mail'])
      ->condition('s.mail', $mail)
      ->condition('s.scope', $scope)
      ->condition('s.hash', $hash)
      ->condition('s.changed', time() - AnonymousSubscriptionStorageInterface::EXPIRED_MAX_TIME, '>=');

    // If there is a result.
    return (!empty($query->execute()->fetchAll()));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteExpired(): void {

    $this->connection->delete('oe_subscriptions_anonymous_subscriptions')
      ->condition('changed', time() - AnonymousSubscriptionStorageInterface::EXPIRED_MAX_TIME, '<')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public static function buildScope(string $type, array $entity_ids = []): string {
    // No other elements than the type.
    if (empty($entity_ids)) {
      return $type;
    }
    // Prepend the type and return.
    array_unshift($entity_ids, $type);

    return implode(':', $entity_ids);
  }

}
