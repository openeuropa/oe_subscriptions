<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_digest\Functional;

use Drupal\message_digest\Entity\MessageDigestInterval;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the user subscriptions page.
 */
class UserDigestTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
   * Tests the user digest preference.
   */
  public function testUserPreferences(): void {
    $fn_get_path = function ($user) {
      $this->drupalLogin($user);
      return "/user/{$user->id()}/subscriptions";
    };

    $this->doTestDigestPreferences($this->drupalCreateUser(), $fn_get_path);
  }

}
