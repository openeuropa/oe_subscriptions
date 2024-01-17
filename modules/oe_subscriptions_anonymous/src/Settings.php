<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to manage anonymous settings.
 */
class Settings implements ContainerInjectionInterface {

  /**
   * Name of the config.
   */
  const CONFIG_NAME = 'oe_subscriptions_anonymous.settings';

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The factory for configuration objects.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected ConfigFactoryInterface $config) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Alters the settings form to add anonymous settings.
   */
  public function settingsFormAlter(&$form) {
    $url = $this->config->get(static::CONFIG_NAME)->get('terms_url');

    $form['terms_url'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => t('Terms page URL'),
      '#default_value' => !empty($url) ? $this->getUriAsDisplayableString($url) : '',
      '#description' => t('The URL to the terms and conditions page.'),
      '#required' => TRUE,
      '#element_validate' => [
        [
          'Drupal\link\Plugin\Field\FieldWidget\LinkWidget',
          'validateUriElement',
        ],
      ],
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
      '#process_default_value' => FALSE,
    ];
    $form['#submit'][] = [$this, 'settingsFormSubmit'];
  }

  /**
   * Submit to save anonymous settings.
   */
  public function settingsFormSubmit($form, FormStateInterface $form_state) {
    $config = $this->config->getEditable(static::CONFIG_NAME);
    $config->set('terms_url', $form_state->getValue('terms_url'))->save();
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of ::getUserEnteredStringAsUri().
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   String to be displayed.
   *
   * @see \Drupal\link\Plugin\Field\FieldWidget\LinkWidget::getUserEnteredStringAsUri()
   */
  private function getUriAsDisplayableString($uri): string {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

}
