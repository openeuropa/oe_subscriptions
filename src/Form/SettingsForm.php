<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The form for the configuration of the Subscription module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Name of the config being edited.
   */
  const CONFIG_NAME = 'oe_subscriptions.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oe_subscriptions_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $introduction_text = $this->config(static::CONFIG_NAME)->get('introduction_text');

    $form['introduction_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Introduction text'),
      '#default_value' => $introduction_text['value'] ?? '',
      '#format' => $introduction_text['format'] ?? NULL,
      '#description' => $this->t('Text displayed on "My subscriptions" page.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(static::CONFIG_NAME)
      ->set('introduction_text', $form_state->getValue('introduction_text'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [static::CONFIG_NAME];
  }

}
