<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions\Trait\RouteAccessTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Route subscriber tests.
 */
class RouteSubscriberTest extends KernelTestBase {

  use UserCreationTrait;
  use FlagCreateTrait;
  use RouteAccessTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'filter',
    'flag',
    'message',
    'message_notify',
    'message_subscribe',
    'message_subscribe_ui',
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
    $this->installEntitySchema('entity_test_with_bundle');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['filter', 'flag', 'message_subscribe', 'user']);

    EntityTestBundle::create(['id' => 'page'])->save();
  }

  /**
   * Tests that the message_subscribe routes are disabled.
   */
  public function testDisabledRoutes() {
    $user = $this->createUser(['administer message subscribe']);
    $flag = $this->createFlagFromArray([
      'id' => 'subscribe_page',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['page'],
    ]);
    $page = EntityTestWithBundle::create([
      'type' => 'page',
    ]);
    $page->save();

    // In order to check routes we need the current user with a flagged entity.
    $this->container->get('flag')->flag($flag, $page, $user);
    $this->assertTrue($flag->isFlagged($page, $user));
    $this->container->get('current_user')->setAccount($user);

    // Check that the given routes work.
    $this->assertTrue($this->checkRouteAccess('message_subscribe_ui.tab', [$user], $user));
    $this->assertTrue($this->checkRouteAccess('message_subscribe_ui.tab.flag', [$user, $flag], $user));

    // After enabling the module the routes return an access denied.
    $this->enableModules(['oe_subscriptions']);
    $this->assertFalse($this->checkRouteAccess('message_subscribe_ui.tab', [$user], $user));
    $this->assertFalse($this->checkRouteAccess('message_subscribe_ui.tab.flag', [$user, $flag], $user));
  }

}
