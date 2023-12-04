<?php

namespace Drupal\iiif_content_search_api\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Dynamic route generation.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructor.
   */
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

      $canonical_route = (clone $canonical_route)
        ->setRequirement('_entity_access', "{$id}.view")
        ->setOption('parameters', ($canonical_route->getOption('parameters') ?? []) + [
          $id => [
            'type' => "entity:{$id}",
          ],
        ]);

      $cs1_route = (clone $canonical_route)
        ->setPath("{$canonical_route->getPath()}/iiif-cs/1")
        ->setDefaults([
          '_controller' => 'iiif_content_search_api.v1.search_controller:search',
          '_title_callback' => 'iiif_content_search_api.v1.search_controller:titleCallback',
          'parameter_name' => $id,
        ])
        ->setOption('no_cache', TRUE);
      $collection->add("entity.{$id}.iiif_content_search.v1", $cs1_route);
      $cs2_route = (clone $canonical_route)
        ->setPath("{$canonical_route->getPath()}/iiif-cs/2")
        ->setDefaults([
          '_controller' => 'iiif_content_search_api.v2.search_controller:search',
          '_title_callback' => 'iiif_content_search_api.v2.search_controller:titleCallback',
          'parameter_name' => $id,
        ])
        ->setOption('no_cache', TRUE);
      $collection->add("entity.{$id}.iiif_content_search.v2", $cs2_route);

      // Set the IIIF Content Search v2 route as the "default" route.
      $default_route = (clone $cs2_route)
        ->setPath("{$canonical_route->getPath()}/iiif-cs");
      $collection->add("entity.{$id}.iiif_content_search", $default_route);
    }
  }

}
