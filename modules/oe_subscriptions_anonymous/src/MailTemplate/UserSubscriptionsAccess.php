<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
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
  public function getParameters(): array {
    return [
      'email',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getVariables(array $params): array {
    $mail = $params['email'];
    $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $subscriptions_page_url = Url::fromRoute('oe_subscriptions_anonymous.user_subscriptions.view', [
      'email' => $params['email'],
      'token' => $this->tokenManager->get($mail, 'user_subscriptions_page'),
    ])->setAbsolute()->toString();

    return [
      // Mimic the [site:url-brief] token.
      'site_url_brief' => preg_replace(['!^https?://!', '!/$!'], '', $site_url),
      'subscriptions_page_url' => $subscriptions_page_url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array $params): array {
    $variables = $this->getVariables($params);

    $message['subject'] = $this->t('Access your subscriptions page on @site_url_brief', [
      '@site_url_brief' => $variables['site_url_brief'],
    ]);

    $message['body'] = $this->t('You are receiving this e-mail because you requested access to your subscriptions page on @site_url_brief.<br>
Click the following link to access your subscriptions page: <a href=":subscriptions_page_url">Access my subscriptions page</a>.<br>
If you didn\'t request access to your subscriptions page or you\'re not sure why you received this e-mail, you can delete it.',
    [
      '@site_url_brief' => $variables['site_url_brief'],
      ':subscriptions_page_url' => $variables['subscriptions_page_url'],
    ]);

    return $message;
  }

}
