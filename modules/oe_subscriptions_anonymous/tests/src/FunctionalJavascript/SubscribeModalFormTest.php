<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
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
      'label' => 'Subscribe article',
      'entity_type' => 'node',
      'bundles' => ['article'],
      'link_type' => 'reload',
      'global' => FALSE,
    ]);
    // Create the node.
    $node = Node::create([
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

    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();
    $form_selector = "form.oe-subscriptions-anonymous-subscribe-form";
    // Got to node page with subscription.
    $this->drupalGet('node/' . $node->id());
    // Click subscribe link.
    $this->clickLink($flag->label());
    $assert_session->assertWaitOnAjaxRequest();
    // The form is shown.
    $assert_session->elementExists('css', $form_selector);
    // E-mail.
    $email = $assert_session->fieldEnabled('email');
    $this->assertTrue($email->isVisible());
    $this->assertTrue($email->hasAttribute('required'));
    // Terms and conditions.
    $terms = $assert_session->fieldEnabled('accept_terms');
    $this->assertTrue($terms->isVisible());
    $this->assertTrue($terms->hasAttribute('required'));
    // Suscribe.
    $subscribe = $page->find('css', 'button.button.form-submit');
    $this->assertTrue($subscribe->isVisible());
    // No thanks.
    $cancel = $page->find('css', 'button.button.dialog-cancel');
    $this->assertTrue($cancel->isVisible());
    // Close modal.
    $close = $page->find('css', 'button.ui-dialog-titlebar-close');
    $close->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', $form_selector);
    // Click subscribe link again.
    $this->clickLink($flag->label());
    $assert_session->assertWaitOnAjaxRequest();
    // No thanks cancel.
    $cancel->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', $form_selector);
  }

}
