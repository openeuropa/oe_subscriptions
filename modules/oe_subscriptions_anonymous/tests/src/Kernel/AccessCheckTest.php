<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Tests\oe_subscriptions\Trait\RouteAccessTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\flag\FlagInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the access checker.
 */
class AccessCheckTest extends KernelTestBase {

  use UserCreationTrait;
  use RouteAccessTrait;

  /**
   * A flag that allows to subscribe to articles.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected FlagInterface $subscribeArticleFlag;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_with_bundle');

    EntityTestBundle::create(['id' => 'article'])->save();
    EntityTestBundle::create(['id' => 'page'])->save();

    // Give access content permission to anonymous.
    $this->grantPermissions(Role::load('anonymous'), ['view test entity']);

    $this->subscribeArticleFlag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
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

    // @todo Remove when support for 10.2.x is dropped.
    // See https://www.drupal.org/project/drupal/issues/3158130
    // Call the install hook of the User module which creates the Anonymous user
    // and User 1. This is needed because the Anonymous user is loaded to
    // provide the current User context which is needed in places like route
    // enhancers.
    // @see CurrentUserContext::getRuntimeContexts().
    // @see EntityConverter::convert().
    \Drupal::moduleHandler()->loadInclude('user', 'install');
    user_install();
  }

  /**
   * Tests that the subscription access check is applied to sensitive routes.
   *
   * @param string $route_name
   *   The route to check.
   * @param array $extra_parameters
   *   Extra parameters to pass to the route.
   *
   * @dataProvider routeDataProvider
   */
  public function testRouteAccessCheck(string $route_name, array $extra_parameters = []): void {
    $flag_id = 'subscribe_article';
    $fn_get_params = static fn(string $flag_id, string $entity_id) => [
      'flag' => $flag_id,
      'entity_id' => $entity_id,
    ] + $extra_parameters;

    $article = EntityTestWithBundle::create([
      'type' => 'article',
    ]);
    $article->save();
    $page = EntityTestWithBundle::create([
      'type' => 'page',
    ]);
    $page->save();

    // Empty parameter values.
    $this->assertFalse($this->checkRouteAccess($route_name, $fn_get_params('', '')));

    // Access is denied for non-existing flags.
    $this->assertFalse($this->checkRouteAccess($route_name, $fn_get_params('subscribe_events', $article->id())));

    // Access should be allowed only for flags that start with 'subscribe_'.
    $this->assertFalse($this->checkRouteAccess($route_name, $fn_get_params('another_flag', $article->id())));

    // Access is allowed only for existing entities.
    $this->assertFalse($this->checkRouteAccess($route_name, $fn_get_params($flag_id, '1234')));

    // Access is not allowed if the entity is not flaggable.
    $this->assertFalse($this->checkRouteAccess($route_name, $fn_get_params($flag_id, $page->id())));

    // Access is allowed when parameters match all conditions.
    $this->assertTrue($this->checkRouteAccess($route_name, $fn_get_params($flag_id, $article->id())));

    // Access is not allowed if the entity to be flagged cannot be viewed by
    // the user.
    $forbidden_access = EntityTestWithBundle::create([
      'type' => 'article',
      // @see \Drupal\entity_test\EntityTestAccessControlHandler::checkAccess()
      'name' => 'forbid_access',
    ]);
    $forbidden_access->save();
    $this->assertFalse($this->checkRouteAccess($route_name, $fn_get_params($flag_id, $forbidden_access->id())));

    // Route is not allowed for logged users.
    $this->assertFalse($this->checkRouteAccess($route_name, $fn_get_params($flag_id, $article->id()), $this->createUser([], 'logged_user')));

    // Disabled flag.
    $this->subscribeArticleFlag->disable()->save();
    $this->assertFalse($this->checkRouteAccess($route_name, $fn_get_params($flag_id, $article->id())));

    // Create a flag that allows to subscribe to all bundles.
    $this->createFlagFromArray([
      'id' => 'subscribe_all_bundles',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => [],
    ]);
    $this->assertTrue($this->checkRouteAccess($route_name, $fn_get_params('subscribe_all_bundles', $article->id())));
    $this->assertTrue($this->checkRouteAccess($route_name, $fn_get_params('subscribe_all_bundles', $page->id())));
  }

  /**
   * Data provider for ::testRouteAccessCheck().
   *
   * @return iterable
   *   The test scenarios.
   */
  public function routeDataProvider(): iterable {
    yield [
      'oe_subscriptions_anonymous.subscription_request',
    ];

    // The next routes have the same parameters.
    $extra_parameters = [
      'email' => 'test@example.com',
      // The access check does not execute validations on the hash.
      'hash' => 'random',
    ];
    yield [
      'oe_subscriptions_anonymous.subscription_request.confirm',
      $extra_parameters,
    ];

    yield [
      'oe_subscriptions_anonymous.subscription_request.cancel',
      $extra_parameters,
    ];
  }

}
