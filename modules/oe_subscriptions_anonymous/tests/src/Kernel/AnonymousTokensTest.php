<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Core\Url;
use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\Tests\oe_subscriptions\Kernel\TokensTest;

/**
 * Test the Anonymous subscriptions tokens.
 */
class AnonymousTokensTest extends TokensTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'extra_field',
    'field',
    'filter',
    'flag',
    'message',
    'message_notify',
    'message_subscribe',
    'oe_subscriptions',
    'oe_subscriptions_anonymous',
    'system',
    'text',
    'user',
    'decoupled_auth',
    'path_alias',
    'token',
  ];

  /**
   * Tests user tokens.
   */
  public function testAnonymousUserTokens(): void {
    $decoupled_user = DecoupledAuthUser::create([
      'mail' => 'decoupled_user@example.com',
      'name' => NULL,
      'status' => 0,
    ]);
    $decoupled_user->save();
    $url = Url::fromUserInput('/user/subscriptions');

    $this->doSubscriptionsPageTokenTest($url, $decoupled_user);
  }

}
