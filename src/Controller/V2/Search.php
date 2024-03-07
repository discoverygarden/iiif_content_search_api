<?php

namespace Drupal\iiif_content_search_api\Controller\V2;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
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
  public function processResults(EntityInterface $_entity, array $used, array $unused, int $result_count, int $page, int $page_size, int $max_page, ResultSetInterface $results, Request $request, RefinableCacheableDependencyInterface $cache_meta) : array {
    $motivation = $used['motivation'];
    $data = [
      '@context' => 'http://iiif.io/api/search/2/context.json',
      'id' => static::createIdUrlString($request, $used, $cache_meta),
      'type' => 'AnnotationPage',
      'ignored' => array_keys($unused),
      'partOf' => [
        'id' => static::createIdUrlString($request, ['page' => FALSE] + $used, $cache_meta),
        'type' => 'AnnotationCollection',
        'total' => $result_count,
        'first' => [
          'id' => static::createIdUrlString($request, ['page' => 0] + $used, $cache_meta),
          'type' => 'AnnotationPage',
        ],
        'last' => [
          'id' => static::createIdUrlString($request, ['page' => $max_page] + $used, $cache_meta),
          'type' => 'AnnotationPage',
        ],
      ],
      'startIndex' => $page_size * $page,
    ];

    if ($page > 0) {
      $data['prev'] = [
        'id' => static::createIdUrlString($request, ['page' => $page - 1] + $used, $cache_meta),
        'type' => 'AnnotationPage',
      ];
    }
    if ($max_page > $page) {
      $data['next'] = [
        'id' => static::createIdUrlString($request, ['page' => $page + 1] + $used, $cache_meta),
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
                'target' => static::createEntityUrl($_entity, $original, $highlight, $cache_meta),
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
