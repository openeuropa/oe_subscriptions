<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the subscription configuration.
 */
class TemplateLinkTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_subscriptions_anonymous',
    'oe_subscriptions_anonymous_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the configuration for the terms link in the subscriptions form.
   */
  public function testLinkTheme(): void {
    $assert_session = $this->assertSession();
    $this->drupalGet('/oe-subscriptions-anonymous-test/template-link-test');

    // Test basic link with title and URL.
    $link_front = $assert_session->elementExists('xpath', '//a[contains(., "Link to front")]');
    $this->assertEquals($link_front->getAttribute('href'), Url::fromRoute('<front>')->toString());

    // Test basic link with no href.
    $link_href = $assert_session->elementExists('xpath', '//a[contains(., "Link without URL")]');
    $this->assertNull($link_href->getAttribute('href'));

    // Test basic link with attributes.
    $link_attributes = $assert_session->elementExists('xpath', '//a[contains(., "Link with attributtes")]');
    $this->assertEquals($link_attributes->getAttribute('class'), 'class-1 class-2');
    $this->assertEquals($link_attributes->getAttribute('attribute-2'), 'attribute-2-value');

    // Test basic link with attached library.
    $link_attributes = $assert_session->elementExists('xpath', '//a[contains(., "Link with attached")]');
    $assert_session->responseContains('build/core/misc/ajax.js');
  }

}
