<?php

namespace Drupal\iiif_content_search_api\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {

  }

  /**
   * {@inheritDoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      $id = $definition->id();
      $canonical_route = $collection->get("entity.{$id}.canonical");
      if (!$canonical_route) {
        continue;
      }

      $cs2_route = (clone $canonical_route)
        ->setPath("{$canonical_route->getPath()}/iiif-cs/2")
        ->setDefaults([
          '_controller' => 'iiif_content_search_api.v2.search_controller:search',
          '_title_callback' => 'iiif_content_search_api.v2.search_controller:titleCallback',
          'parameter_name' => $id,
        ])
        ->setOption('no_cache', TRUE);
      $collection->add("entity.{$id}.iiif-content-search.v2", $cs2_route);

      // Set the IIIF Content Search v2 route as the "default" route.
      $default_route = (clone $cs2_route)
        ->setPath("{$canonical_route->getPath()}/iiif-cs")
      ;
      $collection->add("entity.{$id}.iiif-content-search", $default_route);
    }
  }

}
