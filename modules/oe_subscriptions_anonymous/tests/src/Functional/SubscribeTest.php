<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\AssertMailTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\StatusMessageTrait;

/**
 * Tests the subscribe workflow.
 */
class SubscribeTest extends BrowserTestBase {

  use AssertMailTrait;
  use FlagCreateTrait;
  use StatusMessageTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
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
    $assert_session->addressEquals(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => 'subscribe_article',
      'entity_id' => $article->id(),
    ])->setAbsolute()->toString());

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
    $this->assertSubscriptionCreateMailStatusMessage();
    $assert_session->addressEquals($article->toUrl()->setAbsolute()->toString());

    // Test the e-mail sent.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'test@test.com', $article_flag, $article);

    // Confirm the subscription request.
    $this->drupalGet($mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $assert_session->addressEquals($article->toUrl()->setAbsolute()->toString());
    $account = user_load_by_mail('test@test.com');
    $this->assertNotEmpty($account);
    $this->assertTrue($article_flag->isFlagged($article, $account));

    // The cancel link is now invalid.
    $this->drupalGet($mail_urls[3]);
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');
    $assert_session->addressEquals('/');

    // Subscribe to a different flag and node.
    $this->resetMailCollector();
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => $pages_flag->id(),
      'entity_id' => $page->id(),
    ]));
    $assert_session->fieldExists($mail_label)->setValue('another@example.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $this->assertSubscriptionCreateMailStatusMessage();
    $assert_session->addressEquals($page->toUrl()->setAbsolute()->toString());

    // Test the e-mail sent.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'another@example.com', $pages_flag, $page);

    $this->drupalGet($mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $assert_session->addressEquals($page->toUrl()->setAbsolute()->toString());
    $account = user_load_by_mail('another@example.com');
    $this->assertNotEmpty($account);
    $this->assertTrue($pages_flag->isFlagged($page, $account));

    // The cancel link is now invalid.
    $this->drupalGet($mail_urls[3]);
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');
    $assert_session->addressEquals('/');

    $page_two = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);
    $this->resetMailCollector();
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => $pages_flag->id(),
      'entity_id' => $page_two->id(),
    ]));
    $assert_session->fieldExists($mail_label)->setValue('another@example.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $this->assertSubscriptionCreateMailStatusMessage();
    $assert_session->addressEquals($page_two->toUrl()->setAbsolute()->toString());

    // Test the e-mail sent.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'another@example.com', $pages_flag, $page_two);

    // Use the cancel link first.
    $this->drupalGet($mail_urls[3]);
    $assert_session->statusMessageContains('Your subscription request has been canceled.', 'status');
    $assert_session->addressEquals('/');
    $this->assertFalse($pages_flag->isFlagged($page_two, $account));

    // The confirm link is now invalid.
    $this->drupalGet($mail_urls[2]);
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');
    $assert_session->addressEquals($page_two->toUrl()->setAbsolute()->toString());
  }

  /**
   * Asserts the content of a subscription confirmation mail.
   *
   * @param array $mail_data
   *   The mail data.
   * @param string $email
   *   The e-mail address the mail should be sent to.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag that the mail links should point to.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being flagged.
   *
   * @return array
   *   A list of URLs extracted from the mail.
   */
  protected function assertSubscriptionConfirmationMail(array $mail_data, string $email, FlagInterface $flag, EntityInterface $entity): array {
    $this->assertMail('to', $email);
    $this->assertMail('subject', 'Confirm your subscription to ' . $entity->label());
    $this->assertMailString('body', "{$entity->label()} [1]", 1);
    $this->assertMailString('body', 'Confirm subscription request [2]', 1);
    $this->assertMailString('body', 'Cancel subscription request [3]', 1);

    $mail_urls = $this->getMailFootNoteUrls($mail_data['body']);
    $this->assertCount(3, $mail_urls);
    $this->assertEquals($entity->toUrl()->setAbsolute()->toString(), $mail_urls[1]);
    $url_suffix = sprintf('%s/%s/%s', $flag->id(), $entity->id(), rawurlencode($email));
    $base_confirm_url = $this->getAbsoluteUrl('/subscribe/confirm/' . $url_suffix);
    $this->assertMatchesRegularExpression('@^' . preg_quote($base_confirm_url, '@') . '/.+$@', $mail_urls[2]);
    $base_cancel_url = $this->getAbsoluteUrl('/subscribe/cancel/' . $url_suffix);
    $this->assertMatchesRegularExpression('@^' . preg_quote($base_cancel_url, '@') . '/.+$@', $mail_urls[3]);

    return $mail_urls;
  }

}
