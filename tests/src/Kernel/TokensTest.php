<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\token\Functional\TokenTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Test the Subscriptions tokens.
 */
class TokensTest extends KernelTestBase {

  use TokenTestTrait;
  use UserCreationTrait;
  use NodeCreationTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'flag',
    'message',
    'message_notify',
    'message_subscribe',
    'message_subscribe_ui',
    'node',
    'path_alias',
    'oe_subscriptions',
    'token',
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
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['filter', 'flag', 'message_subscribe', 'user', 'node']);

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
   * Tests user tokens.
   */
  public function testUserTokens(): void {
    $user = $this->createUser();
    $url = Url::fromUserInput("/user/{$user->id()}/subscriptions");

    $this->doSubscriptionsPageTokenTest($url, $user);
  }

  /**
   * Tests the subscriptions page token.
   *
   * @param \Drupal\Core\Url $expected_url
   *   The URL expected.
   * @param \Drupal\user\UserInterface $user
   *   The owner of subscriptions.
   */
  protected function doSubscriptionsPageTokenTest(Url $expected_url, UserInterface $user): void {
    // No user present, token is not generated.
    $this->assertNoTokens('user', [], ['subscriptions-page']);
    // Valid user, token is generated.
    $this->assertTokens('user', ['user' => $user],
     [
       'subscriptions-page' => $expected_url->setAbsolute()->toString(),
       'subscriptions-page:path' => '/' . $expected_url->getInternalPath(),
       'subscriptions-page:alias' => '/' . $expected_url->getInternalPath(),
       'subscriptions-page:absolute' => $expected_url->setAbsolute()->toString(),
       'subscriptions-page:relative' => $expected_url->setAbsolute(FALSE)->toString(),
       'subscriptions-page:brief' => preg_replace(['!^https?://!', '!/$!'], '', $expected_url->setAbsolute()->toString()),
       'subscriptions-page:unaliased' => $expected_url->toString(),
       'subscriptions-page:args:value:0' => explode('/', $expected_url->getInternalPath())[0],
       'subscriptions-page:args:value:1' => explode('/', $expected_url->getInternalPath())[1],
       'subscriptions-page:args:value:2' => explode('/', $expected_url->getInternalPath())[2] ?? NULL,
     ]);

    // Check nested token.
    $type = $this->createContentType();
    $node = $this->createNode([
      'type' => $type->id(),
      'uid' => $user->id(),
    ]);
    $this->assertEquals(
       $expected_url->setAbsolute()->toString(),
       \Drupal::token()->replace('[node:author:subscriptions-page:absolute]', [
         'node' => $node,
       ])
     );
  }

}
