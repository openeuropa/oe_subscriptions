<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions\Functional;

use Drupal\Core\Url;
use Drupal\filter\Entity\FilterFormat;
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
    'oe_subscriptions',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the configuration for the subscriptions settings form.
   */
  public function testSettingsForm(): void {
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $page = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();
    $user = $this->createUser(['administer subscriptions']);
    $page_value = $page->label() . ' (' . $page->id() . ')';

    // User without permission can't access the configuration page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
    $assert_session->pageTextContains('You are not authorized to access this page.');
    $assert_session->statusCodeEquals(403);

    // Test link configuration for terms page.
    $this->drupalLogin($user);
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
    $url_field = $assert_session->fieldExists('Terms page URL');
    // Check that the field is required.
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('Terms page URL field is required.', 'error');
    // Set an internal link value.
    $url_field->setValue($page_value);
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    // The form displays the saved value.
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
    $this->assertEquals($page_value, $url_field->getValue());

    // Set external URL for terms page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
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

    // Test that the introduction text saved and preserved.
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
    $introduction_text = $assert_session->fieldExists('Introduction text');
    $introduction_text->setValue('Test text.');
    $assert_session->buttonExists('Save configuration')->press();
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
    $this->assertEquals('Test text.', $introduction_text->getValue());

    // Test text formats for introduction text.
    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'roles' => ['authenticated'],
    ])->save();
    FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'roles' => ['authenticated'],
    ])->save();
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
    $subform = $assert_session->elementExists('css', '[data-drupal-selector="edit-introduction-text-format"]');
    $text_format = $assert_session->selectExists('Text format', $subform);
    // Check that the new text format is available.
    $this->assertEquals([
      'plain_text' => 'Plain text',
      'full_html' => 'Full HTML',
      'filtered_html' => 'Filtered HTML',
    ], $this->getOptions($text_format));
    $this->assertEquals('plain_text', $text_format->getValue());
    $text_format->setValue('full_html');
    $assert_session->buttonExists('Save configuration')->press();
    // Test that 'full' option is saved.
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
    $this->assertEquals('full_html', $text_format->getValue());
  }

}
