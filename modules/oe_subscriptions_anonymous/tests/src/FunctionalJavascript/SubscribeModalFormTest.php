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
   * The flag.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create an article content type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Create a flag.
    $this->flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'label' => 'Subscribe article',
      'entity_type' => 'node',
      'bundles' => ['article'],
      'link_type' => 'reload',
      'global' => FALSE,
    ]);
    // Create the node.
    $this->node = Node::create([
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
    $this->node->save();
  }

  /**
   * Tests that subscribe link open a modal.
   */
  public function testModalForm(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();
    $form_selector = "form.oe-subscriptions-anonymous-subscribe-form";
    // Got to node page with subscription.
    $this->drupalGet('node/' . $this->node->id());
    // Click subscribe link.
    $this->clickLink('Anonymous Subscribe');
    $assert_session->assertWaitOnAjaxRequest();
    $this->createScreenshot("./test.jpg");
    // The form is shown.
    $assert_session->elementExists('css', $form_selector);
    // E-mail.
    $this->assertTrue($assert_session->fieldEnabled('email')->hasAttribute('required'));
    // Terms and conditions.
    $this->assertTrue($assert_session->fieldEnabled('accept_terms')->hasAttribute('required'));
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
    // Click subscribe link again.
    $this->clickLink('Anonymous Subscribe');
    $assert_session->assertWaitOnAjaxRequest();
    // No thanks cancel.
    $cancel->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementNotExists('css', $form_selector);
  }

}
