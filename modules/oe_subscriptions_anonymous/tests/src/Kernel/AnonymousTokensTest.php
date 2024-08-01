<?php

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Core\Url;
use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\Tests\token\Functional\TokenTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test the Anonymous subscriptions tokens.
 */
class AnonymousTokensTest extends KernelTestBase {

  use TokenTestTrait;
  use UserCreationTrait;

  /**
   * Tests user tokens.
   */
  public function testUserTokens() {
    $decoupled_user = DecoupledAuthUser::create([
      'mail' => 'decoupled_user@example.com',
      'name' => NULL,
      'status' => 0,
    ]);
    $decoupled_user->save();

    // No user present, token is not generated.
    $this->assertNoTokens('user', [], ['subscriptions-page-url']);
    // Valid user, token is generated.
    $this->assertTokens('user', ['user' => $decoupled_user], [
      'subscriptions-page-url' => Url::fromUserInput('/user/subscriptions')->setAbsolute()->toString(),
    ]);
    // Check that the token still provides the same URL for coupled.
    $coupled_user = $this->createUser();
    $this->assertTokens('user', ['user' => $coupled_user], [
      'subscriptions-page-url' => Url::fromUserInput("/user/login?destination=/user/{$coupled_user->id()}/subscriptions")->setAbsolute()->toString(),
    ]);
  }

}
