<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\decoupled_auth\DecoupledAuthUserInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\oe_subscriptions_anonymous\Exception\RegisteredUserEmailException;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the anonymous subscription manager.
 */
class SubscriptionManagerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'decoupled_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('oe_subscriptions_anonymous', ['oe_subscriptions_anonymous_tokens']);
    $this->installEntitySchema('entity_test_with_bundle');

    EntityTestBundle::create(['id' => 'article'])->save();
    EntityTestBundle::create(['id' => 'page'])->save();
  }

  /**
   * Tests the subscription manager service.
   */
  public function testAnonymousSubscriptionManager(): void {
    // Create a flag.
    $article_flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['article'],
    ]);
    $page_flag = $this->createFlagFromArray([
      'id' => 'subscribe_page',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['page'],
    ]);
    $article_one = EntityTestWithBundle::create([
      'type' => 'article',
    ]);
    $article_one->save();
    $article_two = EntityTestWithBundle::create([
      'type' => 'article',
    ]);
    $article_two->save();
    $page = EntityTestWithBundle::create([
      'type' => 'page',
    ]);
    $page->save();

    /** @var \Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionManagerInterface $subscription_manager */
    $subscription_manager = $this->container->get('oe_subscriptions_anonymous.subscription_manager');

    // Test subscribing with an e-mail that is not associated to any users.
    $subscription_manager->subscribe('test@example.com', $article_flag, $article_one->id());
    $user = user_load_by_mail('test@example.com');
    $this->assertInstanceOf(DecoupledAuthUserInterface::class, $user);
    $this->assertTrue($article_flag->isFlagged($article_one, $user));
    // The created user is marked as decoupled.
    $this->assertTrue($user->isDecoupled());
    $this->assertTrue($user->hasRole('anonymous_subscriber'));

    // Only account was created.
    $user_storage = $this->container->get('entity_type.manager')->getStorage('user');
    $this->assertCount(1, $user_storage->loadMultiple());

    // Subscribe the same e-mail to another content of the same type.
    $subscription_manager->subscribe('test@example.com', $article_flag, $article_two->id());
    $this->assertTrue($article_flag->isFlagged($article_two, $user));
    $this->assertTrue($article_flag->isFlagged($article_one, $user));

    // A decoupled user with the given e-mail already existed, so no new
    // accounts were created.
    $this->assertCount(1, $user_storage->loadMultiple());

    // Subscribe another user to the first article.
    $subscription_manager->subscribe('another@example.com', $article_flag, $article_one->id());
    $another_user = user_load_by_mail('another@example.com');
    $this->assertInstanceOf(DecoupledAuthUserInterface::class, $another_user);
    $this->assertTrue($another_user->isDecoupled());
    $this->assertTrue($another_user->hasRole('anonymous_subscriber'));
    $this->assertTrue($article_flag->isFlagged($article_one, $another_user));
    $this->assertCount(2, $user_storage->loadMultiple());
    // The other flags are not impacted.
    $this->assertTrue($article_flag->isFlagged($article_two, $user));
    $this->assertTrue($article_flag->isFlagged($article_one, $user));

    // Subscribe the same user with another flag.
    $subscription_manager->subscribe('test@example.com', $page_flag, $page->id());
    $this->assertTrue($page_flag->isFlagged($page, $user));
    $this->assertCount(2, $user_storage->loadMultiple());

    // We use a very high number for the ID, so we are sure an entity with this
    // id doesn't exist in the storage.
    $nonexistent_id = 10000;
    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('entity_test_with_bundle')->load($nonexistent_id));
    // The service returns FALSE when the entity being subscribed to doesn't
    // exist.
    $this->assertFalse($subscription_manager->subscribe('another@example.com', $page_flag, $nonexistent_id));

    // Subscribe a decoupled user without the 'anonymous_subscriber' role.
    $decoupled_user = $this->createUser([], 'decoupled_user');
    $decoupled_user->decouple()->save();
    $this->assertInstanceOf(DecoupledAuthUserInterface::class, $decoupled_user);
    $this->assertTrue($decoupled_user->isDecoupled());
    $this->assertFalse($decoupled_user->hasRole('anonymous_subscriber'));
    $this->assertCount(3, $user_storage->loadMultiple());
    $subscription_manager->subscribe('decoupled_user@example.com', $page_flag, $page->id());
    $this->assertTrue($page_flag->isFlagged($page, $user));
    $this->assertTrue($user->hasRole('anonymous_subscriber'));
    $this->assertCount(3, $user_storage->loadMultiple());

    // The service returns an exception if we try to subscribe a coupled user.
    // A coupled user is a user with a full account, completely registered in
    // the platform.
    $coupled_user = $this->createUser([], 'coupled_user');
    $this->assertInstanceOf(DecoupledAuthUserInterface::class, $coupled_user);
    $this->assertTrue($coupled_user->isCoupled());
    $this->expectException(RegisteredUserEmailException::class);
    $this->expectExceptionMessage('The e-mail coupled_user@example.com belongs to a fully registered user.');
    $subscription_manager->subscribe('coupled_user@example.com', $page_flag, $page->id());
  }

}
