<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Trait;

/**
 * Trait to assert status messages.
 */
trait StatusMessageTrait {

  /**
   * Asserts that the subscription create mail status message is shown.
   */
  protected function assertSubscriptionCreateMailStatusMessage(): void {
    $this->assertHtmlStatusMessage([
      'h4' => 'A confirmation email has been sent to your email address',
      'p' => 'To confirm your subscription, please click on the confirmation link sent to your e-mail address.',
      'strong' => 'please click on the confirmation link',
    ], 'warning');
  }

  /**
   * Asserts that the subscriptions page mail status message is shown.
   */
  protected function assertSubscriptionsPageMailStatusMessage(): void {
    $this->assertHtmlStatusMessage([
      'h4' => 'A confirmation email has been sent to your email address',
    ], 'warning');
  }

  /**
   * Asserts that a status message contains a given tags/content.
   *
   * @param array $html
   *   Associative array of tags and content to check in the message.
   * @param string $type
   *   The type of message.
   */
  protected function assertHtmlStatusMessage(array $html, string $type): void {
    if (empty($html) || array_is_list($html)) {
      throw new \InvalidArgumentException('An associative array of tags and expected their content is expected.');
    }

    $allowed_types = [
      'status' => 'Status message',
      'error' => 'Error message',
      'warning' => 'Warning message',
    ];
    if (!isset($allowed_types[$type])) {
      throw new \InvalidArgumentException(sprintf("Provide an message type, the allowed values are 'status', 'error', 'warning'. The value provided was '%s'.", $type));
    }

    $assert_session = $this->assertSession();
    foreach ($html as $tag => $content) {
      $xpath = $assert_session->buildXPathQuery('//div[@data-drupal-messages]//div[(contains(@aria-label, :aria_label) or contains(@aria-labelledby, :type))]//' . $tag . '[contains(., :content)]', [
        // Value of the 'aria-label' attribute, used in Stark.
        ':aria_label' => $allowed_types[$type],
        // Value of the 'aria-labelledby' attribute, used in Claro and Olivero.
        ':type' => $type,
        ':content' => $content,
      ]);
      $assert_session->elementExists('xpath', $xpath);
    }
  }

}
