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

    $this->doTestDigestPreferences($user, $fn_get_path);
    $this->doTestFlaggingDigest($user);
  }

}
