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
function oe_subscriptions_anonymous_tokens_alter(array &$replacements, array $context, BubbleableMetadata $bubbleable_metadata): void {
  // Alter the tokens URL to subscriptions page for anonymous users.
  if (
    $context['type'] === 'user' &&
    !empty($context['data']['user']) &&
    !$context['data']['user']->isCoupled()
  ) {
    $url = Url::fromRoute('oe_subscriptions_anonymous.user_subscriptions.request_access');

    if (isset($context['tokens']['subscriptions-page'])) {
      $result = $url->setAbsolute()->toString(TRUE);
      $bubbleable_metadata->addCacheableDependency($result);
      $replacements[$context['tokens']['subscriptions-page']] = $result->getGeneratedUrl();
    }

    // Chained token relationships.
    if (($url_tokens = \Drupal::token()->findWithPrefix($context['tokens'], 'subscriptions-page'))) {
      $anonymous_replacements = \Drupal::token()->generate('url', $url_tokens, ['url' => $url], $context['options'], $bubbleable_metadata);
      $replacements = array_merge($replacements, $anonymous_replacements);
    }
  }
}
