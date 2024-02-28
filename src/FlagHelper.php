<?php

declare(strict_types=1);

namespace Drupal\oe_subscriptions;

use Drupal\flag\FlagInterface;

/**
 * Helper methods to retrieve information about flags.
 *
 * @internal
 */
final class FlagHelper {

  /**
   * Checks if a given flag is prefixed with message_subscribe config.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to check.
   *
   * @return bool
   *   Whether the flag is a subscribe one.
   */
  public static function isSubscribeFlag(FlagInterface $flag): bool {
    return self::hasModuleFlagPrefix('message_subscribe', $flag);
  }

  /**
   * Gets the flag prefix from a module configuration.
   *
   * @param string $module_name
   *   The module name where prefix configuration is set.
   *
   * @return string
   *   The flag prefix.
   */
  public static function getFlagPrefix(string $module_name): string {
    return \Drupal::config("$module_name.settings")->get('flag_prefix') . '_';
  }

  /**
   * Checks if a flag is prefixed according a module configuration.
   *
   * @param string $module_name
   *   The module name where prefix configuration is set.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to check.
   *
   * @return bool
   *   The configuration prefix.
   */
  public static function hasModuleFlagPrefix(string $module_name, FlagInterface $flag): bool {
    $prefix = self::getFlagPrefix($module_name);
    if (empty($prefix) || !str_starts_with($flag->id(), $prefix)) {
      return FALSE;
    }

    return TRUE;
  }

}
