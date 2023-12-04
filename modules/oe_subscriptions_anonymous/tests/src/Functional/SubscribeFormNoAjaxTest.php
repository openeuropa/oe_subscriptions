<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\AssertMailTrait;

/**
 * Tests the subscribe form when it's not rendered in an AJAX context.
 */
class SubscribeFormNoAjaxTest extends BrowserTestBase {

  use AssertMailTrait;
  use FlagCreateTrait;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
  }

  /**
   * Tests the subscription form.
   */
  public function testForm(): void {
    // Create flags.
    $article_flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_short' => 'Subscribe to this article',
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    $pages_flag = $this->createFlagFromArray([
      'id' => 'subscribe_page',
      'entity_type' => 'node',
      'bundles' => ['page'],
    ]);
    // Create some test nodes.
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
    $page = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();
    $this->drupalGet($article->toUrl());
    // Click subscribe link.
    $this->clickLink('Subscribe to this article');
    $assert_session->addressEquals(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe', [
      'flag' => 'subscribe_article',
      'entity_id' => $article->id(),
    ])->setAbsolute()->toString());
    $assert_session->titleEquals(sprintf('Subscribe to %s | Drupal', $node->label()));

    $mail_label = 'Your e-mail';
    $terms_label = 'I have read and agree with the data protection terms.';
    $mail_field = $assert_session->fieldExists($mail_label);
    $terms_field = $assert_session->fieldExists($terms_label);
    // Only one button should be rendered.
    $assert_session->buttonNotExists('No thanks');
    $assert_session->elementsCount('css', '.form-actions input[type="submit"]', 1);

    // Verify that mail and terms field are marked as required.
    $assert_session->buttonExists('Subscribe me')->press();
    // The modal was not closed, and the errors are rendered inside it.
    $assert_session->statusMessageContains("$mail_label field is required.", 'error');
    $assert_session->statusMessageContains("$terms_label field is required.", 'error');
    $this->assertEmpty($this->getMails());

    $mail_field->setValue('test@test.com');
    $terms_field->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageContains('A confirmation e-email has been sent to your e-mail address.', 'status');
    $assert_session->addressEquals($article->toUrl()->setAbsolute()->toString());

    // Test the e-mail sent.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $this->assertMail('to', 'test@test.com');
    $this->assertMail('subject', 'Confirm your subscription to ' . $article->label());
    $this->assertMailString('body', "{$article->label()} [1]", 1);
    $this->assertMailString('body', 'Confirm subscription request [2]', 1);
    $this->assertMailString('body', 'Cancel subscription request [3]', 1);

    $mail_urls = $this->getMailFootNoteUrls($mails[0]['body']);
    $this->assertCount(3, $mail_urls);
    $this->assertEquals($article->toUrl()->setAbsolute()->toString(), $mail_urls[1]);
    $base_confirm_url = $this->getAbsoluteUrl('/subscribe/confirm/subscribe_article/1/test%40test.com/');
    $this->assertMatchesRegularExpression('@^' . preg_quote($base_confirm_url, '@') . '.+$@', $mail_urls[2]);
    $base_cancel_url = $this->getAbsoluteUrl('/subscribe/cancel/subscribe_article/1/test%40test.com/');
    $this->assertMatchesRegularExpression('@^' . preg_quote($base_cancel_url, '@') . '.+$@', $mail_urls[3]);

    // Confirm the subscription request.
    $this->drupalGet($mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $account = user_load_by_mail('test@test.com');
    $this->assertNotEmpty($account);
    $this->assertTrue($article_flag->isFlagged($article, $account));

    // The cancel link is now invalid.
    $this->drupalGet($mail_urls[3]);
    $assert_session->statusMessageContains('You have tried to use a cancel link that has been used or is no longer valid. Please request a new link.', 'error');

    // Subscribe to a different flag and node.
    $this->resetMailCollector();
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe', [
      'flag' => $pages_flag->id(),
      'entity_id' => $page->id(),
    ]));
    $assert_session->fieldExists($mail_label)->setValue('another@example.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $assert_session->statusMessageContains('A confirmation e-email has been sent to your e-mail address.', 'status');

    // Test the e-mail sent.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $this->assertMail('to', 'another@example.com');
    $this->assertMail('subject', 'Confirm your subscription to ' . $page->label());
    $this->assertMailString('body', "{$page->label()} [1]", 1);
    $this->assertMailString('body', 'Confirm subscription request [2]', 1);
    $this->assertMailString('body', 'Cancel subscription request [3]', 1);

    $mail_urls = $this->getMailFootNoteUrls($mails[0]['body']);
    $this->assertCount(3, $mail_urls);
    $this->assertEquals($page->toUrl()->setAbsolute()->toString(), $mail_urls[1]);
    $base_confirm_url = $this->getAbsoluteUrl('/subscribe/confirm/subscribe_page/2/another%40example.com/');
    $this->assertMatchesRegularExpression('@^' . preg_quote($base_confirm_url, '@') . '.+$@', $mail_urls[2]);
    $base_cancel_url = $this->getAbsoluteUrl('/subscribe/cancel/subscribe_page/2/another%40example.com/');
    $this->assertMatchesRegularExpression('@^' . preg_quote($base_cancel_url, '@') . '.+$@', $mail_urls[3]);

    $this->drupalGet($mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $account = user_load_by_mail('another@example.com');
    $this->assertNotEmpty($account);
    $this->assertTrue($pages_flag->isFlagged($page, $account));
  }

}
