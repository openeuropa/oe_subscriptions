<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\Plugin\EmailBuilder;

use Drupal\oe_subscriptions_anonymous\MailTemplate\SubscriptionCreate;
use Drupal\oe_subscriptions_anonymous\MailTemplate\UserSubscriptionsAccess;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines the Email Builder plug-in for anonymous subscribe mails.
 *
 * @EmailBuilder(
 *   id = "oe_subscriptions_anonymous",
 *   label = @Translation("Anonymous subscription"),
 *   sub_types = {
 *     "subscription_create" = @Translation("Subscription create"),
 *     "user_subscriptions_access" = @Translation("User subscriptions access"),
 *   },
 * )
 */
class SubscribeEmailBuilder extends EmailBuilderBase {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param array $params
   *   The recipient.
   */
  public function createParams(EmailInterface $email, array $params = []): void {
    assert($params['email'] != '');

    if ($email->getSubType() === 'subscription_create') {
      assert($params['flag'] != NULL);
      assert($params['entity_id'] != '');

      $email
        ->setParam('flag', $params['flag'])
        ->setParam('entity_id', $params['entity_id']);
    }

    $email->setParam('email', $params['email']);
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message): EmailInterface {
    return $factory->newTypedEmail($message['module'], $message['key'], $message['params']);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email): void {
    $sub_type = $email->getSubType();
    $params = [
      'email' => $email->getParam('email'),
    ];

    if ($sub_type === 'subscription_create') {
       $params += [
        'flag' => $email->getParam('flag'),
        'entity_id' => $email->getParam('entity_id')
       ];
    }

    $class = match ($sub_type) {
      'subscription_create' => SubscriptionCreate::class,
      'user_subscriptions_access' => UserSubscriptionsAccess::class
    };

    $message = \Drupal::classResolver($class)->prepare($params, TRUE);

    $email
      ->setTo(new Address($params['email']))
      ->setSubject($message['subject'])
      ->setBody($message['body']);
  }

}
