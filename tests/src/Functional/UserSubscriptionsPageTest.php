<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\flag\FlagInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the user subscriptions page.
 */
class UserSubscriptionsPageTest extends BrowserTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
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

    EntityTestBundle::create([
      'id' => 'foo',
      'label' => 'The foo label',
    ])->save();
    EntityTestBundle::create(['id' => 'bar'])->save();
  }

  /**
   * Tests the subscriptions page.
   */
  public function testSubscriptionsPage(): void {
    $flag_defaults = [
      'flag_short' => 'Flag',
      'unflag_short' => 'Unflag',
    ];

    $pages_flag = $this->createFlagFromArray([
      'id' => 'subscribe_page',
      'entity_type' => 'node',
      'bundles' => ['page'],
    ] + $flag_defaults);
    $articles_flag = $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_short' => 'Subscribe to this article',
      'entity_type' => 'node',
      'bundles' => ['article'],
    ] + $flag_defaults);
    $generic_flag = $this->createFlagFromArray([
      'id' => 'generic',
      'flag_type' => $this->getFlagType('node'),
      'entity_type' => 'node',
    ] + $flag_defaults);
    $foo_flag = $this->createFlagFromArray([
      'id' => 'subscribe_foo',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['foo'],
    ] + $flag_defaults);
    $bar_flag = $this->createFlagFromArray([
      'id' => 'subscribe_bar',
      'flag_type' => $this->getFlagType('entity_test_with_bundle'),
      'entity_type' => 'entity_test_with_bundle',
      'bundles' => ['bar'],
    ] + $flag_defaults);
    $page_one = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);
    $page_two = $this->drupalCreateNode([
      'type' => 'page',
      'status' => 1,
    ]);
    $article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
    $foo = EntityTestWithBundle::create([
      'type' => 'foo',
      'name' => $this->randomMachineName(),
    ]);
    $foo->save();
    $bar = EntityTestWithBundle::create([
      'type' => 'bar',
      'name' => $this->randomMachineName(),
    ]);
    $bar->save();

    $role = $this->drupalCreateRole([
      'flag subscribe_page',
      'unflag subscribe_page',
      'flag subscribe_article',
      'unflag subscribe_article',
      'flag generic',
      'unflag generic',
      'flag subscribe_foo',
      'unflag subscribe_foo',
      'flag subscribe_bar',
      'unflag subscribe_bar',
      'view test entity',
      'access content',
    ]);
    $user_one = $this->drupalCreateUser([], NULL, FALSE, ['roles' => [$role]]);
    $user_two = $this->drupalCreateUser([], NULL, FALSE, ['roles' => [$role]]);

    // The subscription page is not accessible for anonymous users.
    $this->drupalGet("/user/{$user_one->id()}/subscriptions");
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(403);

    // Users can access the subscription page only if they have edit access
    // on the account.
    $this->drupalLogin($user_two);
    $this->drupalGet("/user/{$user_one->id()}/subscriptions");
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($user_one);
    $this->drupalGet("/user/{$user_one->id()}/subscriptions");
    $assert_session->titleEquals('My subscriptions | Drupal');
    $assert_session->pageTextContains('No subscriptions found.');

    /** @var \Drupal\flag\FlagServiceInterface $flag_service */
    $flag_service = \Drupal::service('flag');
    // Flag some entities for user one.
    $flag_service->flag($articles_flag, $article, $user_one);
    $flag_service->flag($pages_flag, $page_two, $user_one);
    $flag_service->flag($foo_flag, $foo, $user_one);
    // Flag also with the generic flag. Since it's not a subscribe flag, it
    // shouldn't be shown in the page.
    $flag_service->flag($generic_flag, $page_one, $user_one);
    // And some for user two.
    $flag_service->flag($articles_flag, $article, $user_two);
    $flag_service->flag($pages_flag, $page_one, $user_two);

    $this->drupalGet("/user/{$user_one->id()}/subscriptions");
    $table = $assert_session->elementExists('css', 'table.user-subscriptions');

    // Assert the header row labels.
    $this->assertEquals([
      [
        'Type',
        'Title',
        'Operations',
      ],
    ], $this->getTableSectionContent($table, 'thead'));

    $rows = $this->getTableSectionContent($table, 'tbody');
    $this->assertCount(3, $rows);
    $this->assertSubscriptionRow('Test entity with bundle', $foo, $foo_flag, $rows[0]);
    $this->assertSubscriptionRow('Content', $page_two, $pages_flag, $rows[1]);
    $this->assertSubscriptionRow('Content', $article, $articles_flag, $rows[2]);

    // Flag another entities for the user one.
    $flag_service->flag($bar_flag, $bar, $user_one);
    $flag_service->flag($pages_flag, $page_one, $user_one);
    $this->drupalGet("/user/{$user_one->id()}/subscriptions");
    $rows = $this->getTableSectionContent($table, 'tbody');
    $this->assertCount(5, $rows);
    $this->assertSubscriptionRow('Test entity with bundle', $bar, $bar_flag, $rows[0]);
    $this->assertSubscriptionRow('Test entity with bundle', $foo, $foo_flag, $rows[1]);
    $this->assertSubscriptionRow('Content', $page_one, $pages_flag, $rows[2]);
    $this->assertSubscriptionRow('Content', $page_two, $pages_flag, $rows[3]);
    $this->assertSubscriptionRow('Content', $article, $articles_flag, $rows[4]);

    // Check the flags of user two.
    $this->drupalLogin($user_two);
    $this->drupalGet("/user/{$user_two->id()}/subscriptions");
    $rows = $this->getTableSectionContent($table, 'tbody');
    $this->assertCount(2, $rows);
    $this->assertSubscriptionRow('Content', $page_one, $pages_flag, $rows[0]);
    $this->assertSubscriptionRow('Content', $article, $articles_flag, $rows[1]);
  }

  /**
   * Extracts the table cell contents for a table section.
   *
   * @param \Behat\Mink\Element\NodeElement $table
   *   The table element.
   * @param string $section
   *   The table section. Either "thead" or "tbody".
   *
   * @return array
   *   The cell content ordered by row.
   */
  protected function getTableSectionContent(NodeElement $table, string $section): array {
    $cell_selector = match($section) {
      'thead' => 'th',
      'tbody' => 'td',
    };

    $content = [];
    $rows = $table->findAll('css', $section . ' tr');
    /** @var \Behat\Mink\Element\NodeElement[] $rows */
    foreach ($rows as $row) {
      $cells = $row->findAll('css', $cell_selector);
      $row_content = [];
      foreach ($cells as $cell) {
        /** @var \Behat\Mink\Element\NodeElement $cell */
        $row_content[] = trim($cell->getHtml());
      }
      $content[] = $row_content;
    }

    return $content;
  }

  /**
   * Asserts a single row of the subscriptions table.
   *
   * @param string $entity_type_label
   *   The expected entity type label.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The flagged entity.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag.
   * @param array $cells
   *   The cell contents.
   */
  protected function assertSubscriptionRow(string $entity_type_label, EntityInterface $entity, FlagInterface $flag, array $cells): void {
    global $base_path;

    $this->assertEquals($entity_type_label, $cells[0]);
    $this->assertEquals((string) $entity->toLink()->toString(), $cells[1]);

    // The third cell has markup that contains a flag link. Extract the link.
    $crawler = new Crawler($cells[2]);
    $links = $crawler->filter('a');
    $this->assertCount(1, $links);

    $url = sprintf('%sflag/unflag/%s/%s', $base_path, $flag->id(), $entity->id());
    $this->assertStringStartsWith($url, $links->eq(0)->attr('href'));
  }

}
