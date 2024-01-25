<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions\Trait;

use Drupal\Core\Session\AccountInterface;

/**
 * Support for routes access testing.
 */
trait RouteAccessTrait {

  /**
   * Returns access to the route with a set of parameters.
   *
   * @param string $route_name
   *   The route name.
   * @param array $route_parameters
   *   The route parameters.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   A user account to use for the check. Null to use anonymous.
   *
   * @return bool
   *   True if access is allowed, false otherwise.
   */
  protected function checkRouteAccess(string $route_name, array $route_parameters, AccountInterface $user = NULL): bool {
    $access_check = $this->container->get('access_manager')->checkNamedRoute(
      $route_name,
      $route_parameters,
      $user,
      TRUE,
    );

    return $access_check->isAllowed();
  }

}
