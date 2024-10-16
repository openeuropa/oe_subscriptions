<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\SettingsFormAlter;
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
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\flag\FlagServiceInterface $flagService
   *   The flag service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    protected MailManagerInterface $mailManager,
    protected FlagServiceInterface $flagService,
    protected LanguageManagerInterface $languageManager,
    protected RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('plugin.manager.mail'),
      $container->get('flag'),
      $container->get('language_manager'),
      $container->get('renderer')
    );
    $instance->setMessenger($container->get('messenger'));
    $instance->setConfigFactory($container->get('config.factory'));

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
  public function buildForm(array $form, FormStateInterface $form_state, ?FlagInterface $flag = NULL, ?string $entity_id = NULL) {
    // Terms and conditions link.
    $title = $this->t('I have read and agree with the data protection terms.');
    $config = $this->configFactory->get(SettingsFormAlter::CONFIG_NAME);
    // In case we have a value we override default text with the link.
    if (!empty($config->get('terms_url'))) {
      $url = Url::fromUri($config->get('terms_url'));
      if ($url->access()) {
        $title = $this->t('I have read and agree with the <a href=":url" target="_blank" >data protection terms</a>.', [':url' => $url->toString()]);
      }
    }

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#required' => TRUE,
    ];

    $form['accept_terms'] = [
      '#type' => 'checkbox',
      '#title' => $title,
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
    $mail = $form_state->getValue('email');
    /**
     * @var \Drupal\flag\FlagInterface $flag
     * @var string|int $entity_id
     */
    [$flag, $entity_id] = $form_state->getBuildInfo()['args'];

    $mail_key = 'subscription_create';
    $mail_params = [
      'email' => $mail,
      'flag' => $flag,
      'entity_id' => $entity_id,
    ];

    // Check if a user with this email already exists.
    // @todo Use dependency injection instead of function call.
    // The decoupled_auth module is installed, which replaces the class for
    // user entities.
    /** @var \Drupal\decoupled_auth\DecoupledAuthUserInterface|null $account */
    $account = user_load_by_mail($mail);

    if ($account !== FALSE && $account->isCoupled()) {
      // The email address belongs to a regular user account, which requires
      // regular login.
      $mail_key = 'registered_user_email_notice';
      $mail_params = [
        'email' => $mail,
        'entity_type' => $flag->getFlaggableEntityTypeId(),
        'entity_id' => $entity_id,
      ];
    }

    // @todo Send a different e-mail when the user is already subscribed.
    $result = $this->mailManager->mail(
      'oe_subscriptions_anonymous',
      $mail_key,
      $mail,
      $this->languageManager->getCurrentLanguage()->getId(),
      $mail_params,
    );

    if (!$result) {
      $this->messenger()->addError($this->t('An error occurred when sending the confirmation e-mail. Please contact the administrator.'));
      return;
    }

    $confirm_message = [
      '#theme' => 'oe_subscriptions_anonymous_status_message',
      '#title' => $this->t('A confirmation email has been sent to your email address'),
      '#message' => $this->t('To confirm your subscription, <strong>please click on the confirmation link</strong> sent to your e-mail address.'),
    ];
    $rendered_message = $this->renderer->render($confirm_message);
    $this->messenger()->addWarning($rendered_message);

    $entity = $this->flagService->getFlaggableById($flag, $entity_id);
    try {
      // Redirect to the canonical page of the entity.
      $form_state->setRedirectUrl($entity->toUrl());
    }
    catch (UndefinedLinkTemplateException $exception) {
      // Catch scenarios where no canonical link template or uri_callback are
      // defined.
      $form_state->setRedirectUrl(Url::fromRoute('<front>'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    // We need to retrieve the redirect URL set in ::formSubmit(), but
    // the getRedirect() method will return false if redirects are disabled.
    // Form redirects are normally disabled during AJAX requests by the form
    // builder.
    // @see \Drupal\Core\Form\FormBuilder::buildForm()
    $is_redirect_disabled = $form_state->isRedirectDisabled();
    $form_state->disableRedirect(FALSE);
    $redirect = $form_state->getRedirect();
    $form_state->disableRedirect($is_redirect_disabled);
    $response = new AjaxResponse();
    $url = new RedirectCommand($redirect->toString());

    return $response->addCommand($url);
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
