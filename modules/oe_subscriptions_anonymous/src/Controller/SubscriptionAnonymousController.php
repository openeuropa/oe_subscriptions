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
  public function confirmSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash) {

    $hash = $this->anonymousSubscriptionManager->createSubscription($mail, $flag, $entity_id, $hash);

    if (!$result) {
      return new RedirectResponse(Url::fromRoute('<front>'));
    }

    $this->messenger()->addMessage($this->t("Subscription confirmed."));
    $entity = $this->flag->getFlaggableById($flag, $entity_id);

    return new RedirectResponse($entity->toUrl()->toString());
  }

  /**
   * {@inheritdoc}
   */
  public function cancelSubscription(string $mail, FlagInterface $flag, string $entity_id, string $hash) {

    $result = $this->anonymousSubscriptionManager->cancelSubscription($mail, $flag, $entity_id, $hash);

    if (!$result) {
      return new RedirectResponse(Url::fromRoute('<front>'));
    }

    $this->messenger()->addMessage($this->t("Subscription canceled."));
    $entity = $this->flag->getFlaggableById($flag, $entity_id);

    return new RedirectResponse($entity->toUrl()->toString());
  }

}
