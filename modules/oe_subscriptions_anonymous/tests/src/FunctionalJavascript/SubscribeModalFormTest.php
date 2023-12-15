<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\AssertMailTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\MessageTemplateTrait;

/**
 * Modal form test.
 */
class SubscribeModalFormTest extends WebDriverTestBase {

  use AssertMailTrait;
  use FlagCreateTrait;
  use MessageTemplateTrait;

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
   * Tests that subscribe link open a modal.
   */
  public function testModalForm(): void {
    // Create an article content type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Create a flag.
    $link_text = 'Subscribe to this article';
    $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_short' => $link_text,
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    // Create the node.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();
    // Using ui-dialog parent of modal-form,
    // Submit buttons and close in siblings.
    $modal_selector = '.ui-dialog';

    $this->drupalGet($node->toUrl());
    // Click subscribe link.
    $this->clickLink($link_text);
    $assert_session->waitForElement('css', $modal_selector);
    // The modal wrapper.
    $modal = $assert_session->elementExists('css', $modal_selector);
    $this->assertEquals(
      sprintf('Subscribe to %s', $node->label()),
      $assert_session->elementExists('css', '.ui-dialog-title', $modal)->getText()
    );
    // Assert the fields of the modal.
    $mail_label = 'Your e-mail';
    $terms_label = 'I have read and agree with the data protection terms.';
    $mail_field = $assert_session->fieldExists($mail_label, $modal);
    $terms_field = $assert_session->fieldExists($terms_label, $modal);
    // Get button pane with submit buttons.
    $button_pane = $assert_session->elementExists('css', '.ui-dialog-buttonpane', $modal);

    // Verify that mail and terms field are marked as required.
    $this->disableNativeBrowserRequiredFieldValidation();
    $assert_session->buttonExists('Subscribe me', $button_pane)->press();
    $assert_session->assertWaitOnAjaxRequest();
    // The modal was not closed, and the errors are rendered inside it.
    $assert_session->elementTextContains('css', $modal_selector, "$mail_label field is required.");
    $assert_session->elementTextContains('css', $modal_selector, "$terms_label field is required.");

    // Test close modal button.
    $this->drupalGet($node->toUrl());
    $this->clickLink($link_text);
    $assert_session->waitForElement('css', $modal_selector);
    $modal->find('css', 'button.ui-dialog-titlebar-close')->press();
    $assert_session->elementNotExists('css', $modal_selector);
    $assert_session->statusMessageNotExists();

    // Test cancel button, filled data.
    $this->clickLink($link_text);
    $assert_session->waitForElement('css', $modal_selector);
    $mail_field->setValue('test@test.com');
    $terms_field->check();
    $assert_session->buttonExists('No thanks', $button_pane)->press();
    $assert_session->elementNotExists('css', $modal_selector);
    $assert_session->statusMessageNotExists();
    // No e-mails have been sent.
    $this->assertEmpty($this->getMails());

    // Test submit.
    $this->clickLink($link_text);
    $assert_session->waitForElement('css', $modal_selector);
    $mail_field->setValue('test@test.com');
    $terms_field->check();
    $assert_session->buttonExists('Subscribe me', $button_pane)->press();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', $modal_selector);
    $this->confirMessageExists();
    $this->assertCount(1, $this->getMails());
    $this->assertMail('to', 'test@test.com');
  }

  /**
   * Disables the native browser validation for required fields.
   */
  protected function disableNativeBrowserRequiredFieldValidation() {
    $this->getSession()->executeScript("jQuery(':input[required]').prop('required', false);");
  }

}
