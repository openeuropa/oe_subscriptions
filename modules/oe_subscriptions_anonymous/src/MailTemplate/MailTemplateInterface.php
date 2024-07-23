<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

/**
 * Interface for mail templates.
 */
interface MailTemplateInterface {

  /**
   * Gets parameter keys needed for the mail template.
   *
   * @return array
   *   The parameter keys.
   */
  public function getParameters(): array;

  /**
   * Gets processed variables used the mail template.
   *
   * @param array $params
   *   Mail parameters.
   *
   * @return array
   *   The processed associative array.
   */
  public function getVariables(array $params): array;

  /**
   * Prepares the mail content if the core mailer is used.
   *
   * This method has no effect if the emails are sent with symfony_mailer.
   *
   * @param array $params
   *   Mail parameters.
   *
   * @return array
   *   The processed subject and body.
   */
  public function prepare(array $params): array;

}
