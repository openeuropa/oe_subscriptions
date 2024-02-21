<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_digest\Functional;

use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\user\UserInterface;

/**
 * Tests the user notifications frequency in subscriptions page.
 */
class AnonymousUserDigestTest extends UserDigestTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'oe_subscriptions_anonymous',
    'oe_subscriptions_digest',
  ];

  /**
   * Tests the anonymous user digest.
   */
  public function testAnonymousUserDigest(): void {
    $fn_get_path = function (UserInterface $user) {
      return $this->getAnonymousUserSubscriptionsPageUrl($user->getEmail());
    };

    $user_one = DecoupledAuthUser::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => NULL,
      'status' => 1,
      'roles' => ['anonymous_subscriber'],
    ]);
    $user_one->save();
    $this->doTestDigestPreferences($user_one, $fn_get_path);

    $user_two = DecoupledAuthUser::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => NULL,
      'status' => 1,
      'roles' => ['anonymous_subscriber'],
    ]);
    $user_two->save();
    $this->doTestFlaggingDigest($user_two);
  }

}
