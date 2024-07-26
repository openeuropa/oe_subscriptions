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

  $info['tokens']['user']['subscriptions-page-url'] = [
    'name' => t('Subscriptions page'),
    'description' => t('URL to subscriptions page based on the user, to be used in message entities.'),
    'type' => 'url',
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function oe_subscriptions_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if (
    $type === 'user' &&
    !empty($data['user']) &&
    !empty($data['message']) &&
    isset($tokens['subscriptions-page-url'])
  ) {
    // Adding the URL to the subscriptions page will lead into an 403 for
    // non-logged users, we add a login URL with the subscriptions page
    // destination.
    $url = Url::fromRoute('user.login', [], [
      'absolute' => TRUE,
      'query' => [
        'destination' => Url::fromRoute('oe_subscriptions.user.subscriptions', [
          'user' => $data['user']->id(),
        ])->toString(),
      ],
    ]);

    $replacements[$tokens['subscriptions-page-url']] = $url->toString();
  }

  return $replacements;
}
