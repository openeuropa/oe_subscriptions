<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\TokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prepares the "subscription_create" mail.
 */
class SubscriptionCreate implements ContainerInjectionInterface, MailTemplateInterface {

  use StringTranslationTrait;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   * @param \Drupal\oe_subscriptions_anonymous\TokenManagerInterface $tokenManager
   *   The token manager.
   */
  public function __construct(protected FlagServiceInterface $flagService, protected TokenManagerInterface $tokenManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flag'),
      $container->get('oe_subscriptions_anonymous.token_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters(): array {
    return [
      'email',
      'flag',
      'entity_id',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getVariables(array $params): array {
    [
      'email' => $mail,
      'flag' => $flag,
      'entity_id' => $entity_id,
    ] = $params;

    $entity = $this->flagService->getFlaggableById($flag, $entity_id);

    // Generate scope for subscribe.
    $scope = $this->tokenManager::buildScope(TokenManagerInterface::TYPE_SUBSCRIBE, [
      $flag->id(),
      $entity_id,
    ]);
    $hash = $this->tokenManager->get($mail, $scope);

    // Generate mail links confirm and cancel.
    $route_parameters = [
      'flag' => $flag->id(),
      'entity_id' => $entity_id,
      'email' => $mail,
      'hash' => $hash,
    ];

    $confirm_url = Url::fromRoute('oe_subscriptions_anonymous.subscription_request.confirm', $route_parameters, [
      'absolute' => TRUE,
    ])->toString();

    $cancel_url = Url::fromRoute('oe_subscriptions_anonymous.subscription_request.cancel', $route_parameters, [
      'absolute' => TRUE,
    ])->toString();

    return [
      'entity_label' => $entity->label(),
      'entity_url' => $entity->toUrl()->setAbsolute()->toString(),
      'confirm_url' => $confirm_url,
      'cancel_url' => $cancel_url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array $params): array {
    $variables = $this->getVariables($params);

    $message['subject'] = $this->t('Confirm your subscription to @entity_label', [
      '@entity_label' => $variables['entity_label'],
    ]);

    $message['body'] = $this->t('Thank you for showing interest in keeping up with the updates for <a href=":entity_url">@entity_label</a>!<br>
Click the following link to confirm your subscription: <a href=":confirm_url">Confirm my subscription</a>.<br>
If you no longer wish to subscribe, click on the link bellow: <a href=":cancel_url">Cancel my subscription</a>.<br>
If you didn\'t subscribe to these updates or you\'re not sure why you received this e-mail, you can delete it.
You will not be subscribed if you don\'t click on the confirmation link above.',
    [
      '@entity_label' => $variables['entity_label'],
      ':entity_url' => $variables['entity_url'],
      ':confirm_url' => $variables['confirm_url'],
      ':cancel_url' => $variables['cancel_url'],
    ]);

    return $message;
  }

}
