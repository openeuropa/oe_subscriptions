<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\Entity\User;

/**
 * Class to manage anonymous subscriptions.
 */
class AnonymousSubscriptionManager implements AnonymousSubscriptionManagerInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Constructor.
   *
   * @param Drupal\Core\Database\Connection $connection
   *   The current user.
   * @param Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   */
  public function __construct(
    Connection $connection,
    FlagServiceInterface $flagService,
    ) {
    $this->connection = $connection;
    $this->flagService = $flagService;
  }

  /**
   * {@inheritdoc}
   */
  public function createSubscription(string $mail, FlagInterface $flag, string $entity_id): string {

    $hash = Crypt::randomBytesBase64();

    // In case we have an existing unconfirmed, we update hash.
    if ($this->subscriptionExists($mail, $flag, $entity_id)) {
      $this->connection->update('oe_subscriptions_anonymous_subscriptions')
        ->fields([
          'hash' => $hash,
          'changed' => time(),
        ])
        ->condition('mail', $mail)
        ->condition('flag_id', $flag->id())
        ->condition('entity_id', $entity_id)
        ->execute();

      return $hash;
    }

    // Create new entry.
    $this->connection->insert('oe_subscriptions_anonymous_subscriptions')
      ->fields([
        'mail' => $mail,
        'flag_id' => $flag->id(),
        'entity_id' => $entity_id,
        'hash' => $hash,
        'changed' => time(),
      ])->execute();

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash): bool {
    // Check parameters.
    if (!$this->checkSubscription($mail, $flag, $entity_id, $hash)) {
      return FALSE;
    }

    // Load entity.
    $entity = $this->flagService->getFlaggableById($flag, $entity_id);

    if (empty($entity)) {
      return FALSE;
    }

    // Try to load user.
    $account = user_load_by_mail($mail);

    // Create decoupled user.
    if ($account === FALSE) {
      $user = User::create(['mail' => $mail])->save();
      $account = User::load($user);
    }

    if (empty($account)) {
      return FALSE;
    }

    // Already flagged.
    if ($flag->isFlagged($entity, $account)) {
      return TRUE;
    }

    // Do flag.
    $this->flagService->flag($flag, $entity, $account);

    // Update changed.
    $this->connection->update('oe_subscriptions_anonymous_subscriptions')
      ->fields([
        'changed' => time(),
      ])
      ->condition('mail', $mail)
      ->condition('flag_id', $flag->id())
      ->condition('entity_id', $entity_id)
      ->execute();

    // Return result.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash): bool {
    // Subscription doesn't exist.
    if (!$this->checkSubscription($mail, $flag, $entity_id, $hash)) {
      return FALSE;
    }

    // Load elemets to unflag.
    $account = user_load_by_mail($mail);
    $entity = $this->flagService->getFlaggableById($flag, $entity_id);

    // In case where the flag was done.
    if (!empty($entity) && !empty($account) && $flag->isFlagged($entity, $account)) {
      $this->flagService->unflag($flag, $entity, $account);
    }

    // After performing operations, we clean the entry.
    $query = $this->connection->delete('oe_subscriptions_anonymous_subscriptions')
      ->condition('mail', $mail)
      ->condition('flag_id', $flag->id())
      ->condition('entity_id', $entity_id)
      ->condition('hash', $hash)
      ->execute();

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscriptionChanged(string $mail, FlagInterface $flag, string $entity_id): string {
    // Subscription doesn't exist.
    if (!$this->checkSubscription($mail, $flag, $entity_id)) {
      return '';
    }

    // Query to check values, all parameters need to match.
    $query = $this->connection->select('oe_subscriptions_anonymous_subscriptions', 's')
      ->fields('s', ['changed'])
      ->condition('s.mail', $mail)
      ->condition('s.flag_id', $flag->id())
      ->condition('s.entity_id', $entity_id);

    $results = $query->execute()->fetchAll();

    if (empty($results)) {
      return '';
    }
    // If there is a result.
    return (reset($results)->changed);
  }

  /**
   * {@inheritdoc}
   */
  public function subscriptionExists(string $mail, FlagInterface $flag, string $entity_id): bool {
    // The subscription exists, no need of hash.
    return $this->checkSubscription($mail, $flag, $entity_id);
  }

  /**
   * {@inheritdoc}
   */
  private function checkSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash = ''): bool {
    // Query to check values, all parameters need to match.
    $query = $this->connection->select('oe_subscriptions_anonymous_subscriptions', 's')
      ->fields('s', ['mail'])
      ->condition('s.mail', $mail)
      ->condition('s.flag_id', $flag->id())
      ->condition('s.entity_id', $entity_id);

    // We will use hash depending on the check.
    if (!empty($hash)) {
      $query->condition('s.hash', $hash);
    }

    // If there is a result.
    return (!empty($query->execute()->fetchAll()));
  }

}
