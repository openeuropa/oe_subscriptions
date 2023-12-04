<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Trait;

use Drupal\Core\Test\AssertMailTrait as CoreAssertMailTrait;

/**
 * Overrides the core trait to avoid cached results.
 */
trait AssertMailTrait {

  use CoreAssertMailTrait {
    getMails as drupalGetMails;
    assertMail as drupalAssertMail;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMails(array $filter = []) {
    // Reset the cache to allow to call the method multiple times.
    \Drupal::state()->resetCache();

    return $this->drupalGetMails($filter);
  }

  /**
   * {@inheritdoc}
   */
  protected function assertMail($name, $value = '', $message = '') {
    // Reset the cache to allow to call the method multiple times.
    \Drupal::state()->resetCache();

    return $this->drupalAssertMail($name, $value, $message);
  }

}
