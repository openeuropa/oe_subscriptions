<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\filter\Entity\FilterFormat;
use Drupal\flag\FlagInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\oe_subscriptions\Form\SettingsForm;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\user\UserInterface;

/**
 * Tests the user subscriptions page.
 */
abstract class UserSubscriptionsPageTestBase extends BrowserTestBase {

  use FlagCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'entity_test',
    'node',
    'oe_subscriptions',
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

    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'header',
      'id' => 'local_tasks',
    ]);

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
   *
   * @param callable $fn_create_users
   *   Callable to create the users for the test.
   * @param callable $fn_go_to_page
   *   Callable to visit the subscriptions page for a user. It also needs to
   *   take care of logging the user, or requesting a token in order to allow
   *   visiting the path.
   */
  protected function doTestFlagsList(callable $fn_create_users, callable $fn_go_to_page): void {
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

    /** @var \Drupal\user\UserInterface $user_one */
    /** @var \Drupal\user\UserInterface $user_two */
    [$user_one, $user_two] = $fn_create_users();

    $fn_go_to_page($user_one);
    $assert_session = $this->assertSession();
    $assert_session->titleEquals('Manage your subscriptions | Drupal');
    // No subscriptions exist yet.
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

    $fn_go_to_page($user_one);
    $table = $assert_session->elementExists('css', 'table.user-subscriptions');

    // Assert the header row labels.
    $header_cells = $this->getTableSectionRows($table, 'thead');
    $this->assertCount(1, $header_cells);
    $this->assertEquals([
      'Type',
      'Title',
      'Operations',
    ], array_map(fn($cell) => trim($cell->getHtml()), $header_cells[0]));

    $rows = $this->getTableSectionRows($table, 'tbody');
    $this->assertCount(3, $rows);
    $this->assertSubscriptionRow('Test entity with bundle', $foo, $foo_flag, $user_one, $rows[0]);
    $this->assertSubscriptionRow('Content', $page_two, $pages_flag, $user_one, $rows[1]);
    $this->assertSubscriptionRow('Content', $article, $articles_flag, $user_one, $rows[2]);

    // Flag another entities for the user one.
    $flag_service->flag($bar_flag, $bar, $user_one);
    $flag_service->flag($pages_flag, $page_one, $user_one);
    $fn_go_to_page($user_one);
    $rows = $this->getTableSectionRows($table, 'tbody');
    $this->assertCount(5, $rows);
    $this->assertSubscriptionRow('Test entity with bundle', $bar, $bar_flag, $user_one, $rows[0]);
    $this->assertSubscriptionRow('Test entity with bundle', $foo, $foo_flag, $user_one, $rows[1]);
    $this->assertSubscriptionRow('Content', $page_one, $pages_flag, $user_one, $rows[2]);
    $this->assertSubscriptionRow('Content', $page_two, $pages_flag, $user_one, $rows[3]);
    $this->assertSubscriptionRow('Content', $article, $articles_flag, $user_one, $rows[4]);

    // Check the flags of user two.
    $fn_go_to_page($user_two);
    $rows = $this->getTableSectionRows($table, 'tbody');
    $this->assertCount(2, $rows);
    $this->assertSubscriptionRow('Content', $page_one, $pages_flag, $user_two, $rows[0]);
    $this->assertSubscriptionRow('Content', $article, $articles_flag, $user_two, $rows[1]);

    // Use the remove button to unsubscribe from the article.
    $rows[1][2]->pressButton('Remove');
    $assert_session->statusMessageContains('You have successfully unsubscribed from ' . $article->label(), 'status');
    $rows = $this->getTableSectionRows($table, 'tbody');
    $this->assertCount(1, $rows);
    $this->assertSubscriptionRow('Content', $page_one, $pages_flag, $user_two, $rows[0]);
    $this->assertFalse($articles_flag->isFlagged($article, $user_two));

    // Unsubscribe from the page too.
    $rows[0][2]->pressButton('Remove');
    $assert_session->statusMessageContains('You have successfully unsubscribed from ' . $page_one->label(), 'status');
    $this->assertEmpty($this->getTableSectionRows($table, 'tbody'));
    $assert_session->pageTextContains('No subscriptions found.');

    // Administrators can manage other users subscriptions.
    $this->drupalLogin($this->drupalCreateUser(['administer users']));
    $this->drupalGet("/user/{$user_one->id()}/subscriptions");
    $assert_session->titleEquals(sprintf('Manage %s subscriptions | Drupal', $user_one->getDisplayName()));
    $rows = $this->getTableSectionRows($table, 'tbody');
    $this->assertCount(5, $rows);
    // Remove the subscription from the article.
    $rows[4][2]->pressButton('Remove');
    $assert_session->statusMessageContains('You have successfully unsubscribed from ' . $article->label(), 'status');
    $rows = $this->getTableSectionRows($table, 'tbody');
    $this->assertCount(4, $rows);
    $this->assertSubscriptionRow('Test entity with bundle', $bar, $bar_flag, $user_one, $rows[0]);
    $this->assertSubscriptionRow('Test entity with bundle', $foo, $foo_flag, $user_one, $rows[1]);
    $this->assertSubscriptionRow('Content', $page_one, $pages_flag, $user_one, $rows[2]);
    $this->assertSubscriptionRow('Content', $page_two, $pages_flag, $user_one, $rows[3]);
    $this->drupalLogout();
  }

  /**
   * Tests the user preferences fields.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for the test.
   * @param callable $fn_get_path
   *   Callable that returns the path to visit for the test. It also needs to
   *   take care of logging the user, or requesting a token in order to allow
   *   visiting the path.
   */
  protected function doTestUserPreferences(UserInterface $user, callable $fn_get_path): void {
    $path = $fn_get_path($user);
    $this->drupalGet($path);
    $assert_session = $this->assertSession();
    $assert_session->fieldNotExists('Preferred language');

    \Drupal::service('module_installer')->install(['language']);
    $this->drupalGet($path);
    $select = $assert_session->selectExists('Preferred language');
    $this->assertEquals([
      'en' => 'English',
    ], $this->getOptions($select));

    ConfigurableLanguage::createFromLangcode('it')->save();
    $this->drupalGet($path);
    $this->assertEquals([
      'en' => 'English',
      'it' => 'Italian',
    ], $this->getOptions($select));

    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->drupalGet($path);
    $this->assertEquals([
      'en' => 'English',
      'it' => 'Italian',
      'es' => 'Spanish',
    ], $this->getOptions($select));

    $select->selectOption('Italian');
    $assert_session->buttonExists('Save')->press();
    $assert_session->statusMessageContains('Your preferences have been saved', 'status');

    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $user_storage->resetCache();
    /** @var \Drupal\user\UserInterface $user */
    $user = $user_storage->load($user->id());
    $this->assertEquals('it', $user->getPreferredLangcode());
  }

  /**
   * Tests the rendering of the introduction text.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for the test.
   * @param callable $fn_get_path
   *   Callable that returns the path to visit for the test. It also needs to
   *   take care of logging the user, or requesting a token in order to allow
   *   visiting the path.
   */
  protected function doTestFormPreface(UserInterface $user, callable $fn_get_path): void {
    $path = $fn_get_path($user);
    $this->drupalGet($path);
    $assert_session = $this->assertSession();
    $subscriptions_config = \Drupal::configFactory()->getEditable(SettingsForm::CONFIG_NAME);

    // Test that text isn't present by default.
    $this->drupalGet($path);
    $assert_session->pageTextNotContains('Configurable text 1.');

    // Test that the text is displayed after setting configuration.
    $subscriptions_config
      ->set('introduction_text', [
        'value' => 'Configurable text 1.',
        'format' => 'plain_text',
      ])->save();
    $this->drupalGet($path);
    $assert_session->pageTextContains('Configurable text 1.');

    // Test that the text with a link is displayed after updating configuration.
    FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
    ])->save();
    $subscriptions_config
      ->set('introduction_text', [
        'value' => 'Configurable text 2 <a href="/test">Terms and conditions</a>.',
        'format' => 'full_html',
      ])->save();
    $this->drupalGet($path);
    $assert_session->pageTextContains('Configurable text 2 Terms and conditions.');
    $link = $assert_session->elementExists('xpath', '//a[text()="Terms and conditions"]');
    $this->assertEquals('/test', $link->getAttribute('href'));
  }

  /**
   * Extracts the table cell contents for a table section.
   *
   * @param \Behat\Mink\Element\NodeElement $table
   *   The table element.
   * @param string $section
   *   The table section. Either "thead" or "tbody".
   *
   * @return \Behat\Mink\Element\NodeElement[][]
   *   The cell elements, grouped by row.
   */
  protected function getTableSectionRows(NodeElement $table, string $section): array {
    $cell_selector = match($section) {
      'thead' => 'th',
      'tbody' => 'td',
    };

    $cells = [];
    foreach ($table->findAll('css', $section . ' tr') as $row) {
      /** @var \Behat\Mink\Element\NodeElement $row */
      $cells[] = $row->findAll('css', $cell_selector);
    }

    return $cells;
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
   * @param \Drupal\user\UserInterface $account
   *   The user account for which the page has been rendered.
   * @param \Behat\Mink\Element\NodeElement[] $cells
   *   The cell elements.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function assertSubscriptionRow(string $entity_type_label, EntityInterface $entity, FlagInterface $flag, UserInterface $account, array $cells): void {
    $this->assertEquals($entity_type_label, $cells[0]->getHtml());

    $links = $cells[1]->findAll('css', 'a');
    $this->assertCount(1, $links);
    $this->assertEquals($entity->toUrl()->toString(), $links[0]->getAttribute('href'));
    $this->assertEquals($entity->label(), $links[0]->getHtml());

    $buttons = $cells[2]->findAll('named', ['button', 'Remove']);
    $this->assertCount(1, $buttons);

    // @todo is this needed?
    $flagging = \Drupal::service('flag')->getFlagging($flag, $entity, $account);
    $this->assertNotEmpty($flagging);
    $this->assertEquals(sprintf('edit-flag-list-%s-unflag', $flagging->id()), $buttons[0]->getAttribute('data-drupal-selector'));
  }

}
