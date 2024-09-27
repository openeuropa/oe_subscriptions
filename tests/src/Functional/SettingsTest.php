<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests the subscription configuration.
 */
class SettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
    $assert_session = $this->assertSession();
    $user = $this->createUser(['administer subscriptions']);

    // User without permission can't access the configuration page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions.settings'));
    $assert_session->pageTextContains('You are not authorized to access this page.');
    $assert_session->statusCodeEquals(403);

    // Test that the introduction text is saved.
    $this->drupalLogin($user);
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
