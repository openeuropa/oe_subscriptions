<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\AssertMailTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\StatusMessageTrait;
use Drupal\flag\FlagInterface;
use Drupal\oe_subscriptions_anonymous\SettingsFormAlter;

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
    $this->visitSubscriptionRequestPageForEntity($pages_flag, $page);
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
    $this->visitSubscriptionRequestPageForEntity($pages_flag, $page_two);
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

    // Test that a user can have multiple pending subscription requests.
    $this->resetMailCollector();
    $this->visitSubscriptionRequestPageForEntity($pages_flag, $page);
    $assert_session->fieldExists($mail_label)->setValue('multiple@example.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $this->assertSubscriptionCreateMailStatusMessage();
    $this->visitSubscriptionRequestPageForEntity($pages_flag, $page_two);
    $assert_session->fieldExists($mail_label)->setValue('multiple@example.com');
    $assert_session->fieldExists($terms_label)->check();
    $assert_session->buttonExists('Subscribe me')->press();
    $this->assertSubscriptionCreateMailStatusMessage();

    $mails = $this->getMails();
    $this->assertCount(2, $mails);
    $first_mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'multiple@example.com', $pages_flag, $page);
    $second_mail_urls = $this->assertSubscriptionConfirmationMail($mails[1], 'multiple@example.com', $pages_flag, $page_two);

    $this->drupalGet($first_mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $account = user_load_by_mail('multiple@example.com');
    $this->assertNotEmpty($account);
    $this->assertTrue($pages_flag->isFlagged($page, $account));

    $this->drupalGet($second_mail_urls[2]);
    $assert_session->statusMessageContains('Your subscription request has been confirmed.', 'status');
    $this->assertTrue($pages_flag->isFlagged($page_two, $account));
  }

  /**
   * Tests subscribing with an address that belongs to a registered user.
   */
  public function testRegisteredUserEmailSubscribe(): void {
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
    /** @var \Drupal\decoupled_auth\DecoupledAuthUserInterface $user */
    $user = $this->createUser(values: ['mail' => 'conflict@example.com']);
    $this->assertTrue($user->isCoupled());

    // Request to subscribe as anonymous, with the same email address.
    $this->requestSubscriptionForEntity($pages_flag, $entity, 'conflict@example.com');

    // Receive a failure email.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_data = $mails[0];
    $this->assertMailProperty('to', 'conflict@example.com', $mail_data);
    $this->assertMailProperty('subject', "Please log in to subscribe to {$entity->label()}", $mail_data);

    $this->assertMailString('body', "Thank you for showing interest in keeping up with the updates for {$entity->label()} [1]!", $mail_data);
    $this->assertMailString('body', 'The email address you were using when trying to subscribe is already associated with a regular account on this website.', $mail_data);
    $this->assertMailString('body', 'If you still want to subscribe to the updates made to the content, you can log in to the website, using your existing account, and then subscribe as a regular user.', $mail_data);
    $this->assertMailString('body', 'If you do not want to subscribe or are unsure why you received this email, you can ignore this message.', $mail_data);

    $mail_urls = $this->getMailFootNoteUrls($mail_data['body']);
    $this->assertCount(1, $mail_urls);
    $this->assertEquals($entity->toUrl()->setAbsolute()->toString(), $mail_urls[1]);
  }

  /**
   * Tests a subscribe email belonging to a registered user on confirm.
   */
  public function testRegisteredUserEmailConfirm(): void {
    // Create flag and page.
    $pages_flag = $this->createFlagFromArray([
      'id' => 'subscribe_page',
      'entity_type' => 'node',
      'bundles' => ['page'],
    ]);
    $page = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);

    // Request to subscribe as anonymous, at a time when no account exists yet
    // with this email address.
    $this->requestSubscriptionForEntity($pages_flag, $page, 'conflict@example.com');

    // Receive the confirm email.
    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $mail_urls = $this->assertSubscriptionConfirmationMail($mails[0], 'conflict@example.com', $pages_flag, $page);

    // Create a regular user account with the same email address.
    // Do this before visiting the confirm link.
    $this->createUser(values: ['mail' => 'conflict@example.com']);

    // Visit the confirm link from the email.
    $this->drupalGet($mail_urls[2]);

    $assert_session = $this->assertSession();
    $assert_session->statusMessageContains('You have attempted to subscribe as anonymous, using an email address that is already associated with a regular account.', 'warning');
    $this->assertHtmlStatusMessage(['br' => ''], 'warning');
    $assert_session->statusMessageContains('If you still want to subscribe to content updates for this item, you can log in to the website, using your existing account, and then subscribe as a regular user.
', 'warning');
    $assert_session->addressEquals($page->toUrl()->setAbsolute()->toString());
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
