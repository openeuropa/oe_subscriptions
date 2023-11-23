<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Tests the anonymous subscribe manager.
 */
class SubscriptionManagerTest extends KernelTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'extra_field',
    'decoupled_auth',
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
    EntityTestBundle::create(['id' => 'page'])->save();
  }

  /**
   * Tests the manager service.
   */
  public function testAnonymousSubscriptionManager(): void {
    // Create a flag.
    $flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['article'],
    ]);
    // A flag that applies to all bundles.
    $another_flag = $this->createFlagFromArray([
      'id' => 'another_flag',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => [],
    ]);

    $article = EntityTestWithBundle::create([
      'type' => 'article',
    ]);
    $article->save();
    $page = EntityTestWithBundle::create([
      'type' => 'page',
    ]);
    $page->save();

    $article_id = $article->id();
    $page_id = $page->id();
    $mail = '123@mail.com';
    $anonymous_subscribe_service = $this->container->get('oe_subscriptions_anonymous.subscription_manager');
    $flag_service = $this->container->get('flag');

    // Create subscription.
    $hash = $anonymous_subscribe_service->createSubscription($mail, $flag, $article_id);
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    // Validate subscription.
    $anonymous_subscribe_service->confirmSubscription($mail, $flag, $article_id, $hash);
    // Flag with the user to the entity.
    $this->assertTrue($flag->isFlagged($article, user_load_by_mail($mail)));
    // Cancel subscription.
    $anonymous_subscribe_service->cancelSubscription($mail, $flag, $article_id, $hash);
    // No subscription.
    $this->assertFalse($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    // No flag with the user to the entity.
    $this->assertFalse($flag->isFlagged($article, user_load_by_mail($mail)));

    // Create subscription and cancel before confirm.
    $hash = $anonymous_subscribe_service->createSubscription($mail, $flag, $article_id);
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    // Trigger cancel.
    $anonymous_subscribe_service->cancelSubscription($mail, $flag, $article_id, $hash);
    $this->assertFalse($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    // No flag with the user to the entity.
    $this->assertFalse($flag->isFlagged($article, user_load_by_mail($mail)));

    // Confirm/Cancel on non-existing subscription.
    $this->assertFalse($anonymous_subscribe_service->confirmSubscription($mail, $flag, $article_id, $hash));
    $this->assertFalse($anonymous_subscribe_service->cancelSubscription($mail, $flag, $article_id, $hash));

    // Confirm/Cancel on non-existing entity.
    $this->assertFalse($anonymous_subscribe_service->confirmSubscription($mail, $another_flag, $article_id, $hash));
    $this->assertFalse($anonymous_subscribe_service->cancelSubscription($mail, $another_flag, $article_id, $hash));

    // Confirm/Cancel on non-existing entity.
    $this->assertFalse($anonymous_subscribe_service->confirmSubscription($mail, $flag, '123', $hash));
    $this->assertFalse($anonymous_subscribe_service->cancelSubscription($mail, $flag, '123', $hash));

    // Confirm/Cancel with wrong hash.
    $wrong_hash = "1234567890";
    $hash = $anonymous_subscribe_service->createSubscription($mail, $flag, $article_id);
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    // Confirm with wrong hash.
    $anonymous_subscribe_service->confirmSubscription($mail, $flag, $article_id, $wrong_hash);
    // Confirm with wrong hash, subscription exists and is not flagged.
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    $this->assertFalse($flag->isFlagged($article, user_load_by_mail($mail)));
    // Cancel wrong hash.
    $this->assertFalse($anonymous_subscribe_service->cancelSubscription($mail, $flag, $article_id, $wrong_hash));
    // Cancel with wrong hash exists, subscription exists and is not flagged.
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    $this->assertFalse($flag->isFlagged($article, user_load_by_mail($mail)));
    // We remove it at the end.
    $anonymous_subscribe_service->cancelSubscription($mail, $flag, $article_id, $hash);

    // Multiple creation request with the same parameters.
    $first_hash = $anonymous_subscribe_service->createSubscription($mail, $flag, $article_id);
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    $second_hash = $anonymous_subscribe_service->createSubscription($mail, $flag, $article_id);
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    // Then we confirm with last.
    $anonymous_subscribe_service->confirmSubscription($mail, $flag, $article_id, $second_hash);
    $this->assertTrue($flag->isFlagged($article, user_load_by_mail($mail)));
    // We request a subscription again.
    $third_hash = $anonymous_subscribe_service->createSubscription($mail, $flag, $article_id);
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    $this->assertTrue($flag->isFlagged($article, user_load_by_mail($mail)));
    // Then we confirm with last.
    $anonymous_subscribe_service->confirmSubscription($mail, $flag, $article_id, $third_hash);
    $this->assertTrue($flag->isFlagged($article, user_load_by_mail($mail)));
    // And cancel.
    $anonymous_subscribe_service->cancelSubscription($mail, $flag, $article_id, $third_hash);
    $this->assertFalse($flag->isFlagged($article, user_load_by_mail($mail)));
    // None of them are equal.
    $this->assertNotEquals($first_hash, $second_hash);
    $this->assertNotEquals($first_hash, $third_hash);
    $this->assertNotEquals($second_hash, $third_hash);

    // Multiple subscriptions, same mail different flags and entities.
    $hash_flag_article = $anonymous_subscribe_service->createSubscription($mail, $flag, $article_id);
    $hash_another_article = $anonymous_subscribe_service->createSubscription($mail, $another_flag, $article_id);
    $hash_another_page = $anonymous_subscribe_service->createSubscription($mail, $another_flag, $page_id);
    // Generated tokens aren't the same.
    $this->assertNotEquals($hash_flag_article, $hash_another_article);
    $this->assertNotEquals($hash_another_page, $hash_another_article);
    $this->assertNotEquals($hash_flag_article, $hash_another_page);
    // Unconfirmed subscriptions exist.
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $flag, $article_id));
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $another_flag, $article_id));
    $this->assertTrue($anonymous_subscribe_service->subscriptionExists($mail, $another_flag, $page_id));
    // Confirm all.
    $anonymous_subscribe_service->confirmSubscription($mail, $flag, $article_id, $hash_flag_article);
    $anonymous_subscribe_service->confirmSubscription($mail, $another_flag, $article_id, $hash_another_article);
    $anonymous_subscribe_service->confirmSubscription($mail, $another_flag, $page_id, $hash_another_page);
    // Entities are flagged and subscription doesn't exist.
    $this->assertTrue($flag->isFlagged($article, user_load_by_mail($mail)));
    $this->assertTrue($another_flag->isFlagged($article, user_load_by_mail($mail)));
    $this->assertTrue($another_flag->isFlagged($page, user_load_by_mail($mail)));
    // Cancel all.
    $anonymous_subscribe_service->cancelSubscription($mail, $flag, $article_id, $hash_flag_article);
    $anonymous_subscribe_service->cancelSubscription($mail, $another_flag, $article_id, $hash_another_article);
    $anonymous_subscribe_service->cancelSubscription($mail, $another_flag, $page_id, $hash_another_page);
    // No flags are removed.
    $this->assertFalse($flag->isFlagged($article, user_load_by_mail($mail)));
    $this->assertFalse($another_flag->isFlagged($page, user_load_by_mail($mail)));
    $this->assertFalse($another_flag->isFlagged($article, user_load_by_mail($mail)));
  }

}
