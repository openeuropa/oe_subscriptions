<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
  public function prepare(array &$message, array $params): void {
    [
      'email' => $mail,
      'flag' => $flag,
      'entity_id' => $entity_id,
    ] = $params;

    /** @var \Drupal\flag\FlagInterface $flag */
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
    $confirm_link = Link::createFromRoute($this->t('Confirm subscription request'), 'oe_subscriptions_anonymous.subscription_request.confirm', $route_parameters, [
      'absolute' => TRUE,
    ])->toString();

    $cancel_link = Link::createFromRoute($this->t('Cancel subscription request'), 'oe_subscriptions_anonymous.subscription_request.cancel', $route_parameters, [
      'absolute' => TRUE,
    ])->toString();

    // Links for subscription management.
    $variables = [
      '@entity_link' => $entity->toLink()->toString(),
      '@confirm_link' => $confirm_link,
      '@cancel_link' => $cancel_link,
    ];

    $body = $this->t("@entity_link \r\n @confirm_link \r\n @cancel_link", $variables);
    $message['subject'] .= $this->t('Confirm your subscription to @label', [
      '@label' => $entity->label(),
    ]);
    $message['body'][] = MailFormatHelper::htmlToText($body);
  }

}
