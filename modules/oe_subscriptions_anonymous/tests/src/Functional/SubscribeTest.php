<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
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
    'oe_subscriptions_anonymous',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests subscription process.
   */
  public function testSubscriptionProcess(): void {
    // Create an article content type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Create a flag.
    $link_text = 'Subscribe to this article';
    $flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_short' => $link_text,
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    // Create the node.
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();
    // Go to subscribe form page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.anonymous_subscribe',
      [
        'flag' => 'subscribe_article',
        'entity_id' => $article->id(),
      ]));
    // Assert the fields of the form.
    $mail_label = 'Your e-mail';
    $terms_label = 'I have read and agree with the data protection terms.';
    $mail_field = $assert_session->fieldExists($mail_label);
    $terms_field = $assert_session->fieldExists($terms_label);
    $subscribe_button = $assert_session->buttonExists('Subscribe me');
    $cancel_button = $assert_session->buttonExists('No thanks');
    // Test submit.
    $email = 'test@test.com';
    $mail_field->setValue($email);
    $terms_field->check();
    $subscribe_button->press();
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('A confirmation e-email has been sent to your e-mail address.');
    // Assert mail fields.
    $this->assertMail('to', $email);
    // We can't use assertMailString() or assertMail() to check links,
    // so we get body and manually get links.
    $mail = $this->getMails()[0];
    // Search urls in body.
    preg_match_all("/https?:\/\/[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|))/", $mail['body'], $urls);
    foreach ($urls[0] as $url) {
      if (str_contains($url, 'confirm')) {
        $confirm_url = $url;
      }
      if (str_contains($url, 'cancel')) {
        $cancel_url = $url;
      }
    }
    // Confirm subscription.
    $this->drupalGet($confirm_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription confirmed.');
    // Cancel subscription.
    $this->drupalGet($cancel_url);
    $assert_session->statusMessageExists('status');
    $this->assertSession()->pageTextContains('Subscription canceled.');

  }

}
