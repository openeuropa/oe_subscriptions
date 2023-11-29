<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_subscriptions_anonymous\AnonymousSubscriptionStorageInterface;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Tests the anonymous subscribe module services.
 */
class SubscriptionServicesTest extends KernelTestBase {

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
   * Tests the subscrition manager service.
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
    $anonymous_storage_service = $this->container->get('oe_subscriptions_anonymous.subscription_manager');

    // Subscribe to article.
    $anonymous_storage_service->subscribe($mail, $article_flag, $article_id);
    $this->assertTrue($article_flag->isFlagged($article, user_load_by_mail($mail)));
    // Subscribe to page.
    $anonymous_storage_service->subscribe($mail, $page_flag, $page_id);
    $this->assertTrue($page_flag->isFlagged($page, user_load_by_mail($mail)));
    // Subscribe to article again.
    $anonymous_storage_service->subscribe($mail, $article_flag, $article_id);
    $this->assertTrue($article_flag->isFlagged($article, user_load_by_mail($mail)));

  }

  /**
   * Tests the subscription storage service.
   */
  public function testAnonymousSubscriptionStorage(): void {
    $anonymous_storage_service = $this->container->get('oe_subscriptions_anonymous.subscription_storage');
    $mail = '123@mail.com';
    // Build scope, get hash and check that is valid.
    $scope = $anonymous_storage_service->buildScope(AnonymousSubscriptionStorageInterface::TYPE_SUBSCRIBE, ['1', '2']);
    $hash = $anonymous_storage_service->get($mail, $scope);
    $this->assertTrue($anonymous_storage_service->isValid($mail, $scope, $hash));
    // Try check with an outdated hash.
    $new_hash = $anonymous_storage_service->get($mail, $scope);
    $this->assertFalse($anonymous_storage_service->isValid($mail, $scope, $hash));
    // Only latest works.
    $this->assertTrue($anonymous_storage_service->isValid($mail, $scope, $new_hash));
    // Delete subscription.
    $hash = $anonymous_storage_service->get($mail, $scope);
    // Not valid.
    $this->assertFalse($anonymous_storage_service->isValid($mail, $scope, $new_hash));
    // Build again.
    $hash = $anonymous_storage_service->get($mail, $scope);
    $this->assertTrue($anonymous_storage_service->isValid($mail, $scope, $hash));
    // Set an old date for changed and check expired.
    $this->setSubscriptionChanged($mail, $scope, time() - 90000);
    $this->assertFalse($anonymous_storage_service->isValid($mail, $scope, $hash));
    // Create new, set time again and delete expired.
    $hash = $anonymous_storage_service->get('456@mail.com', $scope);
    $this->assertTrue($anonymous_storage_service->isValid('456@mail.com', $scope, $hash));
    $this->setSubscriptionChanged('456@mail.com', $scope, time() - 90000);
    $anonymous_storage_service->deleteExpired();
    $this->assertFalse($anonymous_storage_service->isValid('456@mail.com', $scope, $hash));
  }

  /**
   * Sets a subscription changed value.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param string $scope
   *   The entity to subscribe to.
   * @param string $changed
   *   The value we want to set as changed.
   *
   * @return void
   *   No return value.
   */
  private function setSubscriptionChanged(string $mail, string $scope, $changed): void {
    $connection = $this->container->get('database');
    // Update changed setting the changed older than a day ago.
    $connection->update('oe_subscriptions_anonymous_subscriptions')
      ->fields([
        'changed' => $changed,
      ])
      ->condition('mail', $mail)
      ->condition('scope', $scope)
      ->execute();
  }

}
