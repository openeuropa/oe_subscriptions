<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_digest\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\message_digest\Entity\MessageDigestInterval;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\user\UserInterface;

/**
 * Tests the user subscriptions page.
 */
class UserDigestTest extends BrowserTestBase {

  use FlagCreateTrait;

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
    $another_flag = $this->createFlagFromArray([
      'id' => 'another_flag',
      'entity_type' => 'node',
      'bundles' => [],
    ]);
    $page_one = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);
    $page_two = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);
    FieldConfig::create([
      'field_name' => 'message_digest',
      'entity_type' => 'flagging',
      'label' => 'Frequency',
      'bundle' => 'subscribe_page',
      'description' => '',
      'required' => FALSE,
      'settings' => [],
    ])->save();
    FieldConfig::create([
      'field_name' => 'message_digest',
      'entity_type' => 'flagging',
      'label' => 'ABC',
      'bundle' => 'another_flag',
      'description' => '',
      'required' => FALSE,
      'settings' => [],
    ])->save();

    // User flags entities without setting the frequency.
    $flag_service = $this->container->get('flag');
    $flagging_storage = \Drupal::entityTypeManager()->getStorage('flagging');

    $flagging_page_one = $flag_service->flag($pages_flag, $page_one, $user);
    $this->assertTrue($pages_flag->isFlagged($page_one, $user));
    $this->assertTrue($flagging_page_one->get('message_digest')->isEmpty());

    $flagging_another_one = $flag_service->flag($another_flag, $page_one, $user);
    $this->assertTrue($another_flag->isFlagged($page_one, $user));
    $this->assertTrue($flagging_another_one->get('message_digest')->isEmpty());

    // Message digest preference is updated and all flaggings too.
    $user->set('message_digest', 'message_digest:daily')->save();
    $flagging_page_one = $flagging_storage->load($flagging_page_one->id());
    $this->assertEquals('message_digest:daily', $flagging_page_one->get('message_digest')->value);
    $flagging_another_one = $flagging_storage->load($flagging_another_one->id());
    $this->assertTrue($flagging_another_one->get('message_digest')->isEmpty());

    // Test that new flaggings are updated when frequency preference changes.
    $flagging_page_two = $flag_service->flag($pages_flag, $page_two, $user);
    $this->assertTrue($pages_flag->isFlagged($page_two, $user));
    $this->assertTrue($flagging_page_two->get('message_digest')->isEmpty());

    $user->set('message_digest', 'message_digest:weekly')->save();
    $flagging_storage->resetCache();
    $flagging_page_one = $flagging_storage->load($flagging_page_one->id());
    $this->assertEquals('message_digest:weekly', $flagging_page_one->get('message_digest')->value);
    $flagging_page_two = $flagging_storage->load($flagging_page_two->id());
    $this->assertEquals('message_digest:weekly', $flagging_page_two->get('message_digest')->value);
    $flagging_another_one = $flagging_storage->load($flagging_another_one->id());
    $this->assertTrue($flagging_another_one->get('message_digest')->isEmpty());
  }

  /**
   * Tests the user digest preference.
   */
  public function testUserDigest(): void {
    $fn_get_path = function ($user) {
      $this->drupalLogin($user);
      return "/user/{$user->id()}/subscriptions";
    };

    $this->doTestDigestPreferences($this->drupalCreateUser(), $fn_get_path);
    $this->doTestFlaggingDigest($this->drupalCreateUser());
  }

}
