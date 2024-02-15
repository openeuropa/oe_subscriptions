<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_digest;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to manage digest preferences.
 *
 * @internal
 */
final class SubscriptionsFormAlter implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The user account for which the form is being rendered.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $account;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected ModuleHandlerInterface $moduleHandler, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Alters the form to add digest preference.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function subscriptionsFormAlter(array &$form, $form_state): void {
    $this->moduleHandler->loadInclude('message_digest_ui', 'module');
    $this->account = $form_state->getBuildInfo()['args']['0'];
    $message_digest = $this->account->get('message_digest');
    $storage_definition = $message_digest->getFieldDefinition()->getFieldStorageDefinition();

    $form['message_digest'] = [
      '#type' => 'select',
      '#title' => $this->t('Notifications frequency'),
      '#description' => $this->t('The frequency this currentUser will receive notifications is subscribed to.'),
      '#default_value' => $message_digest->value ?: '0',
      '#options' => message_digest_allowed_values_callback($storage_definition),
    ];

    $form['#submit'][] = [$this, 'subscriptionsFormSubmit'];
  }

  /**
   * Submit to save user digest preference.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function subscriptionsFormSubmit(array $form, FormStateInterface $form_state): void {
    $message_digest = $form_state->getValue('message_digest');
    $this->account->set('message_digest', $message_digest === '' ? NULL : $message_digest)->save();
  }

}
