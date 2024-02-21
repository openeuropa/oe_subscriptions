<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions_digest;

use Drupal\flag\FlaggingInterface;
use Drupal\flag\FlagInterface;

/**
 * Class to manage digest flag.
 *
 * @internal
 */
class FlagHelper {

  /**
   * Checks if a given flagging has a message_digest field with a value.
   *
   * @param \Drupal\flag\FlaggingInterface $flagging
   *   The flagging to check.
   *
   * @return bool
   *   If the flagging has digest.
   */
  public static function isDigestFlagging(FlaggingInterface $flagging): bool {
    if (!self::isDigestFlag($flagging->getFlag())) {
      return FALSE;
    }

    return $flagging->hasField('message_digest') && !$flagging->get('message_digest')->isEmpty();
  }

  /**
   * Checks if a given flag is prefixed with message_subscribe_email config.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to check.
   *
   * @return bool
   *   The email prefix, FALSE otherwise.
   */
  public static function isDigestFlag(FlagInterface $flag): bool {
    return self::isModuleFlagPrefix('message_subscribe_email', $flag);
  }

  /**
   * Checks if a given flag is prefixed with message_subscribe config.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to check.
   *
   * @return bool
   *   The subscribe prefix, FALSE otherwise.
   */
  public static function isSubscribeFlag(FlagInterface $flag): bool {
    return self::isModuleFlagPrefix('message_subscribe', $flag);
  }

  /**
   * Checks if a flag is prefixed according a module configuration.
   *
   * @param string $module_name
   *   The module name wher prefix configuration is set.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to check.
   *
   * @return bool
   *   The configuration prefix.
   */
  protected static function isModuleFlagPrefix(string $module_name, FlagInterface $flag): bool {
    $prefix = self::getFlagPrefix($module_name);
    if (empty($prefix) || !str_starts_with($flag->id(), $prefix . '_')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets the flag prefix from a module configuration.
   *
   * @param string $module_name
   *   The module name wher prefix configuration is set.
   *
   * @return string
   *   The flag prefix.
   */
  public static function getFlagPrefix(string $module_name): string {
    return \Drupal::config("$module_name.settings")->get('flag_prefix');
  }

}
