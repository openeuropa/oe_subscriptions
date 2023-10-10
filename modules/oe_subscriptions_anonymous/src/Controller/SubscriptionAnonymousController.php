<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\flag\FlagServiceInterface;
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
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FlagServiceInterface $flag) {
    $this->entityTypeManager = $entityTypeManager;
    $this->flag = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function subscribeAnonymous(string $subscription_id) {
    $response = new AjaxResponse();
    $response->addCommand(new AlertCommand("Subscription ID: $subscription_id"));
    return $response;
  }

}
