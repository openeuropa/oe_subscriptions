<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\Plugin\EmailBuilder;

use Drupal\oe_subscriptions_anonymous\MailTemplate\MailTemplateHelper;
use Drupal\symfony_mailer\Address;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;

/**
 * Defines the Email Builder plug-in for oe_subscriptions_anonymous module.
 *
 * @EmailBuilder(
 *   id = "oe_subscriptions_anonymous",
 *   label = @Translation("Anonymous subscription"),
 *   sub_types = {
 *     "subscription_create" = @Translation("Subscription creation"),
 *     "user_subscriptions_access" = @Translation("Subscriptions access request"),
 *   },
 * )
 */
class AnonymousEmailBuilder extends EmailBuilderBase {

  /**
   * Creates the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param array $params
   *   The email parameters.
   */
  public function createParams(EmailInterface $email, array $params = []): void {
    $mail_template = MailTemplateHelper::getMailTemplate($email->getSubType());

    foreach ($mail_template::getParameters() as $param) {
      $email->setParam($param, $params[$param]);
    }
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
    $params = [];
    $mail_template = MailTemplateHelper::getMailTemplate($email->getSubType());

    foreach ($mail_template->getParameters() as $param) {
      $params[$param] = $email->getParam($param);
    }

    $message = $mail_template->prepare($params);

    $email
      ->setTo(new Address($params['email']))
      ->setSubject($message['subject'])
      ->setBody($message['body']);
  }

}
