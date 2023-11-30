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
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\TokenManagerInterface;
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
   * @param \Drupal\oe_subscriptions_anonymous\TokenManagerInterface $tokenManager
   *   The token manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected TokenManagerInterface $tokenManager,
    protected MailManagerInterface $mailManager,
    protected FlagServiceInterface $flagService,
    protected LanguageManagerInterface $languageManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('oe_subscriptions_anonymous.token_manager'),
      $container->get('plugin.manager.mail'),
      $container->get('flag'),
      $container->get('language_manager')
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
   * Creates the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\flag\FlagInterface|null $flag
   *   The flag entity.
   * @param string|null $entity_id
   *   The entity ID to which to subscribe.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, FlagInterface $flag = NULL, string $entity_id = NULL) {
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
        // The cancel button will be shown only if the form is rendered in an
        // AJAX request.
        '#access' => FALSE,
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
      $form['actions']['cancel']['#access'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get parameters.
    $mail = $form_state->getValue('email');
    $flag = $form_state->get('flag');
    $entity_id = $form_state->get('entity_id');

    $result = $this->mailManager->mail(
      'oe_subscriptions_anonymous',
      "subscription_create",
      $mail,
      $this->languageManager->getCurrentLanguage()->getId(),
      [
        'email' => $mail,
        'flag' => $flag,
        'entity_id' => $entity_id,
      ]);

    if (!$result) {
      $this->messenger()->addError($this->t('An error occurred when sending the confirmation e-mail. Please contact the administrator.'));
      return;
    }

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

  /**
   * Returns the title for the subscribe route.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function getTitle(FlagInterface $flag, string $entity_id): TranslatableMarkup {
    $entity = $this->flagService->getFlaggableById($flag, $entity_id);
    if (!$entity) {
      return $this->t('Subscribe');
    }

    return $this->t('Subscribe to @label', [
      '@label' => $entity->label(),
    ]);
  }

}
