<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions\Form\UserSubscriptionsForm;
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
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected FlagServiceInterface $flagService,
    protected TokenManagerInterface $tokenManager,
    protected AnonymousSubscriptionManager $subscriptionManager,
    FormBuilderInterface $formBuilder,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    // These two properties are declared in ControllerBase class, so we cannot
    // use constructor property promotion.
    $this->formBuilder = $formBuilder;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag'),
      $container->get('oe_subscriptions_anonymous.token_manager'),
      $container->get('oe_subscriptions_anonymous.subscription_manager'),
      $container->get('form_builder'),
      $container->get('entity_type.manager')
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
      $this->messenger()->addWarning($this->t('You have tried to use a link that has been used or is no longer valid. Please request a new link.'));

      return $response;
    }

    // The token has been used, so we need to invalidate it.
    $this->tokenManager->delete($email, $scope);

    $this->subscriptionManager->subscribe($email, $flag, $entity_id);
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
      $this->messenger()->addWarning($this->t('You have tried to use a link that has been used or is no longer valid. Please request a new link.'));
    }

    return $this->redirect('<front>');
  }

  /**
   * Renders the subscriptions page for anonymous subscribers.
   *
   * @param string $email
   *   The user e-mail.
   * @param string $token
   *   The token to validate the request.
   *
   * @return mixed
   *   A redirect response if token or user are not valid, the subscriptions
   *   form otherwise.
   */
  public function userSubscriptionsPage(string $email, string $token) {
    if (!$this->tokenManager->isValid($email, 'user_subscriptions_page', $token)) {
      $this->messenger()->addError($this->t('Your token is either invalid or it has expired. Please request a new token to access your subscriptions.'));
      return $this->redirect('oe_subscriptions_anonymous.user_subscriptions.request_access');
    }

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'mail' => $email,
    ]);
    /** @var \Drupal\decoupled_auth\DecoupledAuthUserInterface $user */
    $user = empty($users) ? NULL : reset($users);
    if (!$user) {
      $this->messenger()->addWarning($this->t("You don't have any subscriptions at the moment."));
      return $this->redirect('<front>');
    }

    if ($user->isCoupled()) {
      $this->messenger()->addWarning($this->t('It seems that you have an account on this website. Please login to manage your subscriptions.'));
      return $this->redirect('user.login', [], [
        'query' => [
          'destination' => Url::fromRoute('oe_subscriptions.user.subscriptions', [
            'user' => $user->id(),
          ])->toString(),
        ],
      ]);
    }

    return $this->formBuilder->getForm(UserSubscriptionsForm::class, $user);
  }

}
