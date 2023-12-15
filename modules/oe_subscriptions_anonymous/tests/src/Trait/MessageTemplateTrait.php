<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Trait;

/**
 * Trait to assert messages templates.
 */
trait MessageTemplateTrait {

  /**
   * Asserts that the confirm message is present.
   *
   * @return bool
   *   If the message is present in the page.
   */
  public function confirMessageExists(): bool {
    $message = [
      'h4' => 'A confirmation email has been sent to your email address',
      'p' => 'To confirm your subscription, please click on the confirmation link sent to your e-mail address.',
      'strong' => 'please click on the confirmation link',
    ];
    // All elements in the array have to be present.
    $matches = array_filter($message, function ($value, $key) {
      $selector = '//div[@data-drupal-messages]//div[contains(@aria-label, "Warning message") or contains(@aria-labelledby, "warning")]//' . $key . '[contains(.,"' . $value . '")]';
      return $this->assertSession()->elementExists('xpath', $selector);
    }, ARRAY_FILTER_USE_BOTH);

    return empty($matches);
  }

}
