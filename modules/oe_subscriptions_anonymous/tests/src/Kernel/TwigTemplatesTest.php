<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Core\Url;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Test twig templates.
 */
class TwigTemplatesTest extends KernelTestBase {

  /**
   * Tests the anonymous subscribe link template.
   */
  public function testLinkTemplate(): void {
    $front_url = Url::fromRoute('<front>');

    // Test link with title and URL.
    $this->assertAnonymousLink('Link to front', Url::fromRoute('<front>'));

    // Test link with title and string.
    $this->assertAnonymousLink('Link to front', '/test');

    // Test link with no href.
    $this->assertAnonymousLink('Link without URL');

    // Test link with no text.
    $this->assertAnonymousLink('', $front_url);

    // Test link with target '_blank'.
    $this->assertAnonymousLink('Link with target', $front_url, ['target' => '_blank']);

    // Test link with attributes.
    $this->assertAnonymousLink('Link with attributes', $front_url, [
      'class' => ['class-1', 'class-2'],
      'attribute-2' => 'attribute-2-value',
    ]);
  }

  /**
   * Asserts that an anonymous link template is rendered with the given values.
   *
   * @param string $expected_text
   *   The displayed text.
   * @param string|Url $expected_route
   *   The destination.
   * @param array $expected_attributes
   *   The attributes.
   */
  protected function assertAnonymousLink(string $expected_text = '', string|Url $expected_route = '', array $expected_attributes = []): void {
    $values = [
      '#theme' => 'oe_subscriptions_anonymous_link',
      '#title' => $expected_text,
      '#url' => $expected_route,
      '#attributes' => $expected_attributes,
    ];
    $html = (string) $this->container->get('renderer')->renderRoot($values);
    $crawler = new Crawler($html);
    $link = $crawler->filter('a');

    // The href is another attribute we can check together with the expected.
    if (!empty($expected_route)) {
      if ($expected_route instanceof Url) {
        $expected_route = $expected_route->toString();
      }
      $expected_attributes['href'] = $expected_route;
    }

    $this->assertEquals($expected_text, $link->text());

    // Retrieve all attributes present in the node.
    $attributes = array_map(static fn($attr) => $attr->value, iterator_to_array($link->getNode(0)->attributes));
    $this->assertEquals($expected_attributes, $attributes);

  }

}
