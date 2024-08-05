<?php

/**
 * @file
 * Token callbacks for the Subscriptions module.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Implements hook_token_info().
 */
function oe_subscriptions_token_info(): array {
  $info = [];

  $info['tokens']['user']['subscriptions-page'] = [
    'name' => t('Subscriptions page URL'),
    'description' => t('The URL to subscriptions page.'),
    'type' => 'url',
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function oe_subscriptions_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  $replacements = [];

  if (
    $type === 'user' &&
    !empty($data['user'])
  ) {
    // Adding the URL to the subscriptions page will lead into an 403 for
    // non-logged users, we add a login URL with the subscriptions page
    // destination.
    $url = Url::fromRoute('user.login', [], [
      'query' => [
        'destination' => Url::fromRoute('oe_subscriptions.user.subscriptions', [
          'user' => $data['user']->id(),
        ])->toString(),
      ],
    ]);

    if (isset($tokens['subscriptions-page'])) {
      $result = $url->setAbsolute()->toString(TRUE);
      $bubbleable_metadata->addCacheableDependency($result);
      $replacements[$tokens['subscriptions-page']] = $result->getGeneratedUrl();
    }

    // Chained token relationships.
    if (($url_tokens = \Drupal::token()->findWithPrefix($tokens, 'subscriptions-page'))) {
      $replacements += \Drupal::token()->generate('url', $url_tokens, ['url' => $url], $options, $bubbleable_metadata);
    }
  }

  return $replacements;
}
