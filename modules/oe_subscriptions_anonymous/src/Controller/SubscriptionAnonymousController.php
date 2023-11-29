<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionManager;
use Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class SubscriptionAnonymousController.
 *
 * Used to handle anonymous subscriptions.
 */
class SubscriptionAnonymousController extends ControllerBase {

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected FlagServiceInterface $flagService;

  /**
   * Anonymous subscribe manager service.
   *
   * @var \Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionManager
   */
  protected AnonymousSubscriptionManager $subscriptionManager;

  /**
   * Anonymous subscribe manager service.
   *
   * @var \Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionStorageInterface
   */
  protected AnonymousSubscriptionStorageInterface $anonymousSubscriptionStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    FlagServiceInterface $flagService,
    AnonymousSubscriptionStorageInterface $anonymousSubscriptionStorage,
    AnonymousSubscriptionManager $subscriptionManager,
    ) {
    $this->flagService = $flagService;
    $this->anonymousSubscriptionStorage = $anonymousSubscriptionStorage;
    $this->subscriptionManager = $subscriptionManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag'),
      $container->get('oe_subscriptions_anonymous.subscription_storage'),
      $container->get('oe_subscriptions_anonymous.subscription_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function confirmSubscription(FlagInterface $flag, string $entity_id, string $email, string $hash) {

    $scope = $this->anonymousSubscriptionStorage->buildScope(
      AnonymousSubscriptionStorageInterface::TYPE_SUBSCRIBE, [
        $flag->id(),
        $entity_id,
      ]);

    if ($this->anonymousSubscriptionStorage->isValid($email, $scope, $hash)) {
      // It's valid, so we create user if needed and do flag.
      $this->subscriptionManager->subscribe($email, $flag, $entity_id);
      // Success message and redirection to entity.
      $this->messenger()->addMessage($this->t('Subscription confirmed.'));
      $entity = $this->flagService->getFlaggableById($flag, (int) $entity_id);

      return new RedirectResponse($entity->toUrl()->toString());
    }

    // Error message and redirection to home.
    $this->messenger()->addMessage($this->t('The subscription could not be confirmed.'), MessengerInterface::TYPE_ERROR);
    return new RedirectResponse(Url::fromRoute('<front>')->toString());
  }

  /**
   * {@inheritdoc}
   */
  public function cancelSubscription(FlagInterface $flag, string $entity_id, string $email, string $hash) {

    $scope = $this->anonymousSubscriptionStorage->buildScope(
      AnonymousSubscriptionStorageInterface::TYPE_SUBSCRIBE, [
        $flag->id(),
        $entity_id,
      ]);

    if ($this->anonymousSubscriptionStorage->isValid($email, $scope, $hash)) {
      // Load elements to unflag.
      $account = user_load_by_mail($email);
      $entity = $this->flagService->getFlaggableById($flag, (int) $entity_id);
      // In case where the flag was done.
      if (!empty($entity) && !empty($account) && $flag->isFlagged($entity, $account)) {
        $this->flagService->unflag($flag, $entity, $account);
      }
      // Then delete entry.
      $this->anonymousSubscriptionStorage->delete($email, $scope);
      // Success message.
      $this->messenger()->addMessage($this->t('Subscription canceled.'));
      // And redirection to entity.
      $entity = $this->flagService->getFlaggableById($flag, (int) $entity_id);

      return new RedirectResponse($entity->toUrl()->toString());
    }

    // Error message and redirection to home.
    $this->messenger()->addMessage($this->t('The subscription could not be canceled.'), MessengerInterface::TYPE_ERROR);
    return new RedirectResponse(Url::fromRoute('<front>')->toString());

  }

}
