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
  if (
    $context['type'] === 'user' &&
    !empty($context['data']['user']) &&
    !empty($context['data']['message']) &&
    isset($context['tokens']['subscriptions-page-url']) &&
    !$context['data']['user']->isCoupled()
  ) {
    $url = Url::fromRoute('oe_subscriptions_anonymous.user_subscriptions.request_access', [], ['absolute' => TRUE])
      ->toString(TRUE);

    $bubbleable_metadata->addCacheableDependency($url);

    $replacements[$context['tokens']['subscriptions-page-url']] = $url->getGeneratedUrl();
  }
}
