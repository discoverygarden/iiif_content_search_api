<?php

namespace Drupal\iiif_content_search_api\EventSubscriber\V1;

use Drupal\iiif_presentation_api\Event\V3\ContentEntityExtrasEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber integrating IIIF-CS v1 into IIIF-P v3.
 */
class IiifPresentationV3EventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      ContentEntityExtrasEvent::class => 'addExtra',
    ];
  }

  /**
   * Event callback; add extra manifest data.
   *
   * @param \Drupal\iiif_presentation_api\Event\V3\ContentEntityExtrasEvent $event
   *   The event to which to respond.
   */
  public function addExtra(ContentEntityExtrasEvent $event) : void {
    $object = $event->getObject();
    $normalized = $event->getNormalizedData();

    if ($normalized['type'] != 'Manifest') {
      return;
    }

    $event->addExtra('service', [
      "@context" => "http://iiif.io/api/search/1/context.json",
      "profile" => "http://iiif.io/api/search/1/search",
      'id' => $object->toUrl('iiif-content-search.v1')
        ->setAbsolute()
        ->toString(),
      'type' => 'SearchService1',
    ]);
  }

}
