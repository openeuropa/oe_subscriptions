<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_subscriptions_anonymous\TokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prepares the "user_subscriptions_access" mail.
 */
class UserSubscriptionsAccess implements ContainerInjectionInterface, MailTemplateInterface {

  use StringTranslationTrait;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\oe_subscriptions_anonymous\TokenManagerInterface $tokenManager
   *   The token manager.
   */
  public function __construct(protected TokenManagerInterface $tokenManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_subscriptions_anonymous.token_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$message, array $params): void {
    $mail = $params['email'];
    $hash = $this->tokenManager->get($mail, 'user_subscriptions_page');

    $link = Link::createFromRoute($this->t('Click here to access your subscriptions page.'), 'oe_subscriptions_anonymous.user_subscriptions.view', [
      'email' => $mail,
      'token' => $hash,
    ], [
      'absolute' => TRUE,
    ])->toString();

    $message['subject'] .= $this->t('Access your subscriptions page');
    $message['body'][] = MailFormatHelper::htmlToText($link);
  }

}
