<?php

namespace Drupal\iiif_content_search_api\Controller\V1;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
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
  public function processResults(EntityInterface $_entity, array $used, array $unused, int $result_count, int $page, int $page_size, int $max_page, ResultSetInterface $results, Request $request, RefinableCacheableDependencyInterface $cache_meta) : array {
    $data = [
      '@context' => 'http://iiif.io/api/presentation/2/context.json',
      '@id' => static::createIdUrlString($request, $used, $cache_meta),
      '@type' => 'sc:AnnotationList',
      'ignored' => array_keys($unused),
      'within' => [
        'id' => static::createIdUrlString($request, ['page' => FALSE] + $used, $cache_meta),
        '@type' => 'sc:Layer',
        'total' => $result_count,
        'first' => static::createIdUrlString($request, ['page' => 0] + $used, $cache_meta),
        'last' => static::createIdUrlString($request, ['page' => $max_page] + $used, $cache_meta),
      ],
      'startIndex' => $page_size * $page,
    ];

    if ($page > 0) {
      $data['prev'] = static::createIdUrlString($request, ['page' => $page - 1] + $used, $cache_meta);
    }
    if ($max_page > $page) {
      $data['next'] = static::createIdUrlString($request, ['page' => $page + 1] + $used, $cache_meta);
    }

    $data['resources'] = [];

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

      $cache_meta->addCacheableDependency($original);

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
              $data['resources'][] = [
                '@id' => "{$result->getId()}/{$field}/{$snippet_index}/{$highlight_group_index}/{$highlight_index}",
                '@type' => 'Annotation',
                'motivation' => 'sc:Painting',
                'resource' => [
                  '@type' => 'cnt:ContentAsText',
                  'chars' => $highlight['text'],
                ],
                'on' => static::createEntityUrl($_entity, $original, $highlight, $cache_meta),
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
