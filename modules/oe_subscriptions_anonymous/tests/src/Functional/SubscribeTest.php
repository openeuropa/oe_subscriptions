<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Modal form test.
 */
class SubscribeTest extends BrowserTestBase {

  use FlagCreateTrait;
  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'flag',
    'oe_subscriptions',
    'oe_subscriptions_anonymous',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests anonymous subscription process.
   */
  public function testSubscriptionProcess(): void {
    // Create content types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    // Create flags.
    $articles_flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    $pages_flag = $this->createFlagFromArray([
      'id' => 'subscribe_page',
      'entity_type' => 'node',
      'bundles' => ['page'],
    ]);
    // Create nodes.
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
    $page = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();
    $mail_label = 'Your e-mail';
    $terms_label = 'I have read and agree with the data protection terms.';

    // Go to articles subscribe form page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => $articles_flag->id(),
        'entity_id' => $article->id(),
      ]));
    // Test form submit.
    $assert_session->fieldExists($mail_label)->setValue('test1@mail.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail field.
    $this->assertMail('to', 'test1@mail.com');
    // Search URLs in body.
    $mails = $this->getMails();
    $mail = end($mails);
    $confirm_url = $this->firstUrlByText('confirm', $mail['body']);
    $cancel_url = $this->firstUrlByText('cancel', $mail['body']);
    // Confirm subscription.
    $this->drupalGet($confirm_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription confirmed.');
    // Cancel subscription.
    $this->drupalGet($cancel_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription canceled.');

    // Subscribe to a different flag and node.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => $pages_flag->id(),
        'entity_id' => $page->id(),
      ]));
    // Test form submit.
    $assert_session->fieldExists($mail_label)->setValue('test2@mail.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail field.
    $this->assertMail('to', 'test2@mail.com');
    // Search URLs in body.
    $mails = $this->getMails();
    $mail = end($mails);
    $confirm_url = $this->firstUrlByText('confirm', $mail['body']);
    $cancel_url = $this->firstUrlByText('cancel', $mail['body']);
    // Confirm subscription.
    $this->drupalGet($confirm_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription confirmed.');
    // Cancel subscription.
    $this->drupalGet($cancel_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription canceled.');

    // Subscribe and cancel before confirming.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => $pages_flag->id(),
        'entity_id' => $page->id(),
      ]));
    // Test form submit.
    $assert_session->fieldExists($mail_label)->setValue('test3@mail.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail field.
    $this->assertMail('to', 'test3@mail.com');
    // Search URLs in body.
    $mails = $this->getMails();
    $mail = end($mails);
    $cancel_url = $this->firstUrlByText('cancel', $mail['body']);
    $cancel_url = $this->firstUrlByText('cancel', $mail['body']);
    // Cancel subscription.
    $this->drupalGet($cancel_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription canceled.');

    // Try to confirm or cancel unexisting subscriptions.
    $this->drupalGet($confirm_url);
    $assert_session->statusMessageExists('error');
    $this->assertSession()->pageTextContains('The subscription could not be confirmed.');
    $this->drupalGet($cancel_url);
    $assert_session->statusMessageExists('error');
    $this->assertSession()->pageTextContains('The subscription could not be canceled.');

    // Subscribe without confirm, and request subscription again.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => $articles_flag->id(),
        'entity_id' => $article->id(),
      ]));
    // Test form submit.
    $assert_session->fieldExists($mail_label)->setValue('test4@mail.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail field.
    $this->assertMail('to', 'test4@mail.com');
    // Search URLs in body.
    $mails = $this->getMails();
    $first_mail = end($mails);
    $first_confirm_url = $this->firstUrlByText('confirm', $first_mail['body']);
    $first_cancel_url = $this->firstUrlByText('cancel', $first_mail['body']);
    // Visit page again.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => $articles_flag->id(),
        'entity_id' => $article->id(),
      ]));
    // Set values again with same mail.
    $assert_session->fieldExists($mail_label)->setValue('test4@mail.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail field.
    $this->assertMail('to', 'test4@mail.com');
    // Search URLs in body.
    $mails = $this->getMails();
    $second_mail = end($mails);
    $second_confirm_url = $this->firstUrlByText('confirm', $second_mail['body']);
    $second_cancel_url = $this->firstUrlByText('cancel', $second_mail['body']);
    // We try to confirm/cancel with URLs from the oldest mail.
    $this->drupalGet($first_confirm_url);
    $assert_session->statusMessageExists('error');
    $this->assertSession()->pageTextContains('The subscription could not be confirmed.');
    $this->drupalGet($first_cancel_url);
    $assert_session->statusMessageExists('error');
    $this->assertSession()->pageTextContains('The subscription could not be canceled.');
    // Then we confirm and cancel with the latest mail.
    $this->drupalGet($second_confirm_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription confirmed.');
    $this->drupalGet($second_cancel_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription canceled.');

    // Test expired subscription hash.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => $articles_flag->id(),
        'entity_id' => $article->id(),
      ]));
    // Set values again with same mail.
    $assert_session->fieldExists($mail_label)->setValue('test5@mail.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail field.
    $this->assertMail('to', 'test5@mail.com');
    // Search URLs in body.
    $mails = $this->getMails();
    $mail = end($mails);
    $confirm_url = $this->firstUrlByText('confirm', $mail['body']);
    $cancel_url = $this->firstUrlByText('cancel', $mail['body']);
    // Set the changed more than a day ago.
    $this->setSubscriptionChanged('test5@mail.com', $articles_flag, $article->id(), time() - 90000);
    // User can't confirm.
    $this->drupalGet($confirm_url);
    $assert_session->statusMessageExists('error');
    $this->assertSession()->pageTextContains('The confirmation link has expired, request the subscription again please.');
    // But can cancel.
    $this->drupalGet($cancel_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription canceled.');

    // Test expired subscription hash, request again and confirm.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => $articles_flag->id(),
        'entity_id' => $article->id(),
      ]));
    // Set values again with same mail.
    $assert_session->fieldExists($mail_label)->setValue('test6@mail.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail field.
    $this->assertMail('to', 'test6@mail.com');
    // Search URLs in body.
    $mails = $this->getMails();
    $mail = end($mails);
    $confirm_url = $this->firstUrlByText('confirm', $mail['body']);
    $cancel_url = $this->firstUrlByText('cancel', $mail['body']);
    // Set the changed more than a day ago.
    $this->setSubscriptionChanged('test6@mail.com', $articles_flag, $article->id(), time() - 90000);
    // User can't confirm.
    $this->drupalGet($confirm_url);
    $assert_session->statusMessageExists('error');
    $this->assertSession()->pageTextContains('The confirmation link has expired, request the subscription again please.');
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => $articles_flag->id(),
        'entity_id' => $article->id(),
      ]));
    // Set values again with same mail.
    $assert_session->fieldExists($mail_label)->setValue('test6@mail.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail field.
    $this->assertMail('to', 'test6@mail.com');
    // Search URLs in body.
    $mails = $this->getMails();
    $mail = end($mails);
    $confirm_url = $this->firstUrlByText('confirm', $mail['body']);
    $cancel_url = $this->firstUrlByText('cancel', $mail['body']);
    // Confirm.
    $this->drupalGet($confirm_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription confirmed.');
  }

  /**
   * Returns first URL found in a string given a substring.
   *
   * @param string $needle
   *   Substring to contained in URLs.
   * @param string $haystack
   *   String to search URLs.
   *
   * @return string
   *   The fisrt matching URL, empty string otherwise.
   */
  private function firstUrlByText($needle, $haystack): string {
    $link = '';
    preg_match_all("/https?:\/\/[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|))/", $haystack, $urls);
    foreach ($urls[0] as $url) {
      if (str_contains($url, $needle)) {
        $link = $url;
        break;
      }
    }
    return $link;
  }

  /**
   * Sets a subscription changed value.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag used for subscribing.
   * @param string $entity_id
   *   The entity to subscribe to.
   * @param string $changed
   *   The value we want to set as changed.
   *
   * @return void
   *   No return value.
   */
  private function setSubscriptionChanged(string $mail, FlagInterface $flag, string $entity_id, $changed): void {
    $connection = $this->container->get('database');
    // Update changed setting the changed older than a day ago.
    $connection->update('oe_subscriptions_anonymous_subscriptions')
      ->fields([
        'changed' => $changed,
      ])
      ->condition('mail', $mail)
      ->condition('flag_id', $flag->id())
      ->condition('entity_id', $entity_id)
      ->execute();
  }

}
