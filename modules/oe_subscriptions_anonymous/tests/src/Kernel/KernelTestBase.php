<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Base class for kernel tests.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

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
    'decoupled_auth',
    'path_alias',
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('flagging');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['filter', 'flag', 'message_subscribe', 'user']);
    $this->installEntitySchema('message');
  }

}
