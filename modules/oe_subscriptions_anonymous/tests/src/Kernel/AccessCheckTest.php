<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests the access checker.
 */
class AccessCheckTest extends KernelTestBase {

  use FlagCreateTrait;
  use UserCreationTrait;

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
    $this->installEntitySchema('message');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['filter', 'flag', 'message_subscribe', 'user']);
    $this->installEntitySchema('entity_test_with_bundle');

    EntityTestBundle::create(['id' => 'article'])->save();
    EntityTestBundle::create(['id' => 'page'])->save();

    // Give access content permission to anonymous.
    $this->grantPermissions(Role::load('anonymous'), ['view test entity']);
    $this->setCurrentUser(new AnonymousUserSession());
  }

  /**
   * Tests the access to the link based on subscription_id parameters.
   */
  public function testAnonymousLinkAccess(): void {
    // Create a flag.
    $flag_id = 'subscribe_article';
    $flag = $this->createFlagFromArray([
      'id' => $flag_id,
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['article'],
    ]);
    // A flag that applies to all bundles.
    $this->createFlagFromArray([
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

    // Empty parameter values.
    $this->assertFalse($this->checkSubscribeRouteAccess('', ''));

    // Access is denied for non-existing flags.
    $this->assertFalse($this->checkSubscribeRouteAccess('subscribe_events', $article->id()));

    // Access should be allowed only for flags that start with 'subscribe_'.
    $this->assertFalse($this->checkSubscribeRouteAccess('another_flag', $article->id()));

    // Access is allowed only for existing entities.
    $this->assertFalse($this->checkSubscribeRouteAccess($flag_id, '1234'));

    // Access is not allowed if the entity is not flaggable.
    $this->assertFalse($this->checkSubscribeRouteAccess($flag_id, $page->id()));

    // Access is allowed when parameters match all conditions.
    $this->assertTrue($this->checkSubscribeRouteAccess($flag_id, $article->id()));

    // Access is not allowed if the entity to be flagged cannot be viewed by
    // the user.
    $forbidden_access = EntityTestWithBundle::create([
      'type' => 'article',
      // @see \Drupal\entity_test\EntityTestAccessControlHandler::checkAccess()
      'name' => 'forbid_access',
    ]);
    $forbidden_access->save();
    $this->assertFalse($this->checkSubscribeRouteAccess($flag_id, $forbidden_access->id()));

    // Route is not allowed for logged users.
    $this->assertFalse($this->checkSubscribeRouteAccess($flag_id, $article->id(), $this->createUser([], 'logged_user')));

    // Disabled flag.
    $flag->disable()->save();
    $this->assertFalse($this->checkSubscribeRouteAccess($flag_id, $article->id()));

    // Create a flag that allows to subscribe to all bundles.
    $this->createFlagFromArray([
      'id' => 'subscribe_all_bundles',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => [],
    ]);
    $this->assertTrue($this->checkSubscribeRouteAccess('subscribe_all_bundles', $article->id()));
    $this->assertTrue($this->checkSubscribeRouteAccess('subscribe_all_bundles', $page->id()));
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
