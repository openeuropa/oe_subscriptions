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
    $this->assertAnonymousLink('Link to front', "internal:/test");

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

    if (!$expected_route instanceof Url) {
      $expected_route = Url::fromUri($expected_route);
    }

    // The href is another attribute we can check together with the expected.
    if (!empty($expected_route)) {
      $expected_attributes['href'] = $expected_route->toString();
    }

    $this->assertEquals($expected_text, $link->text());

    // Drupal\Core\Template\Attribute doesn't provide the a way to retrieve
    // individual attribute values as rendered string. To reduce complexity of
    // the operation we simulate the same behavior. See:
    // Drupal\Core\Template\AttributeArray.
    array_walk($expected_attributes, fn(&$value) => $value = is_array($value) ? implode(' ', $value) : $value);

    // Retrieve all attributes present in the node.
    $attributes = array_map(static fn($attr) => $attr->value, iterator_to_array($link->getNode(0)->attributes));
    $this->assertEquals($expected_attributes, $attributes);
  }

}
