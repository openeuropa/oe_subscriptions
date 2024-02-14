<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form for anonymous users to request access to their subscriptions page.
 */
class UserSubscriptionsRequestAccessForm extends FormBase {

  /**
   * Creates a new instance of this class.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected MailManagerInterface $mailManager,
    protected LanguageManagerInterface $languageManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('plugin.manager.mail'),
      $container->get('language_manager')
    );
    $instance->setMessenger($container->get('messenger'));

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_subscriptions_anonymous_user_subscriptions_request_access_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Submit'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mail = $form_state->getValue('email');

    $result = $this->mailManager->mail(
      'oe_subscriptions_anonymous',
      'user_subscriptions_access',
      $mail,
      $this->languageManager->getCurrentLanguage()->getId(),
      [
        'email' => $mail,
      ]);

    if (!$result) {
      $this->messenger()->addError($this->t('An error occurred when sending the confirmation e-mail. Please contact the administrator.'));
      return;
    }

    $this->messenger()->addMessage($this->t('A confirmation e-email has been sent to your e-mail address.'));
  }

}
