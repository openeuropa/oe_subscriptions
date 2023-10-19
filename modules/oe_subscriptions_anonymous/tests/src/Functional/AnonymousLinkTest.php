<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\node\Entity\Node;
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create an article content type.
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
    $this->flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'label' => 'Subscribe article',
      'entity_type' => 'node',
      'bundles' => ['article'],
      'link_type' => 'reload',
      'global' => FALSE,
    ]);
    // Create a admin user.
    $this->adminUser = $this->createUser([
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
    $this->node = Node::create([
      'body' => [
        [
          'value' => $this->randomMachineName(32),
          'format' => filter_default_format(),
        ],
      ],
      'type' => 'article',
      'title' => $this->randomMachineName(8),
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'promote' => 0,
      'sticky' => 0,
    ]);
    $this->node->save();
  }

  /**
   * Tests that the created flag is related to a content type 'article'.
   */
  public function testAnonymousLink(): void {
    // Created flag is related to articles.
    $this->assertEquals('node', $this->flag->getFlaggableEntityTypeId());
    $this->assertContains('article', $this->flag->getBundles());
    // Link is present for anonymous.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->linkExists('Anonymous Subscribe');
    // Link is not displayed for admin.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->linkNotExists('Anonymous Subscribe');
    // Go to page content type display.
    $this->drupalGet('admin/structure/types/manage/page/display/teaser');
    // No extra field.
    $this->assertSession()->responseNotContains('Anonymous subscribe link');
    // Go to article content type display.
    $this->drupalGet('admin/structure/types/manage/article/display/teaser');
    // Extra field on this one.
    $this->assertSession()->responseContains('Anonymous subscribe link');
  }

}
