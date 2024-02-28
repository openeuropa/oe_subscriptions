<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_digest\Functional;

use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\user\UserInterface;

/**
 * Tests the anonymous user digest.
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
   * Tests the anonymous user digest preferences.
   */
  public function testDigestPreferences(): void {
    $fn_get_path = function (UserInterface $user) {
      return $this->getAnonymousUserSubscriptionsPageUrl($user->getEmail());
    };
    $user = DecoupledAuthUser::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => NULL,
      'status' => 1,
      'roles' => ['anonymous_subscriber'],
    ]);
    $user->save();
    $this->doTestDigestPreferences($user, $fn_get_path);
  }

  /**
   * Tests the anonymous user flagging digest.
   */
  public function testFlaggingDigest(): void {
    $user = DecoupledAuthUser::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => NULL,
      'status' => 1,
      'roles' => ['anonymous_subscriber'],
    ]);
    $user->save();
    $this->doTestFlaggingDigest($user);
  }

}
