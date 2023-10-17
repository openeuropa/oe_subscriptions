<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Tests the extra field.
 */
class ExtraFieldTest extends KernelTestBase {

  use FlagCreateTrait;

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('flagging');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['filter', 'flag', 'message_subscribe']);
    $this->installEntitySchema('message');
    $this->installEntitySchema('entity_test_with_bundle');

    // Create a test bundle to use as referenced bundle.
    EntityTestBundle::create(['id' => 'page'])->save();
    EntityTestBundle::create(['id' => 'article'])->save();

    // Create a flag.
    $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'label' => 'Subscribe article',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['article'],
      'link_type' => 'reload',
      'global' => FALSE,
    ]);

    $this->createFlagFromArray([
      'id' => 'another_flag',
      'label' => 'Another flag',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => [],
      'link_type' => 'reload',
      'global' => FALSE,
    ]);
  }

  /**
   * Tests the extra field dynamic association on entities.
   */
  public function testExtraField(): void {
    $field_manager = \Drupal::service('entity_field.manager');
    $fields = $field_manager->getExtraFields('entity_test_with_bundle', 'article');
    $this->assertEqualsCanonicalizing([
      'extra_field_oe_subscriptions_anonymous_subscribe_link',
      'flag_subscribe_article',
      'flag_another_flag',
    ], array_keys($fields['display']));

    $fields = $field_manager->getExtraFields('entity_test_with_bundle', 'page');
    $this->assertEqualsCanonicalizing([
      'flag_another_flag',
    ], array_keys($fields['display']));
  }

}
