<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\decoupled_auth\DecoupledAuthUserInterface;
use Drupal\flag\FlagInterface;
use Drupal\oe_subscriptions_anonymous\SettingsFormAlter;
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

    // Visit the article node, and click subscribe.
    $this->drupalGet($article->toUrl());
    $this->clickLink('Subscribe to this article');

    // Without javascript, the subscribe form opens in a new page.
    // With javascript, it would open in a modal.
    $assert_session->addressEquals(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => $article_flag->id(),
      'entity_id' => $article->id(),
    ])->setAbsolute()->toString());

    $mail_label = 'Your e-mail';
    $terms_label = 'I have read and agree with the data protection terms.';
    $mail_field = $assert_session->fieldExists($mail_label);
    $terms_field = $assert_session->fieldExists($terms_label);
    // Only one button should be rendered.
    $assert_session->buttonNotExists('No thanks');
    $assert_session->elementsCount('css', '.form-actions input[type="submit"]', 1);

    // Submit the subscribe form with empty required fields mail and terms.
    $assert_session->buttonExists('Subscribe me')->press();
    // The form fails validation.
    // The modal was not closed, and the errors are rendered inside it.
    $assert_session->statusMessageContains("$mail_label field is required.", 'error');
    $assert_session->statusMessageContains("$terms_label field is required.", 'error');
    $this->assertEmpty($this->getMails());

    // Fill the required fields, and submit again.
    $mail_field->setValue('test@test.com');
    $terms_field->check();
    $assert_session->buttonExists('Subscribe me')->press();

    // The user is redirected to the node page.
    // A status message contains instructions about the confirm email.
    $this->assertSubscriptionCreateMailStatusMessage();
    $assert_session->addressEquals($article->toUrl()->setAbsolute()->toString());

    // Receive the subscription confirm email.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'test@test.com', $article_flag, $article);

    // Visit the confirm link from the email.
    $this->drupalGet($mail_urls[2]);
    // The link opens the node page with a success message.
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $assert_session->addressEquals($article->toUrl()->setAbsolute()->toString());

    // An account was created for the email address.
    // The account is subscribed to the article.
    $account = user_load_by_mail('test@test.com');
    $this->assertNotEmpty($account);
    $this->assertTrue($article_flag->isFlagged($article, $account));
    // The account is not a full account, but a "decoupled" account.
    $this->assertInstanceOf(DecoupledAuthUserInterface::class, $account);
    $this->assertFalse($account->isCoupled());

    // The cancel link from the confirm email is now invalid.
    $this->drupalGet($mail_urls[3]);
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');
    $assert_session->addressEquals('/');

    // Subscribe to a different flag and node.
    $this->resetMailCollector();
    // Visit the subscribe form directly, without going to the node first.
    $this->requestSubscriptionForEntity($pages_flag, $page, 'another@example.com');

    // Receive a subscription confirm email.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'another@example.com', $pages_flag, $page);

    // Visit the subscription confirm link from the email.
    $this->drupalGet($mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $assert_session->addressEquals($page->toUrl()->setAbsolute()->toString());
    // An account was created with the new email address.
    // The account is subscribed to the flag and node.
    $account = user_load_by_mail('another@example.com');
    $this->assertNotEmpty($account);
    $this->assertTrue($pages_flag->isFlagged($page, $account));

    // The cancel link from the confirm email is now invalid.
    $this->drupalGet($mail_urls[3]);
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');
    $assert_session->addressEquals('/');

    // Create and subscribe to another page node.
    $page_two = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);
    $this->resetMailCollector();
    $this->requestSubscriptionForEntity($pages_flag, $page_two, 'another@example.com');

    // Receive a subscription confirm email.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'another@example.com', $pages_flag, $page_two);

    // Visit the cancel link from the email.
    $this->drupalGet($mail_urls[3]);
    $assert_session->statusMessageContains('Your subscription request has been canceled.', 'status');
    $assert_session->addressEquals('/');
    // The user is not subscribed.
    $this->assertFalse($pages_flag->isFlagged($page_two, $account));

    // The confirm link from the email is now invalid.
    $this->drupalGet($mail_urls[2]);
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');
    $assert_session->addressEquals($page_two->toUrl()->setAbsolute()->toString());

    // Test that a user can have multiple pending subscription requests.
    $this->resetMailCollector();
    $this->requestSubscriptionForEntity($pages_flag, $page, 'multiple@example.com');
    $this->requestSubscriptionForEntity($pages_flag, $page_two, 'multiple@example.com');

    // Receive two subscription confirm emails.
    $mails = $this->getMails();
    $this->assertCount(2, $mails);
    $first_mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'multiple@example.com', $pages_flag, $page);
    $second_mail_urls = $this->assertSubscriptionConfirmationMail($mails[1], 'multiple@example.com', $pages_flag, $page_two);

    // Visit the confirm link from the first email.
    $this->drupalGet($first_mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $account = user_load_by_mail('multiple@example.com');
    $this->assertNotEmpty($account);
    $this->assertTrue($pages_flag->isFlagged($page, $account));

    // Visit the confirm link from the second email.
    $this->drupalGet($second_mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $this->assertTrue($pages_flag->isFlagged($page_two, $account));
  }

  /**
   * Tests a case where an email is already taken when a request is submitted.
   */
  public function testEmailTakenOnSubscribeRequest(): void {
    // Create flag and page.
    $pages_flag = $this->createFlagFromArray([
      'id' => 'subscribe_page',
      'entity_type' => 'node',
      'bundles' => ['page'],
    ]);
    $entity = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);

    // Create a regular user account.
    $user = $this->createUser(values: ['mail' => 'conflict@example.com']);
    $this->assertInstanceOf(DecoupledAuthUserInterface::class, $user);
    $this->assertTrue($user->isCoupled());

    // Request to subscribe as anonymous, with the same email address.
    $this->requestSubscriptionForEntity($pages_flag, $entity, 'conflict@example.com');

    // Receive a failure email.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_data = $mails[0];
    $this->assertMailProperty('to', 'conflict@example.com', $mail_data);
    $this->assertMailProperty('subject', "Cannot subscribe to {$entity->label()}", $mail_data);

    $this->assertMailString('body', "Thank you for showing interest in keeping up with the updates for {$entity->label()} [1]!", $mail_data);
    $this->assertMailString('body', 'The email address you were using to subscribe is already associated with a regular account on this website.', $mail_data);
    $this->assertMailString('body', 'If you still want to subscribe to content updates for this item, you should log into the website, using your existing account, and then subscribe as a regular user.', $mail_data);
    $this->assertMailString('body', 'If you do not want to subscribe, you can ignore this message.', $mail_data);

    $mail_urls = $this->getMailFootNoteUrls($mail_data['body']);
    $this->assertCount(1, $mail_urls);
    $this->assertEquals($entity->toUrl()->setAbsolute()->toString(), $mail_urls[1]);
  }

  /**
   * Tests the terms and conditions link.
   */
  public function testTermsAndConditionsLink() {
    $flag = $this->createFlagFromArray([
      'id' => 'subscribe_all',
      'flag_short' => 'Subscribe',
      'entity_type' => 'node',
      'bundles' => [],
    ]);
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
    $page = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();
    $terms_config = \Drupal::configFactory()->getEditable(SettingsFormAlter::CONFIG_NAME);

    // Assert that the link is not present if the configuration is not set.
    $this->visitSubscriptionRequestPageForEntity($flag, $article);
    $assert_session->fieldExists('I have read and agree with the data protection terms.');
    $assert_session->linkNotExists('data protection terms');

    // The link to article is present.
    $terms_config->set('terms_url', 'entity:node/' . $article->id())->save();
    $this->visitSubscriptionRequestPageForEntity($flag, $article);
    $this->clickLink('data protection terms');
    $assert_session->addressEquals($article->toUrl());

    // The link to page is present.
    $terms_config->set('terms_url', 'entity:node/' . $page->id())->save();
    $this->visitSubscriptionRequestPageForEntity($flag, $page);
    $this->clickLink('data protection terms');
    $assert_session->addressEquals($page->toUrl());

    // Delete node and check that the field is not present.
    $page->delete();
    $this->visitSubscriptionRequestPageForEntity($flag, $article);
    $assert_session->fieldExists('I have read and agree with the data protection terms.');
    $assert_session->linkNotExists('data protection terms');

    // Set external URL for terms page.
    $terms_config->set('terms_url', 'https://www.drupal.org/')->save();
    $this->visitSubscriptionRequestPageForEntity($flag, $article);
    $link = $this->getSession()->getPage()->findLink('data protection terms');
    $this->assertEquals('https://www.drupal.org/', $link->getAttribute('href'));
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
    $this->assertMailProperty('to', $email, $mail_data);
    $this->assertMailProperty('subject', "Confirm your subscription to {$entity->label()}", $mail_data);
    $this->assertMailString('body', "Thank you for showing interest in keeping up with the updates for {$entity->label()} [1]!", $mail_data);
    $this->assertMailString('body', 'Click the following link to confirm your subscription: Confirm my subscription [2]', $mail_data);
    $this->assertMailString('body', 'If you no longer wish to subscribe, click on the link below: Cancel the subscription request [3]', $mail_data);
    $this->assertMailString('body', "If you didn't subscribe to these updates or you're not sure why you received this e-mail, you can delete it. You will not be subscribed if you don't click on the confirmation link above.", $mail_data);

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

  /**
   * Visits and submits a subscription form.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The subscription flag.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to subscribe to.
   * @param string $email
   *   The email address to use in the subscribe form.
   */
  protected function requestSubscriptionForEntity(FlagInterface $flag, EntityInterface $entity, string $email): void {
    $assert_session = $this->assertSession();
    $mail_label = 'Your e-mail';
    $terms_label = 'I have read and agree with the data protection terms.';

    // Visit the subscribe form directly, without going to the entity first.
    $this->visitSubscriptionRequestPageForEntity($flag, $entity);

    // Fill the subscribe form, and submit.
    $assert_session->fieldExists($mail_label)->setValue($email);
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();

    // Arrive on the entity page with a status message.
    $this->assertSubscriptionCreateMailStatusMessage();
    $assert_session->addressEquals($entity->toUrl()->setAbsolute()->toString());
  }

  /**
   * Visits the subscription request page for the given entity.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to flag.
   */
  protected function visitSubscriptionRequestPageForEntity(FlagInterface $flag, EntityInterface $entity): void {
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => $flag->id(),
      'entity_id' => $entity->id(),
    ]));
  }

}
