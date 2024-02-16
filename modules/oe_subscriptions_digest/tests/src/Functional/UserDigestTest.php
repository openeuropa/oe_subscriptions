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

    $this->doTestDigestPreferences($this->drupalCreateUser(), $fn_get_path);
    $this->doTestFlaggingDigest($this->drupalCreateUser());
  }

}
