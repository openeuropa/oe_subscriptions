<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionManagerInterface;
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
   * @var \Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionManagerInterface
   */
  protected AnonymousSubscriptionManagerInterface $anonymousSubscriptionManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    FlagServiceInterface $flagService,
    AnonymousSubscriptionManagerInterface $anonymousSubscriptionManager) {
    $this->flagService = $flagService;
    $this->anonymousSubscriptionManager = $anonymousSubscriptionManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag'),
      $container->get('oe_subscriptions_anonymous.subscription_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function confirmSubscription(FlagInterface $flag, string $entity_id, string $email, string $hash) {
    // Get changed value.
    $changed = $this->anonymousSubscriptionManager->getSubscriptionChanged($email, $flag, $entity_id);

    // More than a day is a expired hash.
    if ($changed !== '' && (time() - $changed) >= 86400) {
      $this->messenger()->addMessage($this->t('The confirmation link has expired, request the subscription again please.'), MessengerInterface::TYPE_ERROR);
      return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    if ($this->anonymousSubscriptionManager->confirmSubscription($email, $flag, $entity_id, $hash)) {
      // Success message and redirection to entity.
      $this->messenger()->addMessage($this->t('Subscription confirmed.'));
      $entity = $this->flagService->getFlaggableById($flag, $entity_id);

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

    if ($this->anonymousSubscriptionManager->cancelSubscription($email, $flag, $entity_id, $hash)) {
      // Success message and redirection to entity.
      $this->messenger()->addMessage($this->t('Subscription canceled.'));
      $entity = $this->flagService->getFlaggableById($flag, $entity_id);

      return new RedirectResponse($entity->toUrl()->toString());
    }

    // Error message and redirection to home.
    $this->messenger()->addMessage($this->t('The subscription could not be canceled.'), MessengerInterface::TYPE_ERROR);
    return new RedirectResponse(Url::fromRoute('<front>')->toString());
  }

}
