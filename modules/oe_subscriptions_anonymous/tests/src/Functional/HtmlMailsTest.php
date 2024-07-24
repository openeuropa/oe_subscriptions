<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\symfony_mailer_test\MailerTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\StatusMessageTrait;
use Drupal\user\Entity\Role;
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
   * Node to subscribe.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $article;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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

    $this->article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
  }

  /**
   * Tests that mails are sent with HTML when a compatible mailer is installed.
   */
  public function testHtmlMails(): void {
    $this->doTestDefaultHtmlEmailContents();
  }

  /**
   * Tests variables and overrides for Symfony Mailer module.
   */
  public function testSymfonyMailerOverrides(): void {
    // Enable the overrides for this module.
    \Drupal::service('symfony_mailer.override_manager')->action('oe_subscriptions_anonymous', 'enable');

    // The overrides output by default the same exact e-mail content.
    $this->doTestDefaultHtmlEmailContents();

    // Override the mail templates to test the exposed variables.
    $admin_user = $this->drupalCreateUser([
      'administer mailer',
      'use text format email_html',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/system/mailer/policy/oe_subscriptions_anonymous.subscription_create');
    $assert_session = $this->assertSession();
    $assert_session->fieldExists('edit-config-email-subject-value')->setValue('Overridden subject for create');
    // Set the body to output all the available variables.
    $assert_session->fieldExists('edit-config-email-body-content-value')->setValue(<<<BODY
<span>{{ entity_label }}</span>
<span>{{ entity_url }}</span>
<span>{{ confirm_url }}</span>
<span>{{ cancel_url }}</span>
BODY);
    $assert_session->buttonExists('Save')->press();
    $this->drupalLogout();

    // Visit the article, and submit a subscribe request.
    $article_url = $this->article->toUrl()->setAbsolute()->toString();
    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'anothertest@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

    // Receive the subscribe confirm email.
    // The email content now follows the overridden template.
    $mail = $this->readMail();
    $this->assertTo('anothertest@test.com');
    $this->assertSubject('Overridden subject for create');
    $crawler = new Crawler($mail->getHtmlBody());
    // The HTML should consist of 4 <span> tags.
    $spans = $crawler->filter('.email-sub-type-subscription-create div.clearfix *');
    $this->assertCount(4, $spans);
    $this->assertEquals(['span'], array_unique(array_map(static fn ($tag) => $tag->nodeName, iterator_to_array($spans->getIterator()))));
    $this->assertEquals($this->article->label(), $spans->eq(0)->html());
    $this->assertEquals($article_url, $spans->eq(1)->html());

    // Visit the confirm url from the email.
    // This verifies that the confirm url still works with the overridden email
    // template.
    // Doing this invalidates the cancel url.
    $this->drupalGet($spans->eq(2)->html());
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');

    // Visit the article, and submit a subscribe request, again.
    // This is needed to get a fresh cancel url.
    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'secondtest@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

    // Receive the confirm email.
    $mail = $this->readMail();
    $this->assertTo('secondtest@test.com');
    $crawler = new Crawler($mail->getHtmlBody());
    $spans = $crawler->filter('.email-sub-type-subscription-create div.clearfix *');

    // Visit the cancel url from the email.
    $this->drupalGet($spans->eq(3)->html());
    $assert_session->statusMessageContains('Your subscription request has been canceled.', 'status');

    // Override the templates for the subscriptions access mail.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/system/mailer/policy/oe_subscriptions_anonymous.user_subscriptions_access');
    $assert_session->fieldExists('edit-config-email-subject-value')->setValue('Overridden subject for access');
    $assert_session->fieldExists('edit-config-email-body-content-value')->setValue(<<<BODY
<span>{{ subscriptions_page_url }}</span>
BODY);
    $assert_session->buttonExists('Save')->press();
    $this->drupalLogout();

    // Request access to the manage subscriptions page.
    $this->drupalGet('user/subscriptions');
    $this->submitForm(['Your e-mail' => 'anothertest@test.com'], 'Submit');
    $this->assertSubscriptionsPageMailStatusMessage();

    // Receive the manage subscriptions access email.
    // The email content now follows the overridden template.
    $mail = $this->readMail();
    $this->assertTo('anothertest@test.com');
    $this->assertSubject('Overridden subject for access');
    $crawler = new Crawler($mail->getHtmlBody());
    // The HTML should consist of 1 <span> tag.
    $spans = $crawler->filter('.email-sub-type-user-subscriptions-access div.clearfix *');
    $this->assertCount(1, $spans);
    $this->assertEquals('span', $spans->eq(0)->nodeName());

    Role::load('anonymous_subscriber')
      ->grantPermission('access content')
      ->save();

    // Visit the manage subscriptions access link from the email.
    $this->drupalGet($spans->eq(0)->html());
    // The manage subscriptions page contains a link to the article.
    $assert_session->elementExists('xpath', $assert_session->buildXPathQuery('//a[@href=:href][.=:text]', [
      ':href' => $this->article->toUrl()->toString(),
      ':text' => $this->article->label(),
    ]));
  }

  /**
   * Tests the content of the HTML e-mails shipped as default.
   */
  protected function doTestDefaultHtmlEmailContents(): void {
    $article_label = $this->article->label();
    $article_url = $this->article->toUrl()->setAbsolute()->toString();

    // Visit the article, and submit a subscribe request.
    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'test@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

    // Receive the confirm email, and visit the confirm link.
    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject("Confirm your subscription to $article_label");
    $urls = $this->assertConfirmMailHtml($this->article, $mail->getHtmlBody());
    $this->visitConfirmUrl($urls['confirm']);

    // Visit the article, and submit a subscribe request, again.
    // This is needed to get a fresh cancel url.
    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'test@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

    // Receive the confirm email, and visit the cancel link.
    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject("Confirm your subscription to $article_label");
    $urls = $this->assertConfirmMailHtml($this->article, $mail->getHtmlBody());
    $this->visitCancelUrl($urls['cancel']);

    // Visit the manage subscriptions url.
    // Access to this page requires email confirmation.
    $this->drupalGet('/user/subscriptions');
    $this->submitForm(['Your e-mail' => 'test@test.com'], 'Submit');
    $this->assertSubscriptionsPageMailStatusMessage();

    // Receive the email that provides access to the manage subscriptions page.
    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject('Access your subscriptions page on ' . $this->getSiteUrlBrief());
    $url = $this->assertSubscriptionsMailHtml($mail->getHtmlBody());

    // Visit the url from the access email.
    // The manage subscriptions page shows up.
    $this->drupalGet($url);
    $assert_session = $this->assertSession();
    $assert_session->titleEquals('Manage your subscriptions | Drupal');
    $assert_session->elementExists('css', 'table.user-subscriptions');
  }

  /**
   * Asserts the confirm email contents, and returns the link urls.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the user is subscribing to.
   * @param string $mail_body
   *   The mail body.
   *
   * @return array{confirm: string, cancel: string}
   *   The confirm url and cancel url found in the email body.
   */
  protected function assertConfirmMailHtml(EntityInterface $entity, string $mail_body): array {
    $crawler = new Crawler($mail_body);
    $wrapper = $crawler->filter('.email-sub-type-subscription-create div.clearfix');

    // Check that the links are in the text.
    $urls = $this->assertLinks([
      [
        'text' => $entity->label(),
        'url' => $entity->toUrl()->setAbsolute()->toString(),
      ],
      'confirm' => [
        'text' => 'Confirm my subscription',
      ],
      'cancel' => [
        'text' => 'Cancel the subscription request',
      ],
    ], $wrapper);

    // Check break lines.
    $br = $wrapper->filter('br');
    $this->assertCount(3, $br);
    $this->assertStringContainsString($entity->label() . '</a>!' . $br->html(), $mail_body);
    $this->assertStringContainsString('Confirm my subscription</a>' . $br->html(), $mail_body);
    $this->assertStringContainsString('Cancel the subscription request</a>' . $br->html(), $mail_body);

    // Check the text.
    $this->assertEquals(
      "Thank you for showing interest in keeping up with the updates for {$entity->label()}! " .
      "Click the following link to confirm your subscription: Confirm my subscription. " .
      "If you no longer wish to subscribe, click on the link below: Cancel the subscription request. " .
      "If you didn't subscribe to these updates or you're not sure why you received this e-mail, you can delete it. " .
      "You will not be subscribed if you don't click on the confirmation link above.",
      $wrapper->text());

    return $urls;
  }

  /**
   * Visits the confirm url from the confirm email, and asserts success.
   *
   * @param string $url
   *   Confirm url.
   */
  protected function visitConfirmUrl(string $url): void {
    $assert_session = $this->assertSession();
    // Visit the confirm or cancel link from the email.
    $this->drupalGet($url);
    $assert_session->statusMessageContains("Your subscription request has been confirmed.", 'status');
  }

  /**
   * Visits the cancel url, and asserts success.
   *
   * @param string $url
   *   Cancel url.
   */
  protected function visitCancelUrl(string $url): void {
    $assert_session = $this->assertSession();
    // Visit the confirm or cancel link from the email.
    $this->drupalGet($url);
    $assert_session->statusMessageContains("Your subscription request has been canceled.", 'status');
  }

  /**
   * Asserts HTML in body for Subscriptions page mail.
   *
   * @param string $mail_body
   *   The mail body.
   *
   * @return string
   *   The url that provides access to the manage subscriptions page.
   */
  protected function assertSubscriptionsMailHtml(string $mail_body): string {
    // Check that the links are in the text.
    $crawler = new Crawler($mail_body);
    $wrapper = $crawler->filter('.email-sub-type-user-subscriptions-access div.clearfix');
    $links = $wrapper->filterXPath("//a");
    $this->assertCount(1, $links);
    $this->assertEquals('Access my subscriptions page', $links->eq(0)->text());

    // Check break lines.
    $br = $wrapper->filter('br');
    $this->assertCount(2, $br);
    $this->assertStringContainsString('Access my subscriptions page</a>.' . $br->html(), $mail_body);

    // Check the text.
    $this->assertEquals(
      "You are receiving this e-mail because you requested access to your subscriptions page on {$this->getSiteUrlBrief()}. " .
      "Click the following link to access your subscriptions page: Access my subscriptions page. " .
      "If you didn't request access to your subscriptions page or you're not sure why you received this e-mail, you can delete it.",
      $wrapper->text());

    return $links->eq(0)->attr('href');
  }

  /**
   * Asserts links returning those without URL key value.
   *
   * @param array<array{text: string, url?: string}> $expected_links
   *   Expected link texts and urls.
   *   The expected url is optional.
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The crawler where perform the checks.
   *
   * @return string[]
   *   Actual urls, for which no expected url was provided.
   *   The array keys are the same as in $expected_links.
   */
  protected function assertLinks(array $expected_links, Crawler $crawler): array {
    $text_only_urls = [];

    foreach ($expected_links as $key => $expected_link) {
      $link = $crawler->filterXPath("//a[contains(text(),'{$expected_link['text']}')]");
      $this->assertCount(1, $link);
      if (isset($expected_link['url'])) {
        $this->assertEquals($expected_link['url'], $link->attr('href'));
      }
      $text_only_urls[$key] = $link->attr('href');
    }

    return $text_only_urls;
  }

  /**
   * Returns the site URL without protocol, like the [site:url-brief] token.
   *
   * @return string
   *   The brief site URL.
   */
  protected function getSiteUrlBrief(): string {
    $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

    return preg_replace(['!^https?://!', '!/$!'], '', $site_url);
  }

}
