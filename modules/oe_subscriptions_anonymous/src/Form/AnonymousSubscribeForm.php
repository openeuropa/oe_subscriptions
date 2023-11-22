<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form for the anonymous subscription.
 */
class AnonymousSubscribeForm extends FormBase {

  use AjaxHelperTrait;
  use AjaxFormHelperTrait;

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   */
  public function __construct(protected FlagServiceInterface $flagService) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('flag')
    );
    $instance->setMessenger($container->get('messenger'));

    return $instance;
  }

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
    $form['#attached']['library'][] = 'core/drupal.ajax';

    if ($this->isAjax()) {
      // Due to https://www.drupal.org/node/2897377 we have to declare a fixed
      // ID for the form.
      // Since only one modal can be opened at the time, we can rely on the
      // form ID as HTML ID.
      // @todo Remove this workaround once https://www.drupal.org/node/2897377
      //   is fixed.
      $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Send the email.
    // If the form was rendered in an AJAX call, we don't need to do anything
    // else.
    if ($this->isAjax()) {
      return;
    }

    $this->messenger()->addMessage($this->t('A confirmation e-email has been sent to your e-mail address.'));
    [$flag, $entity_id] = $form_state->getBuildInfo()['args'];
    $entity = $this->flagService->getFlaggableById($flag, $entity_id);
    try {
      // Redirect to the canonical page of the entity.
      $form_state->setRedirectUrl($entity->toUrl());
    }
    catch (UndefinedLinkTemplateException $exception) {
      // Catch scenarios where no caonical link template or uri_callback are
      // defined.
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new MessageCommand($this->t('A confirmation e-email has been sent to your e-mail address.')));

    return $response;
  }

}
