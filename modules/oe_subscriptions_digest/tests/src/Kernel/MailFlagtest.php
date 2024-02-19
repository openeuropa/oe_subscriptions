<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_digest\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Class to test mail flag.
 */
class MailFlagtest extends KernelTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'flag',
    'message_subscribe',
    'message_notify',
    'message_subscribe_email',
    'node',
    'oe_subscriptions_digest',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('flagging');
    $this->installConfig([
      'message_subscribe',
    ]);
    $this->installConfig([
      'message_subscribe_email',
    ]);
  }

  /**
   * Tests duplication of subscribe flags with mail prefix.
   */
  public function testFlagMirroring(): void {
    // Create a flag with wrong prefix.
    $flagStorage = $this->container->get('entity_type.manager')->getStorage('flag');
    $this->createFlagFromArray(['id' => 'another_flag']);
    $this->assertEquals([
      'another_flag',
      'email_node',
      'email_user',
      'subscribe_node',
      'subscribe_user',
    ], array_keys($flagStorage->loadMultiple()));

    // Create a flag and assert that is mirrored.
    $flag = $this->createFlagFromArray(['id' => 'subscribe_content']);
    $this->assertNotNull($flagStorage->load('email_content'));

    // Delete a flag and assert that the cloned version is deleted too.
    $flag->delete();
    $this->assertNull($flagStorage->load('email_content'));
  }

}
