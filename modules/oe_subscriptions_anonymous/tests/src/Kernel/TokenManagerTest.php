<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

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
    $anonymous_storage_service = $this->container->get('oe_subscriptions_anonymous.token_manager');
    $mail = '123@mail.com';
    // Build scope, get hash and check that is valid.
    $scope = $anonymous_storage_service->buildScope(TokenManagerInterface::TYPE_SUBSCRIBE, ['1', '2']);
    $hash = $anonymous_storage_service->get($mail, $scope);
    $this->assertTrue($anonymous_storage_service->isValid($mail, $scope, $hash));
    // Try check with an outdated hash.
    $new_hash = $anonymous_storage_service->get($mail, $scope);
    $this->assertFalse($anonymous_storage_service->isValid($mail, $scope, $hash));
    // Only latest works.
    $this->assertTrue($anonymous_storage_service->isValid($mail, $scope, $new_hash));
    // Delete subscription.
    $hash = $anonymous_storage_service->delete($mail, $scope);
    // Not valid.
    $this->assertFalse($anonymous_storage_service->isValid($mail, $scope, $new_hash));
    // Refresh.
    $hash = $anonymous_storage_service->get($mail, $scope);
    $this->assertTrue($anonymous_storage_service->isValid($mail, $scope, $hash));
    // Set an old date for changed and check expired.
    $this->setSubscriptionChanged($mail, $scope, time() - 90000);
    $this->assertFalse($anonymous_storage_service->isValid($mail, $scope, $hash));
    // Create new, set time again and delete expired.
    $hash = $anonymous_storage_service->get('456@mail.com', $scope);
    $this->assertTrue($anonymous_storage_service->isValid('456@mail.com', $scope, $hash));
    $this->setSubscriptionChanged('456@mail.com', $scope, time() - 90000);
    $anonymous_storage_service->deleteExpired();
    // We can't rely on isValid() given performs two checks, exists and expired.
    // The subscription could exist and be expired, we try to delete it.
    $this->assertFalse($anonymous_storage_service->delete('456@mail.com', $scope));
  }

  /**
   * Sets a subscription changed value.
   *
   * @param string $mail
   *   Subscribing mail.
   * @param string $scope
   *   The entity to subscribe to.
   * @param string $changed
   *   The value we want to set as changed.
   *
   * @return void
   *   No return value.
   */
  private function setSubscriptionChanged(string $mail, string $scope, $changed): void {
    $connection = $this->container->get('database');
    // Update changed setting the changed older than a day ago.
    $connection->update('oe_subscriptions_anonymous_tokens')
      ->fields([
        'changed' => $changed,
      ])
      ->condition('mail', $mail)
      ->condition('scope', $scope)
      ->execute();
  }

}
