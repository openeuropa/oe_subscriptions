<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;

/**
 * Tests the extra field.
 */
class ExtraFieldTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_with_bundle');

    // Create a test bundle to use as referenced bundle.
    EntityTestBundle::create(['id' => 'page'])->save();
    EntityTestBundle::create(['id' => 'article'])->save();
    EntityTestBundle::create(['id' => 'news'])->save();
  }

  /**
   * Tests the extra field dynamic association on entities.
   */
  public function testExtraField(): void {
    // Create a flag.
    $subscribe_article_flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'label' => 'Subscribe article',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['article'],
    ]);

    // A flag that applies to all bundles.
    $this->createFlagFromArray([
      'id' => 'another_flag',
      'label' => 'Another flag',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => [],
    ]);

    $field_manager = \Drupal::service('entity_field.manager');
    // An extra field is generated but only for the article bundle.
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_article',
      'flag_subscribe_article',
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'article')['display']));
    $this->assertEqualsCanonicalizing([
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'page')['display']));
    $this->assertEqualsCanonicalizing([
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'news')['display']));

    $subscribe_article_flag->set('bundles', [
      'article',
      'page',
    ])->save();
    // The field is present for article and page but not news bundles.
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_article',
      'flag_subscribe_article',
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'article')['display']));
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_article',
      'flag_subscribe_article',
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'page')['display']));
    $this->assertEqualsCanonicalizing([
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'news')['display']));

    // Create a new subscribe that applies to all bundles.
    $all_flag = $this->createFlagFromArray([
      'id' => 'subscribe_all',
      'label' => 'Subscribe all bundles',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => [],
    ]);
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_article',
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_all',
      'flag_subscribe_article',
      'flag_subscribe_all',
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'article')['display']));
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_article',
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_all',
      'flag_subscribe_article',
      'flag_subscribe_all',
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'page')['display']));
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_all',
      'flag_subscribe_all',
      'flag_another_flag',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'news')['display']));

    // Disabled flags are not taken into account.
    $all_flag->disable();
    $all_flag->save();
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_article',
      'flag_subscribe_article',
      'flag_another_flag',
      // The flag module doesn't take into account status for their extra
      // fields.
      'flag_subscribe_all',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'article')['display']));
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link:subscribe_article',
      'flag_subscribe_article',
      'flag_another_flag',
      // The flag module doesn't take into account status for their extra
      // fields.
      'flag_subscribe_all',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'page')['display']));
    $this->assertEqualsCanonicalizing([
      'flag_another_flag',
      // The flag module doesn't take into account status for their extra
      // fields.
      'flag_subscribe_all',
    ], array_keys($field_manager->getExtraFields('entity_test_with_bundle', 'news')['display']));
  }

}
