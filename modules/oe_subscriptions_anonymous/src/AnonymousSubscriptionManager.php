<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\Entity\User;

/**
 * Class to manage anonymous subscriptions.
 */
class AnonymousSubscriptionManager {

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
   * Entity type manager.
   *
   * @var \Drupal\flag\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a Anonymous Subscription manager.
   */
  public function __construct(
    Connection $connection,
    FlagServiceInterface $flagService,
    EntityTypeManagerInterface $entityTypeManager
    ) {
    $this->connection = $connection;
    $this->flagService = $flagService;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('database'),
      $container->get('flag'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createSubscription(string $mail, string $flag_id, string $entity_id): string {
    $exists = $this->subscriptionExists($mail, $flag_id, $entity_id);
    if ($exists) {
      // @todo Add messaging/logging.
      return '';
    }
    $token = hash('sha512', "oe_subscriptions_anonymous:$mail:$flag_id:$entity_id");
    $this->connection->insert('oe_subscriptions_anonymous_subscriptions')
      ->fields([
        'mail' => $mail,
        'flag_id' => $flag_id,
        'entity_id' => $entity_id,
        'token' => $token,
      ])->execute();
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSubscription(string $mail, string $flag_id, string $entity_id, string $token): void {
    // Check parameters.
    if (!$this->checkSubscription($mail, $flag_id, $entity_id, $token)) {
      // @todo Add messaging/logging.
      return;
    }
    // Load flag.
    $flag = $this->flagService->getFlagById($flag_id);
    if (empty($flag)) {
      // @todo Add messaging/logging.
      return;
    }
    // Load entity.
    $entity_type = $flag->getFlaggableEntityTypeId();
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    if (empty($entity)) {
      // @todo Add messaging/logging.
      return;
    }
    // Try to load user.
    $account = user_load_by_mail($mail);
    // Create decoupled user.
    if ($account === FALSE) {
      $user = User::create(['mail' => $mail])->save();
      $account = User::load($user);
    }
    if (empty($account)) {
      // @todo Add messaging/logging.
      return;
    }
    // Do flag.
    $this->flagService->flag($flag, $entity, $account);
    // Delete anonymous sub.
    $this->deleteSubscription($mail, $flag_id, $entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function cancelSubscription(string $mail, string $flag_id, string $entity_id, string $token): void {
    // Check the subscription exist and has a valid token.
    if ($this->checkSubscription($mail, $flag_id, $entity_id, $token)) {
      $this->deleteSubscription($mail, $flag_id, $entity_id);
      // If exists has not been validated, bail out.
      return;
    }
    // Else delete flagging, and user.
    $flag = $this->flagService->getFlagById($flag_id);
    if (empty($flag)) {
      // @todo Add messaging/logging.
      return;
    }
    // Try to load user.
    $account = user_load_by_mail($mail);
    if (empty($account)) {
      // @todo Add messaging/logging.
      return;
    }
    // Load entity.
    $entity_type = $flag->getFlaggableEntityTypeId();
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    // Do unflag.
    $this->flagService->unflag($flag, $entity, $account);
    if (empty($entity)) {
      // @todo Add messaging/logging.
      return;
    }
    // @todo cleanup user without flaggins.
  }

  /**
   * {@inheritdoc}
   */
  public function subscriptionExists(string $mail, string $flag_id, string $entity_id): bool {
    // The subscription exists, no need of token.
    return $this->checkSubscription($mail, $flag_id, $entity_id);
  }

  /**
   * {@inheritdoc}
   */
  private function deleteSubscription(string $mail, string $flag_id, string $entity_id): void {
    // Check that exists.
    if (!$this->checkSubscription($mail, $flag_id, $entity_id)) {
      // @todo Add messaging/logging.
      return;
    }
    // Delete entry.
    $this->connection->delete('oe_subscriptions_anonymous_subscriptions')
      ->condition('mail', $mail)
      ->condition('flag_id', $flag_id)
      ->condition('entity_id', $entity_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  private function checkSubscription(string $mail, string $flag_id, string $entity_id, string $token = ''): bool {
    // @todo Add checks, validate mail, flag and entity.
    // Query to check values, all parameters need to match.
    $query = $this->connection->select('oe_subscriptions_anonymous_subscriptions', 's')
      ->fields('s', ['mail'])
      ->condition('s.mail', $mail)
      ->condition('s.flag_id', $flag_id)
      ->condition('s.entity_id', $entity_id);
    // We will use token depending on the check.
    if (!empty($token)) {
      $query->condition('s.token', $token);
    }
    // If there is a result.
    return (!empty($query->execute()->fetchAll()));
  }

}
