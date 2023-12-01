<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;

/**
 * Tests the anonymous subscribe module services.
 */
class SubscriptionManagerTest extends KernelTestBase {

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

}
