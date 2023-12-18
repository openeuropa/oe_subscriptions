<?php

declare(strict_types = 1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

/**
 * Interface for mail templates.
 */
interface MailTemplateInterface {

  /**
   * Prepares the mail template.
   *
   * @param array $message
   *   Message parts.
   * @param array $params
   *   Mail parameters.
   */
  public function prepare(array &$message, array $params): void;

}
