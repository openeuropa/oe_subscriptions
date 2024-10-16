<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\StatusMessageTrait;
use Drupal\symfony_mailer_test\MailerTestTrait;
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

    // Create a user account, to set up an email conflict.
    $this->createUser(values: ['mail' => 'conflict@example.com']);
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

    $article_url = $this->article->toUrl()->setAbsolute()->toString();
    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'anothertest@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

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

    // We need to test that the variables for the confirm and cancel URLs work
    // correctly. We can do this only one at the time, as clicking on one
    // invalidates the other.
    $this->drupalGet($spans->eq(2)->html());
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');

    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'secondtest@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

    $mail = $this->readMail();
    $this->assertTo('secondtest@test.com');
    $crawler = new Crawler($mail->getHtmlBody());
    $spans = $crawler->filter('.email-sub-type-subscription-create div.clearfix *');
    $this->drupalGet($spans->eq(3)->html());
    $assert_session->statusMessageContains('Your subscription request has been canceled.', 'status');

    // Override the 'registered_user_email_notice' email template.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/system/mailer/policy/oe_subscriptions_anonymous.registered_user_email_notice');
    $assert_session->fieldExists('edit-config-email-subject-value')->setValue('Overridden subject for email taken');
    $assert_session->fieldExists('edit-config-email-body-content-value')->setValue(<<<BODY
<span>{{ entity_label }}</span>
<span>{{ entity_url }}</span>
BODY);
    $assert_session->buttonExists('Save')->press();
    $this->drupalLogout();

    // Visit the article, and subscribe with a conflicting email address.
    $this->requestSubscriptionForArticle('conflict@example.com');

    // Receive the failure email.
    $this->readMail();
    $this->assertTo('conflict@example.com');
    $article_label = $this->article->label();
    $this->assertSubject('Overridden subject for email taken');
    $article_url = $this->article->toUrl()->setAbsolute()->toString();
    $this->assertSame(
      <<<BODY
<span>$article_label</span>
<span>$article_url</span>
BODY,
      $this->getMailBodyWithoutWrapper('registered_user_email_notice'),
    );

    // Override the templates for the subscriptions access mail.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/system/mailer/policy/oe_subscriptions_anonymous.user_subscriptions_access');
    $assert_session->fieldExists('edit-config-email-subject-value')->setValue('Overridden subject for access');
    $assert_session->fieldExists('edit-config-email-body-content-value')->setValue(<<<BODY
<span>{{ subscriptions_page_url }}</span>
BODY);
    $assert_session->buttonExists('Save')->press();
    $this->drupalLogout();

    // Test the e-mail content.
    $this->drupalGet('user/subscriptions');
    $this->submitForm(['Your e-mail' => 'anothertest@test.com'], 'Submit');
    $this->assertSubscriptionsPageMailStatusMessage();
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

    $this->drupalGet($spans->eq(0)->html());
    $assert_session->elementExists('xpath', $assert_session->buildXPathQuery('//a[@href=:href][.=:text]', [
      ':href' => $this->article->toUrl()->toString(),
      ':text' => $this->article->label(),
    ]));
  }

  /**
   * Tests the content of the HTML e-mails shipped as default.
   */
  protected function doTestDefaultHtmlEmailContents(): void {
    // Test confirm subscription HTML mail content.
    // Asserts the mail content testing confirmation link.
    $article_label = $this->article->label();
    $article_url = $this->article->toUrl()->setAbsolute()->toString();
    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'test@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject("Confirm your subscription to $article_label");
    $this->assertConfirmMailHtml($this->article, $mail->getHtmlBody(), 'confirmed');

    // Asserts the mail content testing cancellation link.
    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'test@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject("Confirm your subscription to $article_label");
    $this->assertConfirmMailHtml($this->article, $mail->getHtmlBody(), 'canceled');

    // Visit the article, and subscribe with a conflicting email address.
    $this->requestSubscriptionForArticle('conflict@example.com');

    // Receive the failure email.
    $this->readMail();
    $this->assertTo('conflict@example.com');
    $this->assertSubject("Please log in to subscribe to $article_label");
    $article_url = $this->article->toUrl()->setAbsolute()->toString();
    $this->assertSame(
      <<<BODY
<p>Thank you for showing interest in keeping up with the updates for <a href="$article_url">$article_label</a>!</p>
<p>The email address you were using when trying to subscribe is already associated with a regular account on this website.</p>
<p>If you still want to subscribe to the updates made to the content, you can log in to the website, using your existing account, and then subscribe as a regular user.</p>
<p>If you do not want to subscribe or are unsure why you received this email, you can ignore this message.</p>
BODY,
      $this->getMailBodyWithoutWrapper('registered_user_email_notice'),
    );

    // Test request access HTML mail content.
    // Asserts the mail content testing subscription link.
    $this->drupalGet('/user/subscriptions');
    $this->submitForm(['Your e-mail' => 'test@test.com'], 'Submit');
    $this->assertSubscriptionsPageMailStatusMessage();

    $mail = $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject('Access your subscriptions page on ' . $this->getSiteUrlBrief());
    $this->assertSubscriptionsMailHtml($mail->getHtmlBody());
  }

  /**
   * Requests a subscription to the article.
   *
   * @param string $email
   *   The email address to use in the subscribe form.
   */
  protected function requestSubscriptionForArticle(string $email): void {
    $article_url = $this->article->toUrl()->setAbsolute()->toString();

    // Visit the article, and submit a subscribe request.
    $this->drupalGet($article_url);
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => $email,
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();
  }

  /**
   * Asserts HTML in body for Confirm subscription mail.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the user is subscribing to.
   * @param string $mail_body
   *   The mail body.
   * @param string $operation
   *   The operation test to do.
   */
  protected function assertConfirmMailHtml(EntityInterface $entity, string $mail_body, string $operation): void {
    $crawler = new Crawler($mail_body);
    $wrapper = $crawler->filter('.email-sub-type-subscription-create div.clearfix');
    $assert_session = $this->assertSession();
    $urls_index = [
      'confirmed' => 0,
      'canceled' => 1,
    ];

    // Check that the links are in the text.
    $urls = $this->assertLinks([
      [
        'text' => $entity->label(),
        'url' => $entity->toUrl()->setAbsolute()->toString(),
      ],
      [
        'text' => 'Confirm my subscription',
      ],
      [
        'text' => 'Cancel the subscription request',
      ],
    ], $wrapper);

    // Asserts operation link confirmation or cancellation.
    $this->drupalGet($urls[$urls_index[$operation]]);
    $assert_session->statusMessageContains("Your subscription request has been {$operation}.", 'status');

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
  }

  /**
   * Gets the mail body without the wrapper divs.
   *
   * Also asserts the CSS class in the outer wrapper, that derives from the
   * mail key and module name.
   *
   * @param string $mail_id
   *   Expected mail id/key within oe_subscriptions_anonymous.
   *
   * @return string
   *   Trimmed mail body content without wrapper divs.
   */
  protected function getMailBodyWithoutWrapper(string $mail_id): string {
    $crawler = new Crawler($this->email->getHtmlBody());
    $expected_class = 'email-sub-type-' . str_replace('_', '-', $mail_id);
    $content_div = $crawler->filter("body > div.email-type-oe-subscriptions-anonymous.$expected_class table div.clearfix");
    $this->assertCount(1, $content_div, sprintf(
      // Show the actual email body, to make it easier to see what went wrong.
      "Expected an email body with '%s' mail key. Found:\n%s",
      $mail_id,
      $this->email->getHtmlBody(),
    ));
    return trim($content_div->html());
  }

  /**
   * Asserts HTML in body for Subscriptions page mail.
   *
   * @param string $mail_body
   *   The mail body.
   */
  protected function assertSubscriptionsMailHtml(string $mail_body): void {
    // Check that the links are in the text.
    $crawler = new Crawler($mail_body);
    $wrapper = $crawler->filter('.email-sub-type-user-subscriptions-access div.clearfix');
    $links = $wrapper->filterXPath("//a");
    $this->assertCount(1, $links);
    $this->assertEquals('Access my subscriptions page', $links->eq(0)->text());

    // Assert subscription link.
    $this->drupalGet($links->eq(0)->attr('href'));
    $assert_session = $this->assertSession();
    $assert_session->titleEquals('Manage your subscriptions | Drupal');
    $assert_session->elementExists('css', 'table.user-subscriptions');

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
  }

  /**
   * Asserts links returning those without URL key value.
   *
   * @param array[] $expected_links
   *   A list links to check.
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   The crawler where perform the checks.
   *
   * @return array
   *   The text only urls.
   */
  protected function assertLinks(array $expected_links, Crawler $crawler): array {
    $text_only_urls = [];

    foreach ($expected_links as $expected_link) {
      $link = $crawler->filterXPath("//a[contains(text(),'{$expected_link['text']}')]");
      $this->assertCount(1, $link);
      if (isset($expected_link['url'])) {
        $this->assertEquals($expected_link['url'], $link->attr('href'));
        continue;
      }
      $text_only_urls[] = $link->attr('href');
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
