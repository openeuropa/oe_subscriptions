<?php

namespace Drupal\Tests\oe_subscriptions\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;
use Drupal\Tests\token\Functional\TokenTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test the Subscriptions tokens.
 */
class TokensTest extends KernelTestBase {

  use TokenTestTrait;
  use UserCreationTrait;
  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'flag',
    'message',
    'message_notify',
    'message_subscribe',
    'message_subscribe_ui',
    'oe_subscriptions',
    'token',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('message');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['filter', 'flag', 'message_subscribe', 'user']);
  }

  /**
   * Tests user tokens.
   */
  public function testUserTokens() {
    $user = $this->createUser();
    $message_template = $this->createMessageTemplate();
    $message = Message::create(['template' => $message_template->id()]);
    $message->save();

    // No user and message present, token is not generated.
    $this->assertNoTokens('user', [], ['subscriptions-page-url']);
    // Only user present, token is not generated.
    $this->assertNoTokens('user', ['user' => $user], ['subscriptions-page-url']);
    // Only message present, token is not generated.
    $this->assertNoTokens('user', ['message' => $message], ['subscriptions-page-url']);
    // Valid user and message.
    $this->assertTokens('user', ['user' => $user, 'message' => $message], [
      'subscriptions-page-url' => Url::fromUserInput("/user/login?destination=/user/{$user->id()}/subscriptions")->setAbsolute()->toString(),
    ]);
  }

}
