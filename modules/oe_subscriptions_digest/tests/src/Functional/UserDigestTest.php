<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_digest\Functional;

/**
 * {@inheritDoc}
 */
class UserDigestTest extends UserDigestTestBase {

  /**
   * Tests the user digest preferences.
   */
  public function testDigestPreferences(): void {
    $fn_get_path = function ($user) {
      $this->drupalLogin($user);
      return "/user/{$user->id()}/subscriptions";
    };
    $this->doTestDigestPreferences($this->drupalCreateUser(), $fn_get_path);
  }

  /**
   * Tests the user flagging digest.
   */
  public function testFlaggingDigest(): void {
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    $this->doTestFlaggingDigest($user);
  }

}
