<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

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
   * The access manager service.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

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
    $this->installConfig(['filter', 'flag', 'message_subscribe', 'user']);

    // Create a test bundle to use as referenced bundle.
    EntityTestBundle::create(['id' => 'article'])->save();
    // Get access manager service.
    $this->accessManager = $this->container->get('access_manager');
  }

  /**
   * Tests the access to the link based on subscription_id parameters.
   */
  public function testAnonymousLinkAccess(): void {
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
    $nid = $node->id();
    // Give access content permission to anonymous.
    $this->grantPermissions(Role::load('anonymous'), ['access content']);
    $this->setCurrentUser(new AnonymousUserSession());

    // Empty parameter values.
    $this->assertFalse($this->checkSubscribeRouteAccess('', ''));

    // Access is denied for non-existing flags.
    $this->assertFalse($this->checkSubscribeRouteAccess('subscribe_events', $nid));

    // Access should be allowed only for flags that start with 'subscribe_'.
    $this->assertFalse($this->checkSubscribeRouteAccess('another_flag', $nid));

    // Access is allowed only for existing nodes.
    $this->assertFalse($this->checkSubscribeRouteAccess($flag_id, '1234'));

    // Access is allowed when parameters match all conditions.
    $this->assertTrue($this->checkSubscribeRouteAccess($flag_id, $nid));

    // Route is not allowed for logged users.
    $this->assertFalse($this->checkSubscribeRouteAccess($flag_id, $nid, $this->createUser([], 'logged_user')));

    // Disabled flag.
    $flag->disable()->save();
    $this->assertFalse($this->checkSubscribeRouteAccess($flag_id, $nid));
  }

  /**
   * Returns access to the anonymous subscribe route with a set of parameters.
   *
   * @param string $flag_id
   *   The ID of the flag.
   * @param string $entity_id
   *   The entity ID.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   A user account to use for the check. Null to use anonymous.
   *
   * @return bool
   *   True if access is allowed, false otherwise.
   */
  protected function checkSubscribeRouteAccess(string $flag_id, string $entity_id, AccountInterface $user = NULL): bool {
    $route_name = 'oe_subscriptions_anonymous.anonymous_subscribe';
    $access_check = $this->container->get('access_manager')->checkNamedRoute(
      $route_name,
      [
        'flag' => $flag_id,
        'entity_id' => $entity_id,
      ],
      $user,
      TRUE,
    );

    return $access_check->isAllowed();
  }

}
