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
    $url = Url::fromUserInput('/user/subscriptions');

    // No user present, token is not generated.
    $this->assertNoTokens('user', [], ['subscriptions-page']);
    // Valid user, token is generated.
    $this->assertTokens('user', ['user' => $decoupled_user],
    [
      'subscriptions-page' => $url->setAbsolute()->toString(),
      'subscriptions-page:path' => '/user/subscriptions',
      'subscriptions-page:alias' => '/user/subscriptions',
      'subscriptions-page:absolute' => $url->setAbsolute()->toString(),
      'subscriptions-page:relative' => $url->setAbsolute(FALSE)->toString(),
      'subscriptions-page:brief' => preg_replace(['!^https?://!', '!/$!'], '', $url->setAbsolute()->toString()),
      'subscriptions-page:unaliased' => $url->toString(),
      'subscriptions-page:args:value:0' => 'user',
      'subscriptions-page:args:value:1' => 'subscriptions',
      'subscriptions-page:args:value:2' => NULL,
    ]);

    // Check that the token still provides the same URL for coupled.
    $coupled_user = $this->createUser();
    $url = Url::fromUserInput("/user/login?destination=/user/{$coupled_user->id()}/subscriptions");
    // Valid user, token is generated.
    $this->assertTokens('user', ['user' => $coupled_user],
    [
      'subscriptions-page' => $url->setAbsolute()->toString(),
      'subscriptions-page:path' => '/user/login',
      'subscriptions-page:alias' => '/user/login',
      'subscriptions-page:absolute' => $url->setAbsolute()->toString(),
      'subscriptions-page:relative' => $url->setAbsolute(FALSE)->toString(),
      'subscriptions-page:brief' => preg_replace(['!^https?://!', '!/$!'], '', $url->setAbsolute()->toString()),
      'subscriptions-page:unaliased' => $url->toString(),
      'subscriptions-page:args:value:0' => 'user',
      'subscriptions-page:args:value:1' => 'login',
      'subscriptions-page:args:value:2' => NULL,
    ]);
  }

}
