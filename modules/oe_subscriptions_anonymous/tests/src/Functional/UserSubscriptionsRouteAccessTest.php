<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests access to the anonymous user subscriptions route.
 */
class UserSubscriptionsRouteAccessTest extends BrowserTestBase {

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
   * Test special or invalid scenarios where a message is rendered.
   *
   * This is kept into a separate class to avoid installing the datetime_testing
   * module in standard scenarios tests.
   */
  public function testNonValidScenarios(): void {
    // The form can be accessed only by anonymous users.
    $this->drupalLogin($this->drupalCreateUser());
    $this->drupalGet('/user/subscriptions');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(403);
    $this->drupalLogout();

    // Test with invalid tokens.
    $route_name = 'oe_subscriptions_anonymous.user_subscriptions.view';
    $this->drupalGet(Url::fromRoute($route_name, [
      'email' => 'test@example.com',
      'token' => $this->randomMachineName(),
    ]));
    $assert_session->statusMessageContains('Your token is either invalid or it has expired. Please request a new token to access your subscriptions.', 'error');

    // Test with a token generated for another e-mail.
    $token_manager = \Drupal::service('oe_subscriptions_anonymous.token_manager');
    $scope = 'user_subscriptions_page';
    $this->drupalGet(Url::fromRoute($route_name, [
      'email' => 'test@example.com',
      'token' => $token_manager->get('another@example.com', $scope),
    ]));
    $assert_session->statusMessageContains('Your token is either invalid or it has expired. Please request a new token to access your subscriptions.', 'error');

    /** @var \Drupal\datetime_testing\TestTimeInterface $time */
    $time = \Drupal::time();
    $time->freezeTime();
    // Set the current time in the past so that the token generated will be
    // expired when time is unfrozen.
    $time->setTime(1701471031);

    $url = Url::fromRoute($route_name, [
      'email' => 'test@example.com',
      'token' => $token_manager->get('another@example.com', $scope),
    ]);

    $time->unfreezeTime();
    $time->resetTime();

    $this->drupalGet($url);
    $assert_session->statusMessageContains('Your token is either invalid or it has expired. Please request a new token to access your subscriptions.', 'error');

    // Existing users are sent to the login page.
    $user = $this->drupalCreateUser();
    $this->drupalGet(Url::fromRoute($route_name, [
      'email' => $user->getEmail(),
      'token' => $token_manager->get($user->getEmail(), $scope),
    ]));
    $assert_session->statusMessageContains('It seems that you have an account on this website. Please login to manage your subscriptions.', 'warning');
    $assert_session->addressEquals(Url::fromRoute('user.login', [], [
      'query' => [
        'destination' => Url::fromRoute('oe_subscriptions.user.subscriptions', [
          'user' => $user->id(),
        ])->toString(),
      ],
    ])->setAbsolute()->toString());
  }

}
