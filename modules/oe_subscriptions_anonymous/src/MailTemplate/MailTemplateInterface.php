<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_anonymous\MailTemplate;

/**
 * Interface for mail templates.
 */
interface MailTemplateInterface {

  /**
   * Prepares the mail template.
   *
   * @param array $params
   *   Mail parameters.
   *
   * @return array
   *   The processed subject and body.
   */
  public function prepare(array $params): array;

  /**
   * Gets parameter keys used in the mail template.
   *
   * @return array
   *   The parameter keys.
   */
  public static function getParameters(): array;

}
