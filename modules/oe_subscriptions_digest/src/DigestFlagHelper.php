<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_digest;

use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\oe_subscriptions\FlagHelper;

/**
 * Helper methods to discern digest flags and flaggings.
 *
 * @internal
 */
final class DigestFlagHelper {

  /**
   * Checks if a given flagging has is a digest one.
   *
   * @param \Drupal\flag\FlaggingInterface $flagging
   *   The flagging to check.
   *
   * @return bool
   *   Whether the flagging is a digest one.
   */
  public static function isDigestFlagging(FlaggingInterface $flagging): bool {
    if (!self::isDigestFlag($flagging->getFlag())) {
      return FALSE;
    }

    return $flagging->hasField('message_digest');
  }

  /**
   * Checks if a given flag is prefixed with message_subscribe_email config.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to check.
   *
   * @return bool
   *   Whether the flag is a digest one.
   */
  public static function isDigestFlag(FlagInterface $flag): bool {
    return FlagHelper::hasModuleFlagPrefix('message_subscribe_email', $flag);
  }

}
