<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Url;
use Drupal\oe_subscriptions_anonymous\TokenManagerInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Tests the subscription request routes.
 */
class SubscriptionRequestRoutesTest extends BrowserTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime_testing',
    'node',
    'oe_subscriptions_anonymous',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
  }

  /**
   * Tests the subscription request confirm/cancel routes with invalid tokens.
   *
   * Successful scenarios are already covered by ::testForm(), and access tests
   * by the dedicated kernel test.
   *
   * @dataProvider subscriptionRequestInvalidTokenDataProvider
   */
  public function testSubscriptionRequestInvalidToken(string $route_name): void {
    // Create flags.
    $flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_short' => 'Subscribe to this article',
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    // Create some test nodes.
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);

    // Test with a non-existing token.
    $url = Url::fromRoute($route_name, [
      'flag' => $flag->id(),
      'entity_id' => $article->id(),
      'email' => 'test@example.com',
      'hash' => $this->randomMachineName(),
    ]);
    $this->drupalGet($url);
    $assert_session = $this->assertSession();
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');

    /** @var \Drupal\oe_subscriptions_anonymous\TokenManagerInterface $token_manager */
    $token_manager = \Drupal::service('oe_subscriptions_anonymous.token_manager');
    $scope = $token_manager::buildScope(TokenManagerInterface::TYPE_SUBSCRIBE, [
      $flag->id(),
      $article->id(),
    ]);

    // Test with a token generated for another e-mail.
    $url = Url::fromRoute($route_name, [
      'flag' => $flag->id(),
      'entity_id' => $article->id(),
      'email' => 'test@example.com',
      'hash' => $token_manager->get('another@example.com', $scope),
    ]);
    $this->drupalGet($url);
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');

    /** @var \Drupal\datetime_testing\TestTimeInterface $time */
    $time = \Drupal::time();
    $time->freezeTime();
    // Set the current time in the past so that the token generated will be
    // expired when time is unfrozen.
    $time->setTime(1701471031);

    $url = Url::fromRoute($route_name, [
      'flag' => $flag->id(),
      'entity_id' => $article->id(),
      'email' => 'test@example.com',
      'hash' => $token_manager->get('test@example.com', $scope),
    ]);

    $time->unfreezeTime();
    $time->resetTime();

    $this->drupalGet($url);
    $assert_session->statusMessageContains('You have tried to use a link that has been used or is no longer valid. Please request a new link.', 'warning');
  }

  /**
   * Data provider for ::testSubscriptionRequestInvalidToken().
   *
   * @return iterable
   *   The test scenarios.
   */
  public function subscriptionRequestInvalidTokenDataProvider(): iterable {
    yield ['oe_subscriptions_anonymous.subscription_request.confirm'];
    yield ['oe_subscriptions_anonymous.subscription_request.cancel'];
  }

}
