<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Tests the access checker.
 */
class AccessCheckTest extends KernelTestBase {

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
    'node',
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
    $this->installEntitySchema('node');
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('message');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['filter', 'flag', 'message_subscribe']);

    // Create a test bundle to use as referenced bundle.
    EntityTestBundle::create(['id' => 'article'])->save();
  }

  /**
   * Tests the access to the link based on subscription_id parameters.
   */
  public function testAnonymousLink(): void {
    // Services a variables.
    $route_name = 'oe_subscriptions_anonymous.anonymous_subscribe';
    // Create a flag.
    $flag_id = 'subscribe_article';
    $flag = $this->createFlagFromArray([
      'id' => $flag_id,
      'flag_type' => $this->getFlagType('node'),
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    // A flag that applies to all bundles.
    $this->createFlagFromArray([
      'id' => 'another_flag',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => [],
    ]);
    // Node.
    $node = Node::create([
      'title' => $this->randomMachineName(8),
      'type' => 'article',
      'status' => 1,
    ]);
    $node->save();
    $nid = $node->id();
    $access_manager = $this->container->get('access_manager');
    // Empty.
    $this->assertFalse($access_manager->checkNamedRoute($route_name,
      [
        'flag' => '',
        'entity_id' => '',
      ]
    ));
    // No matching flag.
    $this->assertFalse($access_manager->checkNamedRoute($route_name,
      [
        'flag' => 'subscribe_events',
        'entity_id' => $nid,
      ]
    ));
    // Flag doesn't starts with 'subscribe_'.
    $this->assertFalse($access_manager->checkNamedRoute($route_name,
      [
        'flag' => 'another_flag',
        'entity_id' => $nid,
      ]
    ));
    // Disabled flag.
    $flag->disable();
    $flag->save();
    $this->assertFalse($access_manager->checkNamedRoute($route_name,
      [
        'flag' => $flag_id,
        'entity_id' => $nid,
      ]
    ));
    $flag->enable();
    $flag->save();
    // Not existing node.
    $this->assertFalse($access_manager->checkNamedRoute($route_name,
      [
        'flag' => $flag_id,
        'entity_id' => '1234',
      ]
    ));
    // Finally a subscribe parameters matching all conditions.
    $this->assertFalse($access_manager->checkNamedRoute($route_name,
      [
        'flag' => $flag_id,
        'entity_id' => $nid,
      ]
    ));
  }

}
