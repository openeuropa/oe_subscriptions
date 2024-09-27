<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Trait;

/**
 * Extends the core trait to test emails.
 */
trait AssertMailTrait {

  /**
   * Gets an array containing all emails sent during this test case.
   *
   * @return array
   *   An array containing email messages captured during the current test.
   */
  protected function getMails(): array {
    // Reset the cache to allow to call the method multiple times.
    \Drupal::state()->resetCache();

    return \Drupal::state()->get('system.test_mail_collector', []);
  }

  /**
   * Asserts a single property or field from the e-mail.
   *
   * @param string $key
   *   The property name (e.g. "to", "subject").
   * @param mixed $value
   *   The expected property value.
   * @param array|null $mail
   *   The mail itself. If left empty, the last collected e-mail will be used.
   */
  protected function assertMailProperty(string $key, $value, ?array $mail = NULL): void {
    if ($mail === NULL) {
      $mails = $this->getMails();
      $mail = end($mails);
    }

    $this->assertArrayHasKey($key, $mail);
    $this->assertEquals($value, $mail[$key]);
  }

  /**
   * Asserts that a mail property contains the specified string.
   *
   * @param string $key
   *   The property name (e.g. "to", "subject").
   * @param string $string
   *   The string to search.
   * @param array|null $mail
   *   The mail itself. If left empty, the last collected e-mail will be used.
   */
  protected function assertMailString(string $key, string $string, ?array $mail = NULL): void {
    if ($mail === NULL) {
      $mails = $this->getMails();
      $mail = end($mails);
    }

    // Normalize whitespace, as we don't know what the mail system might have
    // done. Any run of whitespace becomes a single space.
    $normalized_mail = preg_replace('/\s+/', ' ', $mail[$key]);
    $normalized_string = preg_replace('/\s+/', ' ', $string);
    $this->assertStringContainsString($normalized_string, $normalized_mail);
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
