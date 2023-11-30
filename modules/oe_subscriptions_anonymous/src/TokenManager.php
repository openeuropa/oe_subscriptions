<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;

/**
 * Manages tokens used to confirm e-mails from anonymous users.
 */
class TokenManager implements TokenManagerInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(protected Connection $connection, protected TimeInterface $time) {}

  /**
   * {@inheritdoc}
   */
  public function get(string $mail, string $scope): string {
    $hash = Crypt::randomBytesBase64();

    if ($this->exists($mail, $scope)) {
      // In case we have an existing subscription, we update changed and hash.
      $this->connection->update('oe_subscriptions_anonymous_tokens')
        ->fields([
          'hash' => $hash,
          'changed' => $this->time->getRequestTime(),
        ])
        ->condition('mail', $mail)
        ->condition('scope', $scope)
        ->execute();

      return $hash;
    }

    // Create new entry.
    $this->connection->insert('oe_subscriptions_anonymous_tokens')
      ->fields([
        'mail' => $mail,
        'scope' => $scope,
        'hash' => $hash,
        'changed' => $this->time->getRequestTime(),
      ])->execute();

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $mail, string $scope): bool {
    $query = $this->connection->delete('oe_subscriptions_anonymous_tokens')
      ->condition('mail', $mail)
      ->condition('scope', $scope);

    // If any rows were deleted.
    return !empty($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  private function exists(string $mail, string $scope): bool {
    $query = $this->connection->select('oe_subscriptions_anonymous_tokens', 's')
      ->fields('s', ['mail'])
      ->condition('s.mail', $mail)
      ->condition('s.scope', $scope)
      ->countQuery();

    return !empty((int) $query->countQuery()->execute()->fetchField());
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(string $mail, string $scope, string $hash): bool {
    // The subscription exists and is not expired.
    $query = $this->connection->select('oe_subscriptions_anonymous_tokens', 's')
      ->fields('s', ['mail'])
      ->condition('s.mail', $mail)
      ->condition('s.scope', $scope)
      ->condition('s.hash', $hash)
      ->condition('s.changed', $this->time->getRequestTime() - TokenManagerInterface::EXPIRED_MAX_TIME, '>=');

    return !empty((int) $query->countQuery()->execute()->fetchField());
  }

  /**
   * {@inheritdoc}
   */
  public function deleteExpired(): void {

    $this->connection->delete('oe_subscriptions_anonymous_tokens')
      ->condition('changed', $this->time->getRequestTime() - TokenManagerInterface::EXPIRED_MAX_TIME, '<')
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
