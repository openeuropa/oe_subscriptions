<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Core\Session\AnonymousUserSession;

/**
 * Tests the access checker.
 */
class AccessCheckTest extends KernelTestBase {

  use FlagCreateTrait;
  use UserCreationTrait;
  use NodeCreationTrait;

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
     $this->installSchema('node', ['node_access']);
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
    $node = $this->createNode([
      'type' => 'article',
      'status' => 1,
    ]);
    $node->save();
    $nid = $node->id();
    $access_manager = $this->container->get('access_manager');
    // We store a anonymous account, passing NULL will take current.
    $anonymous = new AnonymousUserSession();

    // Empty.
    $access = $access_manager->checkNamedRoute(
      $route_name,
      [
        'flag' => '',
        'entity_id' => '',
      ],
      $anonymous,
      TRUE,
    );
    $this->assertFalse($access->isAllowed());

    // No matching flag.
    $access = $access_manager->checkNamedRoute(
      $route_name,
      [
        'flag' => 'subscribe_events',
        'entity_id' => $nid,
      ],
      $anonymous,
      TRUE,
    );
    $this->assertFalse($access->isAllowed());

    // Flag doesn't starts with 'subscribe_'.
    $access = $access_manager->checkNamedRoute(
      $route_name,
      [
        'flag' => 'another_flag',
        'entity_id' => $nid,
      ],
      $anonymous,
      TRUE,
    );
    $this->assertFalse($access->isAllowed());

    // Disabled flag.
    $flag->disable();
    $flag->save();
    $access = $access_manager->checkNamedRoute(
      $route_name,
      [
        'flag' => $flag_id,
        'entity_id' => $nid,
      ],
      $anonymous,
      TRUE,
    );
    $this->assertFalse($access->isAllowed());
    $flag->enable();
    $flag->save();

    // Not existing node.
    $access = $access_manager->checkNamedRoute(
      $route_name,
      [
        'flag' => $flag_id,
        'entity_id' => '1234',
      ],
      $anonymous,
      TRUE,
    );
    $this->assertFalse($access->isAllowed());

    // Route is not allowed for logged users.
    $user = $this->createUser([], 'user_1');
    $access = $access_manager->checkNamedRoute(
      $route_name,
      [
        'flag' => $flag_id,
        'entity_id' => $nid,
      ],
      $this->createUser([], 'logged_user'),
      TRUE,
    );
    $this->assertFalse($access->isAllowed());

    // Finally a subscribe parameters matching all conditions.
    $access = $access_manager->checkNamedRoute(
      $route_name,
      [
        'flag' => $flag_id,
        'entity_id' => $nid,
      ],
      $anonymous,
      TRUE,
    );
    $this->assertTrue($access->isAllowed());

  }

}
