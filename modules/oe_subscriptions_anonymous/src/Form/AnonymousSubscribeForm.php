<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\flag\FlagInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The form for the anonymous subscription.
 */
class AnonymousSubscribeForm extends FormBase {

  /**
   * Mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * Configuration manager.
   *
   * @var Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Renderer.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a settings form.
   *
   * @param Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    MailManagerInterface $mailManager,
    ConfigFactoryInterface $configFactory,
    RendererInterface $renderer) {
    $this->mailManager = $mailManager;
    $this->configFactory = $configFactory;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('renderer'),
    );
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
    $form_state->set('flag', $flag);
    $form_state->set('entity_id', $entity_id);
    // Default Text without link.
    $title = $this->t('I have read and agree with the data protection terms.');
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
