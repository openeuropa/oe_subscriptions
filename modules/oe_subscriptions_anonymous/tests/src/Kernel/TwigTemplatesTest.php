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
    $this->assertAnonymousLink(
      [
        '#title' => 'Link to home',
        '#url' => $front_url,
      ],
      'Link to home',
      [
        'href' => $front_url->toString(),
      ]
    );

    // Test link with title and external url.
    $this->assertAnonymousLink(
      [
        '#title' => 'Link to Drupal',
        '#url' => 'https://www.drupal.org',
      ],
      'Link to Drupal',
      [
        'href' => 'https://www.drupal.org',
      ]
    );

    // Test link with no text.
    $this->assertAnonymousLink(
      [
        '#url' => $front_url,
      ],
      '',
      [
        'href' => $front_url->toString(),
      ]
    );

    // Test link with attributes.
    $this->assertAnonymousLink(
      [
        '#title' => 'Link to test',
        '#url' => 'internal:/test',
        '#attributes' => [
          'target' => '_blank',
          'class' => ['class-1', 'class-2'],
        ],
      ],
      'Link to test',
      [
        'href' => '/test',
        'target' => '_blank',
        'class' => 'class-1 class-2',
      ]
    );
  }

  /**
   * Asserts that an anonymous link template is rendered with the given values.
   *
   * @param array $variables
   *   The theme variables.
   * @param string $expected_text
   *   The displayed text.
   * @param array $expected_attributes
   *   The element attributes.
   */
  protected function assertAnonymousLink(array $variables, string $expected_text, array $expected_attributes): void {
    $values = ['#theme' => 'oe_subscriptions_anonymous_link'] + $variables;
    $html = (string) $this->container->get('renderer')->renderRoot($values);
    $crawler = new Crawler($html);
    $link = $crawler->filter('a');

    $this->assertEquals($expected_text, $link->text());

    // Retrieve all attributes present in the node.
    $attributes = array_map(static fn($attr) => $attr->value, iterator_to_array($link->getNode(0)->attributes));
    $this->assertEquals($expected_attributes, $attributes);
  }

}
