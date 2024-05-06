<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Functional;

use Drupal\symfony_mailer_test\MailerTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\flag\Traits\FlagCreateTrait;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\StatusMessageTrait;

/**
 * Base class for integrations of HTML mails.
 */
abstract class HtmlMailsTestBase extends BrowserTestBase {

  use FlagCreateTrait;
  use StatusMessageTrait;
  use MailerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'oe_subscriptions_anonymous',
    'symfony_mailer_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to manage mailer settings.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Node to subscribe.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $article;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->createFlagFromArray([
      'id' => 'subscribe_article',
      'flag_short' => 'Subscribe',
      'entity_type' => 'node',
      'bundles' => ['article'],
    ]);

    $this->adminUser = $this->drupalCreateUser([
      'administer mailer',
      'use text format email_html',
    ]);
    $this->article = $this->drupalCreateNode([
      'type' => 'article',
      'status' => 1,
    ]);
  }

}
