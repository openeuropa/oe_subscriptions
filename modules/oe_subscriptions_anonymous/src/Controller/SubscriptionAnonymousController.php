<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionManager;
use Drupal\oe_subscriptions_anonymous\TokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class SubscriptionAnonymousController.
 *
 * Used to handle anonymous subscriptions.
 */
class SubscriptionAnonymousController extends ControllerBase {

  /**
   * Creates a new instances of this class.
   *
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   * @param \Drupal\oe_subscriptions_anonymous\TokenManagerInterface $tokenManager
   *   The token manager.
   * @param \Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionManager $subscriptionManager
   *   The subscription manager.
   */
  public function __construct(
    protected FlagServiceInterface $flagService,
    protected TokenManagerInterface $tokenManager,
    protected AnonymousSubscriptionManager $subscriptionManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag'),
      $container->get('oe_subscriptions_anonymous.token_manager'),
      $container->get('oe_subscriptions_anonymous.subscription_manager'),
    );
  }

  /**
   * Confirms a subscription request.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param string $entity_id
   *   The ID of the entity to subscribe to.
   * @param string $email
   *   The e-mail address.
   * @param string $hash
   *   The token to validate the request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   */
  public function confirmSubscriptionRequest(FlagInterface $flag, string $entity_id, string $email, string $hash): RedirectResponse {
    $entity = $this->flagService->getFlaggableById($flag, (int) $entity_id);
    $response = new RedirectResponse($entity->toUrl()->toString());

    $scope = $this->tokenManager->buildScope(TokenManagerInterface::TYPE_SUBSCRIBE, [
      $flag->id(),
      $entity_id,
    ]);

    if (!$this->tokenManager->isValid($email, $scope, $hash)) {
      // The token could be expired or not existing. But to avoid disclosing
      // information about users that actually requested to subscribe, we
      // always use the same message.
      $this->messenger()->addWarning($this->t('You have tried to use a cancel link that has been used or is no longer valid. Please request a new link.'));

      return $response;
    }

    $this->subscriptionManager->subscribe($email, $flag, $entity_id);
    // The token has been used, so we need to invalidate it.
    $this->tokenManager->delete($email, $scope);
    // Success message and redirection to entity.
    $this->messenger()->addMessage($this->t('Your subscription request has been confirmed.'));

    return $response;
  }

  /**
   * Cancels a subscription request.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param string $entity_id
   *   The ID of the entity to subscribe to.
   * @param string $email
   *   The e-mail address.
   * @param string $hash
   *   The token to validate the request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response.
   */
  public function cancelSubscriptionRequest(FlagInterface $flag, string $entity_id, string $email, string $hash): RedirectResponse {
    $scope = $this->tokenManager->buildScope(
      TokenManagerInterface::TYPE_SUBSCRIBE, [
        $flag->id(),
        $entity_id,
      ]);

    if ($this->tokenManager->isValid($email, $scope, $hash)) {
      $this->tokenManager->delete($email, $scope);
      $this->messenger()->addStatus($this->t('Your subscription request has been canceled.'));
    }
    else {
      $this->messenger()->addError($this->t('You have tried to use a cancel link that has been used or is no longer valid. Please request a new link.'));
    }

    return new RedirectResponse(Url::fromRoute('<front>')->toString());

  }

}
