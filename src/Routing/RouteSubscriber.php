<?php

namespace Drupal\domain_simple_sitemap\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    // Change controller class and method for simple sitemap.
    if ($route = $collection->get('simple_sitemap.sitemap')) {
      $route->addDefaults(['_controller' => '\Drupal\domain_simple_sitemap\Controller\AprolisDomainAccessSitemapController::getSitemap']);
    }
    if ($route = $collection->get('simple_sitemap.sitemaps')) {
      $route->addDefaults(['_controller' => '\Drupal\domain_simple_sitemap\Controller\AprolisDomainAccessSitemapController::getSitemap']);
    }
    if ($route = $collection->get('simple_sitemap.chunk_fallback')) {
      $route->addDefaults(['_controller' => '\Drupal\domain_simple_sitemap\Controller\AprolisDomainAccessSitemapController::getSitemap']);
    }
    if ($route = $collection->get('simple_sitemap.chunk')) {
      $route->addDefaults(['_controller' => '\Drupal\domain_simple_sitemap\Controller\AprolisDomainAccessSitemapController::getSitemap']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = array('onAlterRoutes', 101);
    return $events;
  }

}
