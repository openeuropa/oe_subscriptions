<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Kernel;

use Drupal\Core\Url;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the templates.
 */
class TemplatesTest extends KernelTestBase {

  /**
   * Tests the anonymous subscribe link template.
   */
  public function testLinkTemplate(): void {
    $front_url = Url::fromRoute('<front>');

    // Test link with title and URL.
    $this->assertAnonymousLink([
      '#title' => 'Link to front',
      '#url' => $front_url,
    ]);

    // Test link with no href.
    $this->assertAnonymousLink([
      '#title' => 'Link without URL',
    ]);

    // Test link with no text.
    $this->assertAnonymousLink([
      '#url' => $front_url,
    ]);

    // Test link with attributes.
    $this->assertAnonymousLink([
      '#title' => 'Link with attributtes',
      '#url' => $front_url,
      '#attributes' => [
        'class' => ['class-1', 'class-2'],
        'attribute-2' => 'attribute-2-value',
      ],
    ]);

    // Test link duplicating button classes.
    $this->assertAnonymousLink([
      '#title' => 'Link with attributtes',
      '#url' => $front_url,
      '#attributes' => [
        'class' => ['button', 'button--small'],
      ],
    ]);
  }

  /**
   * Asserts that an anonymous link template is rendered with the given values.
   *
   * @param array $variables
   *   The expected values.
   */
  protected function assertAnonymousLink(array $variables): void {
    $values = ['#theme' => 'oe_subscriptions_anonymous_link'] + $variables;
    $html = (string) $this->container->get('renderer')->renderRoot($values);
    $crawler = new Crawler($html);
    $link = $crawler->filter('a');

    if (!empty($variables['#title'])) {
      $this->assertEquals($link->text(), $variables['#title']);
    }

    if (!empty($variables['#url']) && $variables['#url'] instanceof Url) {
      $this->assertEquals($link->attr('href'), $variables['#url']->toString());
    }

    if (isset($variables['#attributes'])) {
      foreach ($variables['#attributes'] as $k => $v) {
        if (is_array($v)) {
          $v = implode(' ', $v);
        }
        if ($k === 'class') {
          // For the classes we check that contains the specified elements given
          // we have some predefined classes.
          $this->assertStringContainsString($v, $link->attr($k));
          continue;
        }
        $this->assertEquals($link->attr($k), $v);
      }
    }
    // Button classes are always present.
    $this->assertStringContainsString('button button--small', $link->attr('class'));
  }

}
