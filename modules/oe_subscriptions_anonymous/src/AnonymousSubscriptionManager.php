<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Class to manage anonymous subscriptions.
 */
class AnonymousSubscriptionManager implements AnonymousSubscriptionManagerInterface {

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    FlagServiceInterface $flagService,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->flagService = $flagService;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe(string $mail, FlagInterface $flag, string $entity_id): bool {
    // Load entity.
    $entity = $this->flagService->getFlaggableById($flag, (int) $entity_id);
    if (empty($entity)) {
      return FALSE;
    }
    // Try to load user.
    $account = user_load_by_mail($mail);
    // Create decoupled user.
    if ($account === FALSE) {
      $account = $this->entityTypeManager->getStorage('user')->create(['mail' => $mail]);
      $account->save();
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
    // Return result.
    return $flag->isFlagged($entity, $account);
  }

}
