<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Trait;

/**
 * Trait to assert status message.
 */
trait StatusMessageTrait {

  /**
   * Asserts that a status message contains a given tags/content.
   *
   * @param array $html
   *   Associative array of tags and content to check in the message.
   * @param string $type
   *   The type of message.
   */
  private function assertHtmlStatusMessage(array $html, string $type): void {
    if (empty($html) || array_is_list($html)) {
      throw new \InvalidArgumentException(sprintf("Provide an associative array of tags and content with the HTML you want to check, please."));
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
    // All elements in the array have to be present.
    foreach ($html as $key => $value) {
      $selector = $assert_session->buildXPathQuery('//div[@data-drupal-messages]//div[(contains(@aria-label, :aria_label) or contains(@aria-labelledby, :type))]//' . $key . '[contains(., :content)]', [
        // Value of the 'aria-label' attribute, used in Stark.
        ':aria_label' => $allowed_types[$type],
        // Value of the 'aria-labelledby' attribute, used in Claro and Olivero.
        ':type' => $type,
        ':content' => $value,
      ]);
      $assert_session->elementExists('xpath', $selector);
    };
  }

}
