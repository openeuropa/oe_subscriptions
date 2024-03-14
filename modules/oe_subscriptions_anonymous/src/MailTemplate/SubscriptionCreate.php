<?php

declare(strict_types=1);

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
  public function prepare(array $params, bool $has_html = FALSE): array {
    [
      'email' => $mail,
      'flag' => $flag,
      'entity_id' => $entity_id,
    ] = $params;
    $message = [];

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
    $confirm_link = Link::createFromRoute($this->t('Confirm my subscription'), 'oe_subscriptions_anonymous.subscription_request.confirm', $route_parameters, [
      'absolute' => TRUE,
    ])->toString();

    $cancel_link = Link::createFromRoute($this->t('Cancel the subscription request'), 'oe_subscriptions_anonymous.subscription_request.cancel', $route_parameters, [
      'absolute' => TRUE,
    ])->toString();

    // Links for subscription management.
    $variables = [
      '@entity_link' => $entity->toLink()->toString(),
      '@confirm_link' => $confirm_link,
      '@cancel_link' => $cancel_link,
    ];

    $body = $this->t("Thank you for showing interest in keeping up with the updates for @entity_link! \r\n
Click the following link to confirm your subscription: @confirm_link \r\n
If you no longer wish to subscribe, click on the link bellow: @cancel_link \r\n
If you didn't subscribe to these updates or you're not sure why you received this e-mail, you can delete it. \r\n
You will not be subscribed if you don't click on the confirmation link above.", $variables);
    $message['subject'] = $this->t('Confirm your subscription to @label', [
      '@label' => $entity->label(),
    ]);
    $message['body'] = $has_html ? $body : MailFormatHelper::htmlToText($body);

    return $message;
  }

}
