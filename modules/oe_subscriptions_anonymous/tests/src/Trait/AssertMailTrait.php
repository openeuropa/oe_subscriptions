<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Trait;

use Drupal\Core\Test\AssertMailTrait as CoreAssertMailTrait;

/**
 * Extends the core trait to test emails.
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

  /**
   * Empties the mail collector.
   */
  protected function resetMailCollector(): void {
    \Drupal::state()->set('system.test_mail_collector', []);
  }

  /**
   * Returns all the URLs that are set as footnote.
   *
   * @param string $text
   *   The text to parse.
   *
   * @return array
   *   An array of URLs extracted, keyed by the corresponding footnote.
   */
  protected function getMailFootNoteUrls(string $text): array {
    preg_match_all('/\[(\d+)\]\s*(https?:\/\/[^\s]+)/', $text, $matches, PREG_SET_ORDER);

    $urls = [];
    foreach ($matches as $match) {
      $urls[$match[1]] = $match[2];
    }

    return $urls;
  }

}
