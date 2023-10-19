<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Plugin\ExtraField\Display;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for the anonymous subscribe link plugin.
 *
 * @see \Drupal\oe_subscriptions_anonymous\Plugin\ExtraField\Display\AnonymousSubscribeLink
 */
class AnonymousSubscribeLinkDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\flag\FlagServiceInterface $flag
   *   The flag service.
   */
  public function __construct(protected FlagServiceInterface $flag) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // We don't use the derivative property as that will statically cache
    // the entries, and doesn't allow for clearing said cache.
    // @see https://www.drupal.org/project/drupal/issues/2880682
    // @see https://www.drupal.org/project/drupal/issues/3001284
    $derivatives = [];

    $flags = $this->flag->getAllFlags();
    // Compose extra field allowed bundles.
    foreach ($flags as $flag) {
      // Disabled config, nothing to do.
      if (!$flag->status() || !str_starts_with($flag->id(), 'subscribe_')) {
        continue;
      }

      // Get flag entity type and related bundles.
      $flag_entity_type = $flag->getFlaggableEntityTypeId();
      $flag_bundles = $flag->getBundles();

      if (empty($flag_bundles)) {
        $flag_bundles = ['*'];
      }
      $bundles = array_map(fn($bundle) => "$flag_entity_type.$bundle", $flag_bundles);

      $derivatives[$flag->id()] = [
        'label' => $this->t('Anonymous subscribe link: @flag', [
          '@flag' => $flag->label(),
        ]),
        'bundles' => $bundles,
      ] + $base_plugin_definition;
    }

    return $derivatives;
  }

}
