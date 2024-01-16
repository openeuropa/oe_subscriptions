<?php

namespace Drupal\oe_subscriptions_anonymous_test;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller routines for theme test routes.
 */
class TestController extends ControllerBase {

  /**
   * Renders anonymous link template with different parameters.
   */
  public function testLinkTemplate() {
    $markup = '';

    $markup .= \Drupal::theme()->render(
      'oe_subscriptions_anonymous_link', [
        'title' => 'Link to front',
        'url' => Url::fromRoute('<front>'),
      ]);

    $markup .= \Drupal::theme()->render(
    'oe_subscriptions_anonymous_link', [
      'title' => 'Link without URL',
      'url' => [],
    ]);

    $markup .= \Drupal::theme()->render(
    'oe_subscriptions_anonymous_link', [
      'title' => 'Link with attributtes',
      'url' => Url::fromRoute('<front>'),
      'attributes' => [
        'class' => ['class-1', 'class-2'],
        'attribute-2' => 'attribute-2-value',
      ],
    ]);

    $markup .= \Drupal::theme()->render(
    'oe_subscriptions_anonymous_link', [
      'title' => 'Link with attached',
      'url' => Url::fromRoute('<front>'),
      '#attached' => [
        'library' => [
          'core/drupal.ajax',
        ],
      ],
    ]);

    return ['#markup' => $markup];
  }

}
