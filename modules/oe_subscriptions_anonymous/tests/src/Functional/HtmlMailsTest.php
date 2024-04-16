<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Url;
use Drupal\symfony_mailer\Email;
use Drupal\symfony_mailer_test\MailerTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\StatusMessageTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the HTML in mails.
 */
class HtmlMailsTest extends BrowserTestBase {

  use FlagCreateTrait;
  use StatusMessageTrait;
  use MailerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'oe_subscriptions_anonymous',
    'symfony_mailer_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the mails.
   *
   * Mail keys:
   *  - subscription_create.
   *  - user_subscriptions_access.
   */
  public function testMails(): void {
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_short' => 'Subscribe',
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
    $assert_session = $this->assertSession();

    // Test confirm subcription HTML mail content.
    // Subscribe to an article.
    $this->drupalGet($article->toUrl());
    $this->clickLink('Subscribe');
    $assert_session->fieldExists('Your e-mail')->setValue('test@test.com');
    $assert_session->fieldExists('I have read and agree with the data protection terms.')->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $this->assertSubscriptionCreateMailStatusMessage();

    // Check the subscription mail content.
    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject("Confirm your subscription to {$article->label()}");
    $this->assertMailBreakLines(3, $mail);
    $this->assertMailContent([
      "Thank you for showing interest in keeping up with the updates for {$article->label()}!",
      "Click the following link to confirm your subscription: Confirm my subscription",
      "If you no longer wish to subscribe, click on the link bellow: Cancel the subscription request",
      "If you didn't subscribe to these updates or you're not sure why you received this e-mail, you can delete it. You will not be subscribed if you don't click on the confirmation link above.",
    ], $mail);

    // Check link to entity using entity label.
    $links = $this->getMailLinks($mail);
    $this->assertEquals($article->label(), $links[0]->textContent);
    $this->drupalGet($links[0]->getAttribute('href'));
    $assert_session->addressEquals($article->toUrl());

    // Check confirmation link.
    $this->assertEquals('Confirm my subscription', $links[1]->textContent);
    $this->drupalGet($links[1]->getAttribute('href'));
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $assert_session->addressEquals($article->toUrl());

    // Check cancel link.
    $this->drupalGet($article->toUrl());
    $this->clickLink('Subscribe');
    $assert_session->fieldExists('Your e-mail')->setValue('test_two@test.com');
    $assert_session->fieldExists('I have read and agree with the data protection terms.')->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $this->assertSubscriptionCreateMailStatusMessage();
    $mail = $this->readMail();
    $this->assertTo('test_two@test.com');
    $links = $this->getMailLinks($mail);
    $this->assertEquals('Cancel the subscription request', $links[2]->textContent);
    $this->drupalGet($links[2]->getAttribute('href'));
    $assert_session->statusMessageContains('Your subscription request has been canceled.', 'status');
    $assert_session->addressEquals('/');

    // Test request access HTML mail content.
    // Request access to subscriptions page.
    $this->drupalGet('/user/subscriptions');
    $assert_session->fieldExists('Your e-mail')->setValue('test@test.com');
    $assert_session->buttonExists('Submit')->press();
    $this->assertSubscriptionsPageMailStatusMessage();

    // Check subs mail content.
    $mail = $this->readMail();
    $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $this->assertTo('test@test.com');
    $this->assertSubject("Access your subscriptions page on $site_url");
    $this->assertMailBreakLines(2, $mail);
    $this->assertMailContent([
      "You are receiving this e-mail because you requested access to your subscriptions page on $site_url",
      "Click the following link to access your subscriptions page: Access my subscriptions page",
      "If you didn't request access to your subscriptions page or you're not sure why you received this e-mail, you can delete it.",
    ], $mail);

    // Check subscriptions page link.
    $links = $this->getMailLinks($mail);
    $this->assertEquals('Access my subscriptions page', $links[0]->textContent);
    $this->drupalGet($links[0]->getAttribute('href'));
    $assert_session->titleEquals('Manage your subscriptions | Drupal');
    $assert_session->elementExists('css', 'table.user-subscriptions');
  }

  /**
   * Retrieves the DOM elements from existing links in a mail.
   *
   * @param \Drupal\symfony_mailer\Email $mail
   *   The mail.
   *
   * @return \DOMElement[]
   *   An array of links as DOM elements.
   */
  protected function getMailLinks(Email $mail): array {
    $crawler = new Crawler($mail->getHtmlBody());
    return array_map(static fn($link) => $link->getNode(), $crawler->filter('a')->links());
  }

  /**
   * Asserts HTML in body content with given texts.
   *
   * @param string[] $expected_texts
   *   A list of expected text content to search in the mail body.
   * @param \Drupal\symfony_mailer\Email $mail
   *   The mail.
   */
  protected function assertMailContent(array $expected_texts, Email $mail): void {
    $crawler = new Crawler($mail->getHtmlBody());
    foreach ($expected_texts as $expected) {
      $this->assertStringContainsString($expected, $crawler->text());
    }
  }

  /**
   * Asserts a number of break lines in a mail.
   *
   * @param int $expected
   *   The number of expected break lines.
   * @param \Drupal\symfony_mailer\Email $mail
   *   The mail.
   */
  protected function assertMailBreakLines(int $expected, Email $mail): void {
    $crawler = new Crawler($mail->getHtmlBody());
    $this->assertCount($expected, $crawler->filter('br'));
  }

}
