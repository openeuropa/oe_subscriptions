<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Url;
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

    // Test confirm subscription HTML mail content.
    // Asserts the mail content testing confirmation link.
    $this->drupalGet($article->toUrl());
    $this->clickLink('Subscribe');
    $assert_session->fieldExists('Your e-mail')->setValue('test@test.com');
    $assert_session->fieldExists('I have read and agree with the data protection terms.')->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $this->assertSubscriptionCreateMailStatusMessage();

    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject("Confirm your subscription to {$article->label()}");
    $this->assertConfirmMailHtml(
      [
        'text' => $article->label(),
        'url' => $article->toUrl()->setAbsolute()->toString(),
      ],
      [
        'text' => 'Confirm my subscription',
      ],
      [
        'text' => 'Cancel the subscription request',
      ],
      $mail->getHtmlBody(),
      'confirmed'
    );

    // Asserts the mail content testing cancelation link.
    $this->drupalGet($article->toUrl());
    $this->clickLink('Subscribe');
    $assert_session->fieldExists('Your e-mail')->setValue('test@test.com');
    $assert_session->fieldExists('I have read and agree with the data protection terms.')->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $this->assertSubscriptionCreateMailStatusMessage();

    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject("Confirm your subscription to {$article->label()}");
    $this->assertConfirmMailHtml(
      [
        'text' => $article->label(),
        'url' => $article->toUrl()->setAbsolute()->toString(),
      ],
      [
        'text' => 'Confirm my subscription',
      ],
      [
        'text' => 'Cancel the subscription request',
      ],
      $mail->getHtmlBody(),
      'canceled'
    );

    // Test request access HTML mail content.
    // Asserts the mail content testing subscription link.
    $this->drupalGet('/user/subscriptions');
    $assert_session->fieldExists('Your e-mail')->setValue('test@test.com');
    $assert_session->buttonExists('Submit')->press();
    $this->assertSubscriptionsPageMailStatusMessage();

    $mail = $this->readMail();
    $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $this->assertTo('test@test.com');
    $this->assertSubject("Access your subscriptions page on $site_url");

    $this->assertSubscriptionsMailHtml(
      [
        'text' => $site_url,
        'url' => $site_url,
      ],
      [
        'text' => 'Access my subscriptions page',
      ],
      $mail->getHtmlBody(),
    );

  }

  /**
   * Asserts HTML in body content with given texts.
   *
   * @param array $entity_link
   *   The link to the entity.
   * @param array $confirm_link
   *   The link to confirm the subscription.
   * @param array $cancel_link
   *   The link to cancel the subscription.
   * @param string $mail_body
   *   The mail body.
   * @param string $operation
   *   Either confirmation or cancelation.
   */
  protected function assertConfirmMailHtml(array $entity_link, array $confirm_link, array $cancel_link, string $mail_body, string $operation): void {
    $crawler = new Crawler($mail_body);
    $wrapper = $crawler->filter('.email-sub-type-subscription-create div.clearfix');
    $assert_session = $this->assertSession();
    $urls_index = [
      'confirmed' => 0,
      'canceled' => 1,
    ];

    // Check that the links are in the text.
    $urls = $this->assertLinks([$entity_link, $confirm_link, $cancel_link], $wrapper);

    // Asserts operation link either confirmation or cancelation.
    $this->drupalGet($urls[$urls_index[$operation]]);
    $assert_session->statusMessageContains("Your subscription request has been {$operation}.", 'status');

    // Check break lines.
    $this->assertCount(3, $wrapper->filter('br'));
    $this->assertStringContainsString($entity_link['text'] . '</a>!<br>', $mail_body);
    $this->assertStringContainsString($confirm_link['text'] . '</a><br>', $mail_body);
    $this->assertStringContainsString($cancel_link['text'] . '</a><br>', $mail_body);

    // Check the text.
    $this->assertEquals(
      "Thank you for showing interest in keeping up with the updates for {$entity_link['text']}! " .
      "Click the following link to confirm your subscription: {$confirm_link['text']} " .
      "If you no longer wish to subscribe, click on the link bellow: {$cancel_link['text']} " .
      "If you didn't subscribe to these updates or you're not sure why you received this e-mail, you can delete it. " .
      "You will not be subscribed if you don't click on the confirmation link above.",
      $wrapper->text());
  }

  /**
   * Asserts HTML in body content with given texts.
   *
   * @param array $site_link
   *   The link to the site.
   * @param array $subscriptions_link
   *   The link to the subscriptions page.
   * @param string $mail_body
   *   The mail body.
   */
  protected function assertSubscriptionsMailHtml(array $site_link, array $subscriptions_link, string $mail_body): void {
    $crawler = new Crawler($mail_body);
    $wrapper = $crawler->filter('.email-sub-type-user-subscriptions-access div.clearfix');
    $assert_session = $this->assertSession();

    // Check that the links are in the text.
    [$subscriptions_url] = $this->assertLinks([$site_link, $subscriptions_link], $wrapper);

    // Assert subscription link.
    $this->drupalGet($subscriptions_url);
    $assert_session->titleEquals('Manage your subscriptions | Drupal');
    $assert_session->elementExists('css', 'table.user-subscriptions');

    // Check break lines.
    $this->assertCount(2, $wrapper->filter('br'));
    $this->assertStringContainsString($site_link['text'] . '</a>.<br>', $mail_body);
    $this->assertStringContainsString($subscriptions_link['text'] . '</a><br>', $mail_body);

    // Check the text.
    $this->assertEquals(
      "You are receiving this e-mail because you requested access to your subscriptions page on {$site_link['text']}. " .
      "Click the following link to access your subscriptions page: {$subscriptions_link['text']} " .
      "If you didn't request access to your subscriptions page or you're not sure why you received this e-mail, you can delete it.",
      $wrapper->text());
  }

  /**
   * Asserts links.
   *
   * @param array[] $expected_links
   *   A list links to check.
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The crawler where perform the checks.
   *
   * @return array
   *   The links without URL key value.
   */
  protected function assertLinks(array $expected_links, Crawler $crawler): array {
    $token_urls = [];

    foreach ($expected_links as $expected_link) {
      $link = $crawler->filterXPath("//a[contains(text(),'{$expected_link['text']}')]");
      $this->assertCount(1, $link);
      if (isset($expected_link['url'])) {
        $this->assertEquals($expected_link['url'], $link->attr('href'));
        continue;
      }
      $token_urls[] = $link->attr('href');
    }

    return $token_urls;
  }

}
