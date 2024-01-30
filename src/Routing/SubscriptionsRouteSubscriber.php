<?php

namespace Drupal\oe_subscriptions\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class SubscriptionsRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $message_subscribe_routes = [
      // Submodule: message_subscribe_ui.
      'message_subscribe_ui.tab',
      'message_subscribe_ui.tab.flag',
    ];

    foreach ($message_subscribe_routes as $message_subscribe_route) {
      if ($route = $collection->get($message_subscribe_route)) {
        $route->setRequirement('_access', 'FALSE');
      }
    }
  }

}
