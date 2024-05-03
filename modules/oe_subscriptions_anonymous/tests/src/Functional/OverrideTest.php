<?php

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

/**
 * Test Mailer overrides.
 *
 * @group symfony_mailer
 */
class OverrideTest extends SymfonyMailerTestBase {

  /**
   * Test mailer override form.
   */
  public function testOverride() {
    $admin_user = $this->drupalCreateUser(['administer mailer', 'use text format email_html']);
    $assert_session = $this->assertSession();
    $this->drupalLogin($admin_user);

    // Check the override info page with subscriptions overrides.
    $expected = [
      ['Anonymous subscriptions', 'Disabled', '', 'Enable'],
      ['Update Manager', 'Disabled', 'Update notification addresses', 'Enable & Import'],
      // phpcs:ignore Drupal.Arrays.Array.LongLineDeclaration
      ['User', 'Disabled', 'Update notification addresses', "User email settings\nWarning: This overrides the default HTML messages with imported plain text versions"],
      ['*All*', '', '', 'Enable & import'],
    ];
    $this->drupalGet('admin/config/system/mailer/override');
    $this->checkOverrideInfo($expected);

    // Force import the user override.
    $this->drupalGet('admin/config/system/mailer/override/oe_subscriptions_anonymous/enable');
    $assert_session->pageTextContains('Are you sure you want to do Enable for override Anonymous subscriptions?');
    $assert_session->pageTextContains('Related Mailer Policy will be reset to default values.');
    $this->submitForm([], 'Enable');

    // Check the override info page again.
    $expected[0][1] = 'Enabled';
    $expected[0][3] = 'Disable';
    $assert_session->pageTextContains('Completed Enable for override Anonymous subscriptions');
    $this->checkOverrideInfo($expected);

    // Check confirm override.
    $this->drupalGet('admin/config/system/mailer/policy/oe_subscriptions_anonymous.subscription_create');
    $this->submitForm([
      'edit-config-email-body-content-value' => 'Anonymous subscriptions confirm subscription body override.',
      'edit-config-email-subject-value' => 'Anonymous subscriptions confirm subscription subject override.',
    ], 'Save');
    $this->drupalLogout();

    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
    $this->drupalGet($article->toUrl());
    $this->clickLink('Subscribe');
    $this->submitForm([
      'Your e-mail' => 'test@test.com',
      'I have read and agree with the data protection terms.' => '1',
    ], 'Subscribe me');
    $this->assertSubscriptionCreateMailStatusMessage();

    $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject('Anonymous subscriptions confirm subscription subject override.');
    $this->assertBodyContains('Anonymous subscriptions confirm subscription body override.');

    // Check access override.
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/config/system/mailer/policy/oe_subscriptions_anonymous.user_subscriptions_access');
    $this->submitForm([
      'edit-config-email-body-content-value' => 'Anonymous subscriptions access subscriptions page body override.',
      'edit-config-email-subject-value' => 'Anonymous subscriptions access subscriptions page subject override.',
    ], 'Save');
    $this->drupalLogout();

    $this->drupalGet('user/subscriptions');
    $this->submitForm(['Your e-mail' => 'test@test.com'], 'Submit');
    $this->assertSubscriptionsPageMailStatusMessage();

    $this->readMail();
    $this->assertTo('test@test.com');
    $this->assertSubject('Anonymous subscriptions access subscriptions page subject override.');
    $this->assertBodyContains('Anonymous subscriptions access subscriptions page body override.');
  }

  /**
   * Checks the symfony_mailer override info page.
   *
   * @param array $expected
   *   Array of expected table cell contents.
   */
  protected function checkOverrideInfo(array $expected) {
    $this->assertSession()->addressEquals('admin/config/system/mailer/override');
    foreach ($this->xpath('//tbody/tr') as $row) {
      $expected_row = array_pop($expected);
      foreach ($row->find('xpath', '/td') as $cell) {
        $expected_cell = array_pop($expected_row);
        $this->assertEquals($expected_cell, $cell->getText());
      }
    }
  }

}
