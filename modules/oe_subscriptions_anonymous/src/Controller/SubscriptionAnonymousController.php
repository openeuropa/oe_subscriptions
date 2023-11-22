<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Controller;

use Drupal\Core\Controller\ControllerBase;
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

    if ($this->anonymousSubscriptionManager->confirmSubscription($email, $flag, $entity_id, $hash)) {
      $this->messenger()->addMessage($this->t('Subscription confirmed.'));
      $entity = $this->flagService->getFlaggableById($flag, $entity_id);

      return new RedirectResponse($entity->toUrl()->toString());
    }

    return new RedirectResponse(Url::fromRoute('<front>')->toString());
  }

  /**
   * {@inheritdoc}
   */
  public function cancelSubscription(FlagInterface $flag, string $entity_id, string $email, string $hash) {

    if ($this->anonymousSubscriptionManager->cancelSubscription($email, $flag, $entity_id, $hash)) {
      $this->messenger()->addMessage($this->t('Subscription canceled.'));
      $entity = $this->flagService->getFlaggableById($flag, $entity_id);

      return new RedirectResponse($entity->toUrl()->toString());
    }

    return new RedirectResponse(Url::fromRoute('<front>')->toString());
  }

}
