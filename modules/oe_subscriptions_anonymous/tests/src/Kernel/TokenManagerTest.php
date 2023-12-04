<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\oe_subscriptions_anonymous\TokenManagerInterface;

/**
 * Tests the anonymous subscribe module services.
 */
class TokenManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'decoupled_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('oe_subscriptions_anonymous', ['oe_subscriptions_anonymous_tokens']);
  }

  /**
   * Tests the token manager service.
   */
  public function testTokenManager(): void {
    $time = $this->createMock(TimeInterface::class);
    $request_time = 1701471031;
    $time->method('getRequestTime')
      ->willReturnCallback(function () use (&$request_time) {
        return $request_time;
      });
    $this->container->set('datetime.time', $time);
    /** @var \Drupal\oe_subscriptions_anonymous\TokenManagerInterface $token_service */
    $token_service = $this->container->get('oe_subscriptions_anonymous.token_manager');

    $mail = '123@mail.com';
    // Build scope, get hash and check that is valid.
    $scope = $token_service->buildScope(TokenManagerInterface::TYPE_SUBSCRIBE, ['1', '2']);
    $token = $token_service->get($mail, $scope);
    $this->assertTrue($token_service->isValid($mail, $scope, $token));

    // Generate a new token for the same e-mail and scope.
    $new_token = $token_service->get($mail, $scope);
    // The old token is not valid anymore.
    $this->assertFalse($token_service->isValid($mail, $scope, $token));
    // Only latest works.
    $this->assertTrue($token_service->isValid($mail, $scope, $new_token));

    // Delete the token.
    $this->assertTrue($token_service->delete($mail, $scope));
    // The token is not valid anymore.
    $this->assertFalse($token_service->isValid($mail, $scope, $new_token));

    // Token lasts for an hour.
    $token = $token_service->get($mail, $scope);
    $request_time += 86400;
    $this->assertTrue($token_service->isValid($mail, $scope, $token));
    $request_time += 1;
    $this->assertFalse($token_service->isValid($mail, $scope, $token));

    // Create new, set time again and delete expired.
    $token = $token_service->get('456@mail.com', $scope);
    $this->assertTrue($token_service->isValid('456@mail.com', $scope, $token));
    $request_time += 86401;
    $token_service->deleteExpired();
    // We can't rely on isValid() given performs two checks, if it exists and
    // it's not expired.
    // The delete() operation returns true only if a token was deleted,
    // regardless of expiration date.
    $this->assertFalse($token_service->delete('456@mail.com', $scope));
    $this->assertFalse($token_service->delete($mail, $scope));

    // Test that tokens generated with different e-mails are different.
    $token = $token_service->get('one@example.com', $scope);
    $token_service->get('two@example.com', $scope);
    $this->assertFalse($token_service->isValid('two@example.com', $scope, $token));
    $this->assertTrue($token_service->isValid('one@example.com', $scope, $token));

    // Test that different tokens generated with same e-mail but different
    // scope work correctly.
    $mail = 'same@example.com';
    $token_scope_a = $token_service->get($mail, 'a');
    $token_scope_b = $token_service->get($mail, 'b');
    $this->assertTrue($token_service->isValid($mail, 'a', $token_scope_a));
    $this->assertTrue($token_service->isValid($mail, 'b', $token_scope_b));
    $this->assertFalse($token_service->isValid($mail, 'a', $token_scope_b));
    $this->assertFalse($token_service->isValid($mail, 'b', $token_scope_a));
  }

}
