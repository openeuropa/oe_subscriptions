<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flag\FlagInterface;

/**
 * The form for the anonymous subscription.
 */
class AnonymousSubscribeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_subscriptions_anonymous_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FlagInterface $flag = NULL, $entity_id = NULL) {
    $form_state->set('flag', $flag);
    $form_state->set('entity_id', $entity_id);

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#required' => TRUE,
    ];
    $form['accept_terms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have read and agree with the data protection terms.'),
      '#required' => TRUE,
    ];
    // This button will is used to close the modal, no submit callback.
    $form['actions'] = [
      '#type' => 'actions',
      'cancel' => [
        '#type' => 'button',
        '#value' => $this->t('No thanks') ,
        '#attributes' => [
          'class' => ['dialog-cancel'],
        ],
      ],
      'submit' => [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Subscribe me'),
      ],
    ];
    // Module library.
    $form['#attached']['library'][] = 'core/drupal.ajax';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addMessage($this->t('A confirmation e-email has been sent to your e-mail address.'));
  }

}
