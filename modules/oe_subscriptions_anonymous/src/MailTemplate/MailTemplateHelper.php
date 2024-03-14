<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

/**
 * Helper methods to support mail templates use.
 *
 * @internal
 */
final class MailTemplateHelper {

  /**
   * Gets the associated class to a given key.
   *
   * @param string $key
   *   The mail key.
   *
   * @return string
   *   The associated class.
   */
  public static function getKeyClass(string $key): string {
    return match ($key) {
      'subscription_create' => SubscriptionCreate::class,
      'user_subscriptions_access' => UserSubscriptionsAccess::class,
    };
  }

  /**
   * Instantiates an object of the given mail template key.
   */
  public static function getMailTemplate(string $key): MailTemplateInterface {
    return \Drupal::classResolver(self::getKeyClass($key));
  }

}
