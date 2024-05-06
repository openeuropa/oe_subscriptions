<?php

/**
 * @file
 * Token callbacks for the Anonymous subscriptions module.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Implements hook_token_info().
 */
function oe_subscriptions_anonymous_token_info(): array {
  $type = [
    'name' => t('Anonymous subscriptions'),
    'description' => t('Tokens related to anonymous subscriptions.'),
  ];

  $anonymous_subscriptions['subscriptions_page'] = [
    'name' => t('Subscriptions page'),
    'description' => t('Link to subscriptions page based on message owner, to be used in message entities.'),
    'needs-data' => 'message',
  ];

  return [
    'types' => ['oe_subscriptions_anonymous' => $type],
    'tokens' => ['oe_subscriptions_anonymous' => $anonymous_subscriptions],
  ];
}

/**
 * Implements hook_tokens().
 */
function oe_subscriptions_anonymous_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if (
    $type == 'oe_subscriptions_anonymous' &&
    isset($tokens['subscriptions_page']) &&
    !empty($data['message'])
  ) {
    /** @var \Drupal\message\Entity\Message $message */
    $message = $data['message'];
    $route = 'oe_subscriptions_anonymous.user_subscriptions.request_access';
    $options = [
      'absolute' => TRUE,
    ];

    if ($message->getOwner() !== NULL && $message->getOwner()->isCoupled()) {
      // Adding the URL to the subscriptions page will lead into an 403 for
      // non-logged users, we add a login URL with the subscriptions page
      // destination.
      $route = 'user.login';
      $options += [
        'query' => [
          'destination' => Url::fromRoute('oe_subscriptions.user.subscriptions', [
            'user' => $message->getOwnerId(),
          ])->toString(),
        ],
      ];
    }

    $replacements[$tokens['subscriptions_page']] = Url::fromRoute($route, [], $options)->toString(TRUE);
  }

  return $replacements;
}
