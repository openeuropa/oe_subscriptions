<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\EmailValidatorInterface;
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
   * Mail validator service.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $mailValidatorService;

  /**
   * Constructs a Anonymous Subscription manager.
   */
  public function __construct(Connection $connection, FlagServiceInterface $flagService, EmailValidatorInterface $mailValidatorService) {
    $this->connection = $connection;
    $this->flagService = $flagService;
    $this->mailValidatorService = $mailValidatorService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('database'),
      $container->get('flag'),
      $container->get('email.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createSubscription(string $mail, FlagInterface $flag, string $entity_id): string {
    // Validate mail.
    if (!$this->mailValidatorService->isValid($mail)) {
      // @todo Add messaging/logging.
      return '';
    }

    $flag_id = $flag->id();
    $hash = Crypt::randomBytesBase64();

    // In case we have an existing unconfirmed, we update hash.
    if ($this->subscriptionExists($mail, $flag, $entity_id)) {
      $this->connection->update('oe_subscriptions_anonymous_subscriptions')
        ->fields(['hash' => $hash])
        ->condition('mail', $mail)
        ->condition('flag_id', $flag_id)
        ->condition('entity_id', $entity_id)
        ->execute();

      return $hash;
    }

    // Create new entry.
    $this->connection->insert('oe_subscriptions_anonymous_subscriptions')
      ->fields([
        'mail' => $mail,
        'flag_id' => $flag_id,
        'entity_id' => $entity_id,
        'hash' => $hash,
      ])->execute();

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash): bool {
    if (!$this->mailValidatorService->isValid($mail)) {
      // @todo Add messaging/logging.
      return FALSE;
    }
    // Check parameters.
    if (!$this->checkSubscription($mail, $flag, $entity_id, $hash)) {
      // @todo Add messaging/logging.
      return FALSE;
    }
    // Load entity.
    $entity = $this->flagService->getFlaggableById($flag, $entity_id);
    if (empty($entity)) {
      // @todo Add messaging/logging.
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
      // @todo Add messaging/logging.
      return FALSE;
    }
    // Do flag.
    $flagging = $this->flagService->flag($flag, $entity, $account);
    if (empty($flagging)) {
      return FALSE;
    }
    // Clean validated subscriptions.
    return $this->deleteSubscription($mail, $flag, $entity_id);

  }

  /**
   * {@inheritdoc}
   */
  public function cancelSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash): bool {
    if (!$this->mailValidatorService->isValid($mail)) {
      // @todo Add messaging/logging.
      return FALSE;
    }
    // Delete if unconfirmed.
    if ($this->checkSubscription($mail, $flag, $entity_id, $hash)) {
      // If exists has not been validated, bail out.
      return $this->deleteSubscription($mail, $flag, $entity_id);
    }
    // Delete confirmed.
    $account = user_load_by_mail($mail);
    if (empty($account)) {
      // @todo Add messaging/logging.
      return FALSE;
    }
    // Load entity.
    $entity = $this->flagService->getFlaggableById($flag, $entity_id);
    if (empty($entity)) {
      // @todo Add messaging/logging.
      return FALSE;
    }
    // No flagging.
    if (empty($this->flagService->getFlagging($flag, $entity, $account))) {
      return FALSE;
    }
    // Do unflag.
    $this->flagService->unflag($flag, $entity, $account);
    // @todo delete user without flaggins.
    return TRUE;
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
  private function deleteSubscription(string $mail, FlagInterface $flag, string $entity_id): bool {
    // Check that exists.
    if (!$this->checkSubscription($mail, $flag, $entity_id)) {
      // @todo Add messaging/logging.
      return FALSE;
    }
    // Delete entry.
    $query = $this->connection->delete('oe_subscriptions_anonymous_subscriptions')
      ->condition('mail', $mail)
      ->condition('flag_id', $flag->id())
      ->condition('entity_id', $entity_id);

    return (!empty($query->execute()));
  }

  /**
   * {@inheritdoc}
   */
  private function checkSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash = ''): bool {
    // @todo Add checks, validate mail, flag and entity.
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
