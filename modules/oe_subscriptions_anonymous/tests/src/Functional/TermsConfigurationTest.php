<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Tests the subscription configuration.
 */
class TermsConfigurationTest extends BrowserTestBase {

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
  public function testTermsConfiguration(): void {
    // Create flags.
    $this->createFlagFromArray([
      'id' => 'subscribe_all',
      'flag_short' => 'Subscribe',
      'entity_type' => 'node',
      'bundles' => [],
    ]);
    // Create some test nodes.
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
    $page = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);

    $assert_session = $this->assertSession();

    // Assert that the link is not present if the configuration is not set.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => 'subscribe_all',
      'entity_id' => $article->id(),
    ]));
    $assert_session->fieldExists('I have read and agree with the data protection terms.');
    $assert_session->linkNotExists('data protection terms');

    // User without permission can't access the configuration page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.settings'));
    $assert_session->pageTextContains('You are not authorized to access this page.');
    $assert_session->statusCodeEquals(403);

    // Assert user with permissions can manage configuration.
    $user = $this->createUser(['administer anonymous subscriptions']);
    $this->drupalLogin($user);
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.settings'));
    $url_field = $assert_session->fieldExists('Terms page URL');
    $url_field->setValue($page->label() . ' (' . $page->id() . ')');
    $assert_session->buttonExists('Save configuration')->press();

    // Check that the value is preserved.
    $assert_session->statusMessageContains('The configuration options have been saved.');
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.settings'));
    $this->assertEquals($url_field->getValue(), $page->label() . ' (' . $page->id() . ')');
    $this->drupalLogout();

    // The link is present in page.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => 'subscribe_all',
      'entity_id' => $page->id(),
    ]));
    $assert_session->linkExists('data protection terms');

    // The link is present in article.
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => 'subscribe_all',
      'entity_id' => $article->id(),
    ]));
    $assert_session->linkExists('data protection terms');

    // Delete node and check that the field is not present.
    $page->delete();
    $this->drupalGet(Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => 'subscribe_all',
      'entity_id' => $article->id(),
    ]));
    $assert_session->fieldExists('I have read and agree with the data protection terms.');
    $assert_session->linkNotExists('data protection terms');

  }

}
