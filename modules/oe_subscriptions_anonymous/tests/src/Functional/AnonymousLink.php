<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;

/**
 * Anonymous link test.
 */
class AnonymousLink extends BrowserTestBase {

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
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

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
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Get the Flag Service.
    $this->flagService = \Drupal::service('flag');
    // Create a flag.
    $this->flag = $this->createFlag('node', ['article'], 'reload');
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
  public function testFlagInContentType(): void {
    $this->assertEquals('node', $this->flag->getFlaggableEntityTypeId());
    $this->assertContains('article', $this->flag->getBundles());
  }

  /**
   * Tests that the link is displayed to anonymous.
   */
  public function testAnonymousLink(): void {
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->responseContains('Anonymous Subscribe');
  }

  /**
   * Tests that the link is not displayed to auth.
   */
  public function testAdminLink(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->responseNotContains('Anonymous Subscribe');
  }

}
