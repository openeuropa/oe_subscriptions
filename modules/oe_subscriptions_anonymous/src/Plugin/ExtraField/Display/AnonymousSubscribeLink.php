<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;

/**
 * Anonymous subscribe link.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_subscriptions_anonymous_subscribe_link",
 *   label = @Translation("Anonymous subscribe link"),
 *   description = @Translation("Link to subscribe to notifications about this entity."),
 *   bundles = {
 *     "node.*"
 *   },
 *   weight = -30,
 *   visible = true
 * )
 */
class AnonymousSubscribeLink extends ExtraFieldDisplayBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {

    // Based con extra field config.
    // Based on entity available flags.
    $url = Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe', [
      'subscription_id' => implode(':', [
        'subscribe_node',
        $entity->id(),
      ]),
    ]);

    $build = [];

    if (!$url->access()) {
      // @todo Handle caching.
      return $build;
    }

    $build = [
      '#type' => 'link',
      '#title' => $this->t('Anonymous Subscribe'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['use-ajax', 'button', 'button--small'],
        'data-dialog-type' => 'modal',
      ],
    ];

    return $build;
  }

}
