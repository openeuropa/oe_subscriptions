<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Tests\oe_subscriptions\Functional\UserSubscriptionsPageTestBase;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\SubscriptionsPageTrait;
use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Tests the subscriptions page for anonymous (decoupled) users.
 */
class AnonymousUserSubscriptionsPageTest extends UserSubscriptionsPageTestBase {

  use SubscriptionsPageTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_subscriptions_anonymous',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Role::load('anonymous_subscriber')
      ->grantPermission('view test entity')
      ->grantPermission('access content')
      ->save();
  }

  /**
   * Tests the subscriptions page.
   */
  public function testFlagsList(): void {
    $fn_create_users = function () {
      $user_one = DecoupledAuthUser::create([
        'mail' => 'user_one@example.com',
        'name' => NULL,
        'status' => 1,
        'roles' => ['anonymous_subscriber'],
      ]);
      $user_one->save();
      $user_two = DecoupledAuthUser::create([
        'mail' => 'user_two@example.com',
        'name' => NULL,
        // The status depends on the website configuration. We set this one as
        // blocked, so we can verify that our pages do not depend on the user
        // account status.
        'status' => 0,
        'roles' => ['anonymous_subscriber'],
      ]);
      $user_two->save();

      return [$user_one, $user_two];
    };

    $fn_go_to_page = function (UserInterface $user) {
      $this->drupalGet($this->getAnonymousUserSubscriptionsPageUrl($user->getEmail()));
    };
    $this->doTestFlagsList($fn_create_users, $fn_go_to_page);

    // Verify that the mail field is required.
    $this->drupalGet('/user/subscriptions');
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Submit')->press();
    $assert_session->statusMessageContains('Your e-mail field is required.', 'error');

    // If a user doesn't exist on the website, a message is shown.
    $this->drupalGet($this->getAnonymousUserSubscriptionsPageUrl('test@example.com'));
    $assert_session->statusMessageContains("You don't have any subscriptions at the moment.", 'warning');
  }

  /**
   * Tests the user preferences fields.
   */
  public function testUserPreferences(): void {
    $user = DecoupledAuthUser::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => NULL,
      'status' => 1,
      'roles' => ['anonymous_subscriber'],
    ]);
    $user->save();
    $fn_get_path = function (UserInterface $user) {
      return $this->getAnonymousUserSubscriptionsPageUrl($user->getEmail());
    };

    $this->doTestUserPreferences($user, $fn_get_path);
  }

  /**
   * Tests the rendering of the introduction text.
   */
  public function testFormPreface(): void {
    $user = DecoupledAuthUser::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => NULL,
      'status' => 1,
      'roles' => ['anonymous_subscriber'],
    ]);
    $user->save();
    $fn_get_path = function (UserInterface $user) {
      return $this->getAnonymousUserSubscriptionsPageUrl($user->getEmail());
    };

    $this->doTestFormPreface($user, $fn_get_path);
  }

}
