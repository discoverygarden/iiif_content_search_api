<?php

namespace Drupal\iiif_content_search_api\Controller\V2;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\iiif_content_search_api\Controller\AbstractSearchController;
use Drupal\search_api\Query\ResultSetInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * IIIF Content Search V2 controller.
 */
class Search extends AbstractSearchController {

  /**
   * {@inheritDoc}
   */
  public function processResults(EntityInterface $_entity, array $used, array $unused, int $result_count, int $page, int $page_size, int $max_page, ResultSetInterface $results, Request $request) : array {
    $motivation = $used['motivation'];
    $data = [
      '@context' => 'http://iiif.io/api/search/2/context.json',
      'id' => static::createIdUrl($request, $used)->toString(),
      'type' => 'AnnotationPage',
      'ignored' => array_keys($unused),
      'partOf' => [
        'id' => static::createIdUrl($request, ['page' => FALSE] + $used)->toString(),
        'type' => 'AnnotationCollection',
        'total' => $result_count,
        'first' => [
          'id' => static::createIdUrl($request, ['page' => 0] + $used)->toString(),
          'type' => 'AnnotationPage',
        ],
        'last' => [
          'id' => static::createIdUrl($request, ['page' => $max_page] + $used)->toString(),
          'type' => 'AnnotationPage',
        ],
      ],
      'startIndex' => $page_size * $page,
    ];

    if ($page > 0) {
      $data['prev'] = [
        'id' => static::createIdUrl($request, ['page' => $page - 1] + $used)->toString(),
        'type' => 'AnnotationPage',
      ];
    }
    if ($max_page > $page) {
      $data['next'] = [
        'id' => static::createIdUrl($request, ['page' => $page + 1] + $used)->toString(),
        'type' => 'AnnotationPage',
      ];
    }

    $data['items'] = [];
    $data['annotations'] = [];

    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $highlights = $result->getExtraData('islandora_hocr_highlights');

      if (empty($highlights)) {
        continue;
      }

      /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $adapter */
      $adapter = $result->getOriginalObject();

      /** @var \Drupal\Core\Entity\ContentEntityInterface $original */
      $original = $adapter->getEntity();

      if (!$original) {
        continue;
      }

      foreach ($this->getLanguageFields($results) as $field) {
        if (empty($highlights[$field])) {
          continue;
        }
        $field_info = $highlights[$field];
        foreach ($field_info['snippets'] as $snippet_index => $snippet) {
          foreach ($snippet['highlights'] as $highlight_group_index => $highlights) {
            foreach ($highlights as $highlight_index => $highlight) {
              $data['items'][] = [
                'id' => "{$result->getId()}/{$field}/{$snippet_index}/{$highlight_group_index}/{$highlight_index}",
                'type' => 'Annotation',
                'motivation' => $motivation,
                'body' => [
                  'type' => 'TextualBody',
                  'value' => $highlight['text'],
                  'format' => 'text/plain',
                ],
                'target' => Url::fromRoute(
                  "entity.{$_entity->getEntityTypeId()}.iiif_p.canvas",
                  [
                    $_entity->getEntityTypeId() => $_entity->id(),
                    'canvas_type' => $original->getEntityTypeId(),
                    'canvas_id' => $original->id(),
                  ],
                  [
                    'fragment' => 'xywh=' . implode(',', [
                      $highlight['ulx'],
                      $highlight['uly'],
                      $highlight['lrx'] - $highlight['ulx'],
                      $highlight['lry'] - $highlight['uly'],
                    ]),
                  ]
                )->setAbsolute()->toString(),
              ];
            }
          }
        }
      }
    }

    return $data;
  }

  /**
   * {@inheritDoc}
   */
  protected static function getVersion() : string {
    return 'V2';
  }

}
