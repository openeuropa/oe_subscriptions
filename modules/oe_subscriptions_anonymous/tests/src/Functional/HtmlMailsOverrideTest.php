<?php

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

/**
 * Test overrides integrated in HTML mails.
 */
class HtmlMailsOverrideTest extends HtmlMailsTestBase {

  /**
   * Test mailer override form and policies modification.
   */
  public function testOverride() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->adminUser);

    // Check the override info page with subscriptions overrides.
    $expected = [
      ['Anonymous subscriptions', 'Disabled', '', 'Enable'],
      ['*All*', '', '', 'Enable & import'],
    ];
    $this->drupalGet('admin/config/system/mailer/override');
    $this->checkOverrideInfo($expected);

    \Drupal::service('symfony_mailer.override_manager')->action('oe_subscriptions_anonymous', 'enable');

    // Check the override info page again.
    $this->drupalGet('admin/config/system/mailer/override');
    $expected[0][1] = 'Enabled';
    $expected[0][3] = 'Disable';
    $this->checkOverrideInfo($expected);

    // Test subscription confirm mail policies.
    $this->drupalGet('admin/config/system/mailer/policy/oe_subscriptions_anonymous.subscription_create');
    $body_field = $assert_session->fieldExists('edit-config-email-body-content-value');
    $subject_field = $assert_session->fieldExists('edit-config-email-subject-value');
    // Check default content with available variables.
    $this->assertEquals(
      'Confirm your subscription to {{ entity_label }}',
      $subject_field->getValue()
    );
    $this->assertEquals(
      "Thank you for showing interest in keeping up with the updates for {{ entity_link }}!<br>
Click the following link to confirm your subscription: {{ confirm_link }}<br>
If you no longer wish to subscribe, click on the link bellow: {{ cancel_link }}<br>
If you didn't subscribe to these updates or you're not sure why you received this e-mail, you can delete it.
You will not be subscribed if you don't click on the confirmation link above.",
      $body_field->getValue()
    );
    // Check modification of subject and body with new values.
    $subject_field->setValue('Anonymous subscriptions confirm subscription subject override.');
    $body_field->setValue('Anonymous subscriptions confirm subscription body override.');
    $assert_session->buttonExists('Save')->press();
    $this->drupalLogout();

    $this->drupalGet($this->article->toUrl());
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

    // Test subscriptions page mail policies.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/mailer/policy/oe_subscriptions_anonymous.user_subscriptions_access');
    // Check default content with available variables.
    $this->assertEquals(
      'Access your subscriptions page on {{ site_url }}',
      $subject_field->getValue()
    );
    $this->assertEquals(
      "You are receiving this e-mail because you requested access to your subscriptions page on {{ site_link }}.<br>
Click the following link to access your subscriptions page: {{ subscriptions_page_link }}<br>
If you didn't request access to your subscriptions page or you're not sure why you received this e-mail, you can delete it.",
      $body_field->getValue()
    );
    // Check modification of subject and body with new values.
    $subject_field->setValue('Anonymous subscriptions access subscriptions page subject override.');
    $body_field->setValue('Anonymous subscriptions access subscriptions page body override.');
    $assert_session->buttonExists('Save')->press();
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
