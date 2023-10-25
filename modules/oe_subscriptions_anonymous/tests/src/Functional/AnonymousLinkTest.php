<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Anonymous link test.
 */
class AnonymousLinkTest extends BrowserTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'flag',
    'field_ui',
    'oe_subscriptions',
    'oe_subscriptions_anonymous',
  ];

  /**
   * A user with Flag admin rights.
   *
   * @var AccountInterface
   */
  protected $adminUser;

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
   * Tests that the created flag is related to a content type 'article'.
   */
  public function testAnonymousLink(): void {
    $session = $this->getSession();
    $page_session = $session->getPage();
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Create a page content type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);
    // Create a flag.
    $flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'label' => 'Subscribe article',
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);
    // Create a admin user.
    $adminUser = $this->createUser([
      'administer flags',
      'administer flagging display',
      'administer flagging fields',
      'administer node display',
      'administer modules',
      'administer nodes',
      'create article content',
      'edit any article content',
      'delete any article content',
    ]);
    // Create the node.
    $article = $this->drupalCreateNode([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ],
      ],
      'type' => 'article',
      'title' => $this->randomMachineName(8),
      'uid' => $adminUser->id(),
      'status' => 1,
      'promote' => 0,
      'sticky' => 0,
    ]);
    $article->save();
    // Create the node.
    $page = $this->drupalCreateNode([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ],
      ],
      'type' => 'page',
      'title' => $this->randomMachineName(8),
      'uid' => $adminUser->id(),
      'status' => 1,
      'promote' => 0,
      'sticky' => 0,
    ]);
    $page->save();
    // Link is present for anonymous.
    $this->drupalGet('node/' . $article->id());
    $href = '/subscribe/' . $flag->id() . '/' . $article->id();
    $this->assertTrue($page_session->findLink($flag->label())->hasAttribute('href', $href));
    // Link is present is not present in page for anonymous.
    $this->drupalGet('node/' . $page->id());
    $this->assertFalse($page_session->hasLink($flag->label()));
    // Link is present after adding page to bundles in flag.
    $flag->set('bundles', [
      'article',
      'page',
    ])->save();
    $this->drupalGet('node/' . $page->id());
    $href = '/subscribe/' . $flag->id() . '/' . $page->id();
    $this->assertTrue($page_session->findLink($flag->label())->hasAttribute('href', $href));
    // Link is not displayed for admin.
    $this->drupalLogin($adminUser);
    $this->drupalGet('node/' . $article->id());
    $this->assertFalse($page_session->hasLink($flag->label()));
  }

}
