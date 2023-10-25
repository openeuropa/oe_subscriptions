<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Modal form test.
 */
class SubscribeModalFormTest extends WebDriverTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'flag',
    'oe_subscriptions',
    'oe_subscriptions_anonymous',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that subscribe link open a modal.
   */
  public function testModalForm(): void {
    // Create an article content type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Create a flag.
    $flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'label' => 'Subscribe to article',
      'short_text' => 'Subscribe article',
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    // Create the node.
    $node = $this->drupalCreateNode([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ],
      ],
      'type' => 'article',
      'title' => $this->randomMachineName(8),
      'uid' => 0,
      'status' => 1,
      'promote' => 0,
      'sticky' => 0,
    ]);
    $node->save();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    // Using ui-dialog parent of modal-form,
    // Submit buttons and close in siblings.
    $modal_select = '.ui-dialog';
    // Got to node page with subscription.
    $this->drupalGet($page->toUrl());
    // Click subscribe link.
    $link_text = $flag->getShortText('flag');
    $this->clickLink($link_text);
    $assert_session->assertWaitOnAjaxRequest();
    // The modal wrapper.
    $modal = $page->find('css', $modal_select);
    // Find all elements.
    $mail_label = 'Your e-mail';
    $terms_label = 'I have read and agree with the data protection terms.';
    // Using xpath, findButton get input (hidden) instead button tags.
    // Submit has no specific class to select thourgh CSS.
    $button_pane = $assert_session->elementExists('css', '.ui-dialog-buttonpane', $modal);
    // Try to submit, empty form.
    $this->disableNativeBrowserRequiredFieldValidation();
    $assert_session->buttonExists('Subscribe me', $button_pane)->press();
    $this->assertSession()->pageTextContains("$mail_label field is required.");
    $this->assertSession()->pageTextContains("$terms_label field is required.");
    // Test Close modal button.
    $this->drupalGet('node/' . $node->id());
    $this->clickLink($link_text);
    $assert_session->assertWaitOnAjaxRequest();
    $modal->find('css', 'button.ui-dialog-titlebar-close')->press();
    $assert_session->elementNotExists('css', $modal_select);
    // Test cancel button, filled data.
    $this->clickLink($link_text);
    $assert_session->assertWaitOnAjaxRequest();
    $modal->findField($mail_label)->setValue('test@test.com');
    $modal->findField($terms_label)->check();
    $assert_session->buttonExists('No thanks', $button_pane)->press();
    $assert_session->elementNotExists('css', $modal_select);
    // No message no submit.
    $assert_session->statusMessageNotExists();
  }

  /**
   * Disables the native browser validation for required fields.
   */
  protected function disableNativeBrowserRequiredFieldValidation() {
    $this->getSession()->executeScript("jQuery(':input[required]').prop('required', false);");
  }

}
