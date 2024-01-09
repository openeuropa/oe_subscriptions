<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions\Functional;

/**
 * Tests the user subscriptions page.
 */
class UserSubscriptionsPageTest extends UserSubscriptionsPageTestBase {

  /**
   * Tests the subscriptions page.
   */
  public function testFlagsList(): void {
    $user_one = $user_two = FALSE;
    $fn_create_users = function () use (&$user_one, &$user_two) {
      $role = $this->drupalCreateRole([
        'flag subscribe_page',
        'unflag subscribe_page',
        'flag subscribe_article',
        'unflag subscribe_article',
        'flag generic',
        'unflag generic',
        'flag subscribe_foo',
        'unflag subscribe_foo',
        'flag subscribe_bar',
        'unflag subscribe_bar',
        'view test entity',
        'access content',
      ]);
      $user_one = $this->drupalCreateUser([], NULL, FALSE, ['roles' => [$role]]);
      $user_two = $this->drupalCreateUser([], NULL, FALSE, ['roles' => [$role]]);

      return [$user_one, $user_two];
    };
    $fn_go_to_page = function ($user) {
      $this->drupalLogin($user);
      $this->drupalGet("/user/{$user->id()}/subscriptions");
    };
    $this->doTestFlagsList($fn_create_users, $fn_go_to_page);

    // The subscription page is not accessible for anonymous users.
    $this->drupalGet("/user/{$user_one->id()}/subscriptions");
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(403);

    // Users can access the subscription page only if they have edit access
    // on the account.
    $this->drupalLogin($user_two);
    $this->drupalGet("/user/{$user_one->id()}/subscriptions");
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($user_one);
    $this->drupalGet($user_one->toUrl());
    // The subscriptions page is placed as tab in the dedicated block.
    $link = $assert_session->elementExists('css', '#block-local-tasks')->findLink('Subscriptions');
    $this->assertNotEmpty($link);
    $link->click();
    $table = $assert_session->elementExists('css', 'table.user-subscriptions');
    $this->assertCount(4, $this->getTableSectionRows($table, 'tbody'));
  }

  /**
   * Tests the user preferences fields.
   */
  public function testUserPreferences(): void {
    $fn_get_path = function ($user) {
      $this->drupalLogin($user);
      return "/user/{$user->id()}/subscriptions";
    };

    $this->doTestUserPreferences($this->drupalCreateUser(), $fn_get_path);
  }

  /**
   * Tests the form configuration.
   */
  public function testFormConfiguration(): void {
    $fn_get_path = function ($user) {
      $this->drupalLogin($user);
      return "/user/{$user->id()}/subscriptions";
    };

    $this->doTestFormConfiguration($this->drupalCreateUser(), $fn_get_path);
  }

}
