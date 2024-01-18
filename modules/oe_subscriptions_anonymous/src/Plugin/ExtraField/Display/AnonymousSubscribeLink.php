<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Plugin\ExtraField\Display;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Anonymous subscribe link.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_subscriptions_anonymous_subscribe_link",
 *   label = @Translation("Anonymous subscribe link"),
 *   description = @Translation("Link to subscribe to notifications about this entity."),
 *   deriver = "Drupal\oe_subscriptions_anonymous\Plugin\ExtraField\Display\AnonymousSubscribeLinkDeriver",
 *   visible = true
 * )
 */
class AnonymousSubscribeLink extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected FlagServiceInterface $flag;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FlagServiceInterface $flag) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flag = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $build = [];
    $cache = new CacheableMetadata();
    // Based on derivative id.
    $flag = $this->flag->getFlagById($this->getDerivativeId());
    // Bail out if the flag cannot be loaded.
    if (empty($flag)) {
      return $build;
    }
    // Get link to form.
    $url = Url::fromRoute('oe_subscriptions_anonymous.subscription_request', [
      'flag' => $flag->id(),
      'entity_id' => $entity->id(),
    ]);
    // Cache based on flag.
    $cache->addCacheableDependency($flag);
    // Cache based on access.
    $access = $url->access(NULL, TRUE);
    $cache->addCacheableDependency($access);
    // No explicit access.
    if (!$access->isAllowed()) {
      $cache->applyTo($build);
      return $build;
    }
    // Link.
    $build = [
      '#theme' => 'oe_subscriptions_anonymous_link',
      '#title' => $flag->getShortText('flag'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['use-ajax', 'button', 'button--small'],
        'data-dialog-type' => 'modal',
      ],
      '#attached' => [
        'library' => [
          'core/drupal.ajax',
        ],
      ],
    ];
    $cache->applyTo($build);

    return $build;
  }

}
