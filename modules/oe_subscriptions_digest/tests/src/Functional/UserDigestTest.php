<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_digest\Functional;

/**
 * Tests the user subscriptions page.
 */
class UserDigestTest extends UserDigestTestBase {

  /**
   * Tests the user digest.
   */
  public function testUserDigest(): void {
    $fn_get_path = function ($user) {
      $this->drupalLogin($user);
      return "/user/{$user->id()}/subscriptions";
    };

    $user = $this->drupalCreateUser([], NULL, FALSE, ['message_subscribe_email' => TRUE]);
    $this->doTestDigestPreferences($user, $fn_get_path);
    $this->doTestFlaggingDigest($user);
  }

}
