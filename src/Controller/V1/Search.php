<?php

namespace Drupal\iiif_content_search_api\Controller\V1;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\iiif_content_search_api\Controller\AbstractSearchController;
use Drupal\search_api\Query\ResultSetInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * IIIF Content Search V1 controller.
 */
class Search extends AbstractSearchController {

  /**
   * {@inheritDoc}
   */
  public function processResults(EntityInterface $_entity, array $used, array $unused, int $result_count, int $page, int $page_size, int $max_page, ResultSetInterface $results, Request $request) : array {
    $data = [
      '@context' => 'http://iiif.io/api/presentation/2/context.json',
      '@id' => static::createIdUrl($request, $used)->toString(),
      '@type' => 'sc:AnnotationList',
      'ignored' => array_keys($unused),
      'within' => [
        'id' => static::createIdUrl($request, ['page' => FALSE] + $used)->toString(),
        '@type' => 'sc:Layer',
        'total' => $result_count,
        'first' => static::createIdUrl($request, ['page' => 0] + $used)->toString(),
        'last' => static::createIdUrl($request, ['page' => $max_page] + $used)->toString(),
      ],
      'startIndex' => $page_size * $page,
    ];

    if ($page > 0) {
      $data['prev'] = static::createIdUrl($request, ['page' => $page - 1] + $used)->toString();
    }
    if ($max_page > $page) {
      $data['next'] = static::createIdUrl($request, ['page' => $page + 1] + $used)->toString();
    }

    $data['resources'] = [];

    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $highlights = $result->getExtraData('islandora_hocr_highlights');

      /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $adapter */
      $adapter = $result->getOriginalObject();

      /** @var \Drupal\Core\Entity\ContentEntityInterface $original */
      $original = $adapter->getEntity();

      if (!$original) {
        continue;
      }

      foreach ($this->getLanguageFields($results) as $field) {
        $field_info = $highlights[$field];
        foreach ($field_info['snippets'] as $snippet_index => $snippet) {
          foreach ($snippet['highlights'] as $highlight_group_index => $highlights) {
            foreach ($highlights as $highlight_index => $highlight) {
              $data['resources'][] = [
                '@id' => "{$result->getId()}/{$field}/{$snippet_index}/{$highlight_group_index}/{$highlight_index}",
                '@type' => 'Annotation',
                'motivation' => 'sc:Painting',
                'resource' => [
                  '@type' => 'cnt:ContentAsText',
                  'chars' => $highlight['text'],
                ],
                'on' => Url::fromRoute(
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
    return 'V1';
  }

}
