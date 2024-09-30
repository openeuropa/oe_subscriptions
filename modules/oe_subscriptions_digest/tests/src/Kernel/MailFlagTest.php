<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_digest\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\oe_subscriptions\FlagHelper;

/**
 * Class to test mail flag.
 */
class MailFlagTest extends KernelTestBase {

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
    // Make sure our test imported correctly the configuration.
    $this->assertEquals('email_', FlagHelper::getFlagPrefix('message_subscribe_email'));
    $this->assertEquals('subscribe_', FlagHelper::getFlagPrefix('message_subscribe'));

    // Create a flag with wrong prefix.
    $flag_storage = $this->container->get('entity_type.manager')->getStorage('flag');
    $this->createFlagFromArray(['id' => 'another_flag']);
    $this->assertEquals([
      'another_flag',
      'email_node',
      'email_user',
      'subscribe_node',
      'subscribe_user',
    ], array_keys($flag_storage->loadMultiple()));

    // Create a flag and assert that is mirrored.
    $flag = $this->createFlagFromArray(['id' => 'subscribe_content']);
    $this->assertNotNull($flag_storage->load('email_content'));

    // Delete a flag and assert that the cloned version is deleted too.
    $flag->delete();
    $this->assertNull($flag_storage->load('email_content'));

    // Flags that are syncing (e.g. when installing or importing config) are
    // not mirrored.
    $this->createFlagFromArray([
      'id' => 'subscribe_syncing',
      'isSyncing' => TRUE,
    ]);
    $this->assertEquals([
      'another_flag',
      'email_node',
      'email_user',
      'subscribe_node',
      'subscribe_user',
      'subscribe_syncing',
    ], array_keys($flag_storage->loadMultiple()));

    // Mark a flag as part of a synchronisation operation. The matching email
    // flag should not be deleted.
    /** @var \Drupal\flag\FlagInterface $user_flag */
    $user_flag = $flag_storage->load('subscribe_user');
    $user_flag->setSyncing(TRUE);
    $user_flag->delete();
    $this->assertEquals([
      'another_flag',
      'email_node',
      'email_user',
      'subscribe_node',
      'subscribe_syncing',
    ], array_keys($flag_storage->loadMultiple()));

    // Create a subscribe flag for which the corresponding email flag already
    // exists. No extra flags will be created.
    $this->createFlagFromArray(['id' => 'subscribe_user']);
    $this->assertEquals([
      'another_flag',
      'email_node',
      'email_user',
      'subscribe_node',
      'subscribe_syncing',
      'subscribe_user',
    ], array_keys($flag_storage->loadMultiple()));

    // Set the email flag prefix as empty. The prefix is required for flag
    // mirroring.
    $email_config = \Drupal::configFactory()->getEditable('message_subscribe_email.settings');
    $email_config->set('flag_prefix', '')->save();

    // Mirrored flags won't be created nor deleted.
    $this->createFlagFromArray(['id' => 'subscribe_test']);
    $this->assertEquals([
      'another_flag',
      'email_node',
      'email_user',
      'subscribe_node',
      'subscribe_syncing',
      'subscribe_test',
      'subscribe_user',
    ], array_keys($flag_storage->loadMultiple()));
    $flag_storage->load('subscribe_node')->delete();
    $this->assertEquals([
      'another_flag',
      'email_node',
      'email_user',
      'subscribe_syncing',
      'subscribe_test',
      'subscribe_user',
    ], array_keys($flag_storage->loadMultiple()));

    // The email prefix is used for the flag prefix.
    $email_config->set('flag_prefix', 'custom')->save();
    $this->createFlagFromArray(['id' => 'subscribe_article']);
    $this->assertEquals([
      'another_flag',
      'custom_article',
      'email_node',
      'email_user',
      'subscribe_article',
      'subscribe_syncing',
      'subscribe_test',
      'subscribe_user',
    ], array_keys($flag_storage->loadMultiple()));

    // The subscribe flag prefix is used to match when a mirror flag should be
    // created.
    \Drupal::configFactory()->getEditable('message_subscribe.settings')
      ->set('flag_prefix', 'my_subscribe_prefix')
      ->save();
    $email_config->set('flag_prefix', 'test_email')->save();

    // No mirror is created.
    $this->createFlagFromArray(['id' => 'subscribe_taxonomy']);
    $this->assertEquals([
      'another_flag',
      'custom_article',
      'email_node',
      'email_user',
      'subscribe_article',
      'subscribe_syncing',
      'subscribe_taxonomy',
      'subscribe_test',
      'subscribe_user',
    ], array_keys($flag_storage->loadMultiple()));
    $this->createFlagFromArray(['id' => 'my_subscribe_prefix_media']);
    $this->assertEquals([
      'another_flag',
      'custom_article',
      'email_node',
      'email_user',
      'my_subscribe_prefix_media',
      'subscribe_article',
      'subscribe_syncing',
      'subscribe_taxonomy',
      'subscribe_test',
      'subscribe_user',
      'test_email_media',
    ], array_keys($flag_storage->loadMultiple()));
  }

}
