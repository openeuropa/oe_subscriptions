<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the anonymous subscribe service.
 */
class AnonymousSubscribeServiceTest extends KernelTestBase {

  use FlagCreateTrait;
  use UserCreationTrait;

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
    $this->installSchema('oe_subscriptions_anonymous', ['oe_subscriptions_anonymous_subscriptions']);
    $this->installConfig(['filter', 'flag', 'message_subscribe', 'user']);
    $this->installEntitySchema('entity_test_with_bundle');

    EntityTestBundle::create(['id' => 'article'])->save();
  }

  /**
   * Tests the manager service.
   */
  public function testAnonymousSubscriptionManager(): void {
    // Create a flag.
    $flag_id = 'subscribe_article';
    $flag = $this->createFlagFromArray([
      'id' => $flag_id,
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['article'],
    ]);

    $article = EntityTestWithBundle::create([
      'type' => 'article',
    ]);
    $article->save();
    $entity_id = $article->id();

    $mail = '123@mail.com';
    $anonymous_subscribe_service = $this->container->get('oe_subscriptions_anonymous.subscription_manager');
    $flag_service = $this->container->get('flag');

    // Create subscription.
    $token = hash('sha512', "oe_subscriptions_anonymous:$mail:$flag_id:$entity_id");
    $generated_token = $anonymous_subscribe_service->createSubscription($mail, $flag, $entity_id);
    $this->assertEquals($token, $generated_token);
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $entity_id));

    // Validate subscription.
    $anonymous_subscribe_service->validateSubscription($mail, $flag, $entity_id, $generated_token);
    // No subscription.
    $this->assertFalse($anonymous_subscribe_service->subscriptionExists($mail, $flag, $entity_id));
    // Flag with the user to the entity.
    $this->assertTrue($flag->isFlagged($article, user_load_by_mail($mail)));

    // Cancel subscription.
    $anonymous_subscribe_service->cancelSubscription($mail, $flag, $entity_id, $generated_token);
    // No subscription.
    $this->assertFalse($anonymous_subscribe_service->subscriptionExists($mail, $flag, $entity_id));
    // No flag with the user to the entity.
    $this->assertFalse($flag->isFlagged($article, user_load_by_mail($mail)));

    // Create subscription and cancel before validate.
    $generated_token = $anonymous_subscribe_service->createSubscription($mail, $flag, $entity_id);
    $this->assertEquals($token, $generated_token);
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $entity_id));
    // Trigger cancel.
    $anonymous_subscribe_service->cancelSubscription($mail, $flag, $entity_id, $generated_token);
    // No subscription.
    $this->assertFalse($anonymous_subscribe_service->subscriptionExists($mail, $flag, $entity_id));
    // No flag with the user to the entity.
    $this->assertFalse($flag->isFlagged($article, user_load_by_mail($mail)));

  }

}
