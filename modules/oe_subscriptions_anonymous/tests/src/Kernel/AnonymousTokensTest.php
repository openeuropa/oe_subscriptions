<?php

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Core\Url;
use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\message\Entity\Message;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;
use Drupal\Tests\token\Functional\TokenTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test the Anonymous subscriptions tokens.
 */
class AnonymousTokensTest extends KernelTestBase {

  use TokenTestTrait;
  use UserCreationTrait;
  use MessageTemplateCreateTrait;

  /**
   * Tests token for subscriptions page.
   */
  public function testSubscriptionsPageToken() {
    // Create message template to test token rendering.
    $message_template = $this->createMessageTemplate(
      'test_message',
      'Test message',
      '',
      [
        '[oe_subscriptions_anonymous:subscriptions_page]',
      ],
      [
        'token options' => [
          'clear' => FALSE,
          'token replace' => TRUE,
        ],
      ],
    );

    // Create different users to check the output for decoupled and coupled.
    $decoupled_user = DecoupledAuthUser::create([
      'mail' => 'decoupled_user@example.com',
      'name' => NULL,
      'status' => 0,
    ]);
    $decoupled_user->save();
    $coupled_user = $this->createUser([], 'coupled_user');

    // Check token rendering in messages with different owners.
    $message_decoupled_owner = Message::create(['template' => $message_template->id()])
      ->setOwnerId($decoupled_user->id());
    $message_decoupled_owner->save();
    $expected_url = Url::fromUserInput('/user/subscriptions')->setAbsolute()->toString();
    $this->assertEquals($expected_url, (string) $message_decoupled_owner);

    $message_coupled_owner = Message::create(['template' => $message_template->id()])
      ->setOwnerId($coupled_user->id());
    $message_coupled_owner->save();
    $expected_url = Url::fromUserInput("/user/login?destination=/user/{$coupled_user->id()}/subscriptions")->setAbsolute()->toString();
    $this->assertEquals($expected_url, (string) $message_coupled_owner);
  }

}
