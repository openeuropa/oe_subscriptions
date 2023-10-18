<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\oe_subscriptions_anonymous\Form\AnonymousSubscribeForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SubscriptionAnonymousController.
 *
 * Used to handle anonymous subscriptions.
 */
class SubscriptionAnonymousController extends ControllerBase {

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected FlagServiceInterface $flag;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FormBuilderInterface $formBuilder, FlagServiceInterface $flag) {
    $this->entityTypeManager = $entityTypeManager;
    $this->formBuilder = $formBuilder;
    $this->flag = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function subscribeAnonymous(string $subscription_id) {
    // Validation?
    return $this->formBuilder->getForm(AnonymousSubscribeForm::class, $subscription_id);
  }

}
