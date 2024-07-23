<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\Exception\RegisteredUserEmailException;

/**
 * Class to manage anonymous subscriptions.
 */
class AnonymousSubscriptionManager implements AnonymousSubscriptionManagerInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly FlagServiceInterface $flagService,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function subscribe(string $mail, FlagInterface $flag, string|int $entity_id): bool {
    // Load entity.
    $entity = $this->flagService->getFlaggableById($flag, (int) $entity_id);
    if (empty($entity)) {
      return FALSE;
    }

    // Check if a user with this email already exists.
    $account = user_load_by_mail($mail);

    // If no user is present, create a decoupled user.
    if ($account === FALSE) {
      /** @var \Drupal\user\UserInterface $account */
      $account = $this->entityTypeManager->getStorage('user')->create([
        'mail' => $mail,
        'message_subscribe_email' => TRUE,
      ]);
      $account->addRole('anonymous_subscriber')->save();
    }

    /** @var \Drupal\decoupled_auth\DecoupledAuthUserInterface $account */
    if ($account->isCoupled()) {
      throw new RegisteredUserEmailException(sprintf('The e-mail %s belongs to a fully registered user.', $mail));
    }

    if (!$account->hasRole('anonymous_subscriber')) {
      $account->addRole('anonymous_subscriber')->save();
    }

    // Already flagged.
    if ($flag->isFlagged($entity, $account)) {
      return TRUE;
    }

    $this->flagService->flag($flag, $entity, $account);

    return TRUE;
  }

}
