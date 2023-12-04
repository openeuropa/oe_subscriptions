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
   * Tests the subscription form.
   */
  public function testForm(): void {
    // Create an article content type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Create a flag.
    $link_text = 'Subscribe to this article';
    $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_short' => $link_text,
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    // Create the node.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();
    $this->drupalGet($node->toUrl());
    // Click subscribe link.
    $this->clickLink($link_text);
    $assert_session->addressEquals(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe', [
      'flag' => 'subscribe_article',
      'entity_id' => $node->id(),
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
    $assert_session->addressEquals($node->toUrl()->setAbsolute()->toString());
    $this->assertCount(1, $this->getMails());
    $this->assertMail('to', 'test@test.com');
  }

}
