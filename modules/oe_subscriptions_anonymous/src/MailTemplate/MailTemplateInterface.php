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
   * @param bool $has_html
   *   If the mail has HTML.
   *
   * @return array
   *   The processed subject and body.
   */
  public function prepare(array $params, bool $has_html): array;

}
