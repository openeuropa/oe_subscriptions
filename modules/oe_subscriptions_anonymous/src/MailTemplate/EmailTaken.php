<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Prepares the "email_taken" mail.
 *
 * This is sent if an anonymous user attempts to subscribe with an email address
 * that is already associated with a regular user account.
 */
class EmailTaken implements ContainerInjectionInterface, MailTemplateInterface {

  use StringTranslationTrait;
  use AutowireTrait;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getParameters(): array {
    return [
      'email',
      'entity_type',
      'entity_id',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getVariables(array $params): array {
    [
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
    ] = $params;

    $entity = $this->entityTypeManager
      ->getStorage($entity_type)
      ->load($entity_id);

    // @todo In theory, it is possible that the entity no longer exists.
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

    $message['subject'] = $this->t('Cannot subscribe to @entity_label', [
      '@entity_label' => $variables['entity_label'],
    ]);

    $message['body'] = $this->t(
      '<p>Thank you for showing interest in keeping up with the updates for <a href=":entity_url">@entity_label</a>!</p>
<p>The email address you were using to subscribe is already associated with a regular account on this website.</p>
<p>If you still want to subscribe to content updates for this item, you should log into the website, using your existing account, and then subscribe as a regular user.</p>
<p>If you do not want to subscribe, you can ignore this message.</p>',
      [
        '@entity_label' => $variables['entity_label'],
        ':entity_url' => $variables['entity_url'],
      ],
    );

    return $message;
  }

}
