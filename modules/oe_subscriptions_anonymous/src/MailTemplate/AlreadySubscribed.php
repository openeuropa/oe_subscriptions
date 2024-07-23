<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\TokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prepares the "already_subscribed" mail.
 */
class AlreadySubscribed implements ContainerInjectionInterface, MailTemplateInterface {

  use StringTranslationTrait;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   * @param \Drupal\oe_subscriptions_anonymous\TokenManagerInterface $tokenManager
   *   The token manager.
   */
  public function __construct(
    protected readonly FlagServiceInterface $flagService,
    protected readonly TokenManagerInterface $tokenManager,
  ) {}

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
      'flag' => $flag,
      'entity_id' => $entity_id,
    ] = $params;

    $entity = $this->flagService->getFlaggableById($flag, $entity_id);

    return [
      'entity_label' => $entity->label(),
      'entity_url' => $entity->toUrl()->setAbsolute()->toString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array $params): array {
    $variables = $this->getVariables($params);

    $message['subject'] = $this->t('Already subscribed to @entity_label', [
      '@entity_label' => $variables['entity_label'],
    ]);

    $message['body'] = $this->t(
      '<p>Thank you for showing interest in keeping up with the updates for <a href=":entity_url">@entity_label</a>!</p>
<p>You are already subscribed to this item.</p>
<p>If you no longer wish to receive updates for this item, you can <a href=":cancel_url">Unsubscribe</a>.</p>
<p>If you want to remain subscribed, you can ignore this message.</p>',
      [
        '@entity_label' => $variables['entity_label'],
        ':entity_url' => $variables['entity_url'],
        ':cancel_url' => $variables['cancel_url'],
      ],
    );

    return $message;
  }

}
