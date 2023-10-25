<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Core\Routing\RouteMatch;
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
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['filter', 'flag', 'message_subscribe']);
    $this->installEntitySchema('message');

    // Create a test bundle to use as referenced bundle.
    EntityTestBundle::create(['id' => 'article'])->save();
  }

  /**
   * Tests the access to the link based on subscription_id parameters.
   */
  public function testAnonymousLink(): void {
    // Services a variables.
    $access_checker = \Drupal::service('oe_subscriptions_anonymous.access_checker');
    $route_provider = \Drupal::service('router.route_provider');
    $route_name = 'oe_subscriptions_anonymous.anonymous_subscribe';
    $route = $route_provider->getRouteByName($route_name);
    // Create a flag.
    $flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'label' => 'Subscribe article',
      'flag_type' => $this->getFlagType('node'),
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    $flag_id = $flag->id();
    // A flag that applies to all bundles.
    $this->createFlagFromArray([
      'id' => 'another_flag',
      'label' => 'Another flag',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => [],
    ]);
    // Node.
    $node = Node::create([
      'title' => $this->randomMachineName(8),
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ],
      ],
      'type' => 'article',
      'status' => 1,
    ]);
    $node->save();
    $nid = $node->id();

    // No paramenters.
    $route_match = new RouteMatch(
      $route_name,
      $route,
    );
    $this->assertFalse($access_checker->access($route_match)->isAllowed());

    // Empty.
    $route_match = new RouteMatch(
      $route_name,
      $route,
      [
        'flag' => '',
        'entity_id' => '',
      ]
    );
    $this->assertFalse($access_checker->access($route_match)->isAllowed());

    // No matching flag.
    $route_match = new RouteMatch(
      $route_name,
      $route,
      [
        'flag' => 'subscribe_events',
        'entity_id' => $nid,
      ]
    );
    $this->assertFalse($access_checker->access($route_match)->isAllowed());

    // Flag doesn't starts with 'subscribe_'.
    $route_match = new RouteMatch(
      $route_name,
      $route,
      [
        'flag' => 'another_flag',
        'entity_id' => $nid,
      ]
    );
    $this->assertFalse($access_checker->access($route_match)->isAllowed());

    // Disabled flag.
    $flag->disable();
    $flag->save();
    $route_match = new RouteMatch(
      $route_name,
      $route,
      [
        'flag' => $flag_id,
        'entity_id' => $nid,
      ]
    );
    $this->assertFalse($access_checker->access($route_match)->isAllowed());
    $flag->enable();
    $flag->save();

    // Not existing node.
    $route_match = new RouteMatch(
      $route_name,
      $route,
      [
        'flag' => $flag_id,
        'entity_id' => 1234,
      ]
    );
    $this->assertFalse($access_checker->access($route_match)->isAllowed());

    // Finally a subscribe parameters matching all conditions.
    $route_match = new RouteMatch(
      $route_name,
      $route,
      [
        'flag' => $flag_id,
        'entity_id' => $nid,
      ]
    );
    $this->assertTrue($access_checker->access($route_match)->isAllowed());

  }

}
