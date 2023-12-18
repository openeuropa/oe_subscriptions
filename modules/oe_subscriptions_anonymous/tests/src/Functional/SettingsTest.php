<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Tests the subscription configuration.
 */
class SettingsTest extends BrowserTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
   * Tests the configuration for the terms link in the subscriptions form.
   */
  public function testSettingsForm(): void {
    $this->createFlagFromArray([
      'id' => 'subscribe_all',
      'flag_short' => 'Subscribe',
      'entity_type' => 'node',
      'bundles' => [],
    ]);
    $page = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();
    $user = $this->createUser(['administer anonymous subscriptions']);
    $page_value = $page->label() . ' (' . $page->id() . ')';

    // User without permission can't access the configuration page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.settings'));
    $assert_session->pageTextContains('You are not authorized to access this page.');
    $assert_session->statusCodeEquals(403);

    // Test link configuration for terms page.
    $this->drupalLogin($user);
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.settings'));
    $url_field = $assert_session->fieldExists('Terms page URL');
    // Check that the field is required.
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('Terms page URL field is required.', 'error');
    // Set an internal link value.
    $url_field->setValue($page_value);
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    // The form displays the saved value.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.settings'));
    $this->assertEquals($url_field->getValue(), $page_value);

    // Set external URL for terms page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.settings'));
    $url_field->setValue('https://www.drupal.org/');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');

    // Set invalid links.
    $url_field->setValue('Plain text');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('Manually entered paths should start with one of the following characters: / ? #', 'error');
    $url_field->setValue('www.drupal.org');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('Manually entered paths should start with one of the following characters: / ? #', 'error');
  }

}
