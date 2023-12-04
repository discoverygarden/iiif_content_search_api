<?php

namespace Drupal\iiif_content_search_api\EventSubscriber\V2;

use Drupal\iiif_presentation_api\Event\V3\ContentEntityExtrasEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber integrating IIIF-CS v2 into IIIF-P v3.
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
      'id' => $object->toUrl('iiif-content-search.v2')
        ->setAbsolute()
        ->toString(),
      'type' => 'SearchService2',
    ]);
  }

}
