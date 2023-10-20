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
    $node = $this->drupalCreateNode([
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
    $node->save();
    // Created flag is related to articles.
    $this->assertEquals('node', $flag->getFlaggableEntityTypeId());
    $this->assertContains('article', $flag->getBundles());
    // Link is present for anonymous.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->linkExists($flag->label());
    // Link is not displayed for admin.
    $this->drupalLogin($adminUser);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->linkNotExists($flag->label());
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
