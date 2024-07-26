<?php

/**
 * @file
 * Token callbacks for the Anonymous subscriptions module.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;

/**
 * Implements hook_tokens_alter().
 */
function oe_subscriptions_anonymous_tokens_alter(array &$replacements, array $context, BubbleableMetadata $bubbleable_metadata) {
  // Alter the token URL to subscriptions page for anonymous users.
  if (isset($context['tokens']['subscriptions-page-url'])) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $context['data']['user'];

    if (!$user->isCoupled()) {
      $url = Url::fromRoute('oe_subscriptions_anonymous.user_subscriptions.request_access', [], ['absolute' => TRUE]);
      $replacements[$context['tokens']['subscriptions-page-url']] = $url->toString();
    }
  }
}
