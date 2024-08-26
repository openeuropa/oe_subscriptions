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
   * Gets an object of the given mail template key.
   */
  public static function getMailTemplate(string $key): MailTemplateInterface {
    return \Drupal::classResolver(match ($key) {
      'subscription_create' => SubscriptionCreate::class,
      'registered_user_email_notice' => EmailTaken::class,
      'user_subscriptions_access' => UserSubscriptionsAccess::class,
    });
  }

}
