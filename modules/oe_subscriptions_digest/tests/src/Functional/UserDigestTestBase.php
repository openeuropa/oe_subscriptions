<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_digest\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\SubscriptionsPageTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\message_digest\Entity\MessageDigestInterval;
use Drupal\user\UserInterface;

/**
 * Tests the user digest.
 */
abstract class UserDigestTestBase extends BrowserTestBase {

  use FlagCreateTrait;
  use SubscriptionsPageTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'oe_subscriptions_digest',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the user digest preference.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for the test.
   * @param callable $fn_get_path
   *   Callable that returns the path to visit for the test. It also needs to
   *   take care of logging the user, or requesting a token in order to allow
   *   visiting the path.
   */
  protected function doTestDigestPreferences(UserInterface $user, callable $fn_get_path): void {
    // Test notifications frequency user settings.
    $path = $fn_get_path($user);
    $this->drupalGet($path);
    $assert_session = $this->assertSession();

    $select = $assert_session->selectExists('Notifications frequency');

    $this->assertEquals([
      'Send immediately' => 'Send immediately',
      'message_digest:daily' => 'Daily',
      'message_digest:weekly' => 'Weekly',
    ], $this->getOptions($select));

    MessageDigestInterval::create([
      'id' => 'bi_weekly',
      'label' => 'Bi-weekly',
      'interval' => '2 weeks',
    ])->save();
    $this->drupalGet($path);
    $this->assertEquals([
      'Send immediately' => 'Send immediately',
      'message_digest:daily' => 'Daily',
      'message_digest:weekly' => 'Weekly',
      'message_digest:bi_weekly' => 'Bi-weekly',
    ], $this->getOptions($select));

    MessageDigestInterval::create([
      'id' => 'monthly',
      'label' => 'Monthly',
      'interval' => '1 month',
    ])->save();
    $this->drupalGet($path);
    $this->assertEquals([
      'Send immediately' => 'Send immediately',
      'message_digest:daily' => 'Daily',
      'message_digest:weekly' => 'Weekly',
      'message_digest:bi_weekly' => 'Bi-weekly',
      'message_digest:monthly' => 'Monthly',
    ], $this->getOptions($select));

    $select->selectOption('Daily');
    $assert_session->buttonExists('Save')->press();
    $assert_session->statusMessageContains('Your preferences have been saved', 'status');

    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $user_storage->resetCache();
    /** @var \Drupal\user\UserInterface $user */
    $user = $user_storage->load($user->id());
    $this->assertEquals('message_digest:daily', $user->get('message_digest')->value);
  }

  /**
   * Tests the flagging digest updating.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for the test.
   */
  protected function doTestFlaggingDigest(UserInterface $user): void {
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $pages_flag = $this->createFlagFromArray([
      'id' => 'subscribe_page',
      'entity_type' => 'node',
      'bundles' => ['page'],
    ]);
    $page_one = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);
    $page_two = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);
    $email_node_message_digest = FieldConfig::loadByName('flagging', 'email_node', 'message_digest');
    $email_node_message_digest->createDuplicate()->set('bundle', 'email_page')->save();

    $flag_service = \Drupal::service('flag');
    $flagging_storage = \Drupal::entityTypeManager()->getStorage('flagging');
    $mail_flag = $flag_service->getFlagById('email_page');

    // User flags entities without setting the digest, gets 'send inmediatly'.
    $flag_service->flag($pages_flag, $page_one, $user);
    $this->assertTrue($pages_flag->isFlagged($page_one, $user));
    $this->assertTrue($mail_flag->isFlagged($page_one, $user));
    $flagging_page_one = $flag_service->getFlagging($mail_flag, $page_one, $user);
    $this->assertEquals(0, $flagging_page_one->get('message_digest')->value);

    // Message digest preference is updated and flagging too.
    $user->set('message_digest', 'message_digest:daily')->save();
    $flagging_page_one = $flagging_storage->load($flagging_page_one->id());
    $this->assertEquals('message_digest:daily', $flagging_page_one->get('message_digest')->value);

    // Test that new flaggins digest use the flagging user preference.
    $flag_service->flag($pages_flag, $page_two, $user);
    $this->assertTrue($pages_flag->isFlagged($page_two, $user));
    $this->assertTrue($mail_flag->isFlagged($page_two, $user));
    $flagging_page_two = $flag_service->getFlagging($mail_flag, $page_two, $user);
    $this->assertEquals('message_digest:daily', $flagging_page_two->get('message_digest')->value);

    // Test that all flaggings are updated when user digest preference changes.
    $user->set('message_digest', 'message_digest:weekly')->save();
    $flagging_page_one = $flagging_storage->load($flagging_page_one->id());
    $this->assertEquals('message_digest:weekly', $flagging_page_one->get('message_digest')->value);
    $flagging_page_two = $flagging_storage->load($flagging_page_two->id());
    $this->assertEquals('message_digest:weekly', $flagging_page_two->get('message_digest')->value);
  }

}
