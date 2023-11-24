<?php

namespace Drupal\iiif_content_search_api\Controller\V2\Search;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Search controller.
 */
class Search extends ControllerBase {

  /**
   * Route content callback.
   *
   * @param string $parameter_name
   *   The name of the parameter bearing the "context" entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being served.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route matched for the request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\search_api\SearchApiException
   */
  public function search(string $parameter_name, Request $request, RouteMatchInterface $route_match) {
    /** @var \Drupal\Core\Entity\EntityInterface $_entity */
    $_entity = $route_match->getParameter($parameter_name);
    $all = $request->query->all() + [
      'motivation' => 'highlighting',
      'page_size' => 100,
      'page' => 0,
    ];
    $used_keys = array_fill_keys(['q', 'motivation', 'page_size', 'page'], TRUE);
    $unused = array_diff_key($all, $used_keys);
    $used = array_intersect_key($all, $used_keys);

    $query_string = $used['q'];
    $motivation = $used['motivation'];
    $page_size = $used['page_size'];
    $page = $used['page'];

    // @todo Change up/drop the default.
    $index_id = getenv('IIIF_CONTENT_SEARCH_INDEX_ID') ?: 'default_solr_index';

    $index = Index::load($index_id);

    $query = $index->query();

    $query->keys($query_string);
    // @todo Make base conditions configurable?
    $query->addCondition('field_ancestors', $_entity->id());
    $query->setOption('islandora_hocr_properties', [
      'islandora_hocr_field' => [],
    ]);
    $query->range($page * $page_size, $page_size);

    $results = $query->execute();

    $result_count = $results->getResultCount();
    $max_page = intdiv($result_count, $page_size);

    $data = [
      '@context' => 'http://iiif.io/api/search/2/context.json',
      'id' => static::createIdUrl($request, $used),
      'type' => 'AnnotationPage',
      'ignored' => array_keys($unused),
      'partOf' => [
        'id' => static::createIdUrl($request, ['page' => FALSE] + $used),
        'type' => 'AnnotationCollection',
        'total' => $result_count,
        'first' => [
          'id' => static::createIdUrl($request, ['page' => 0] + $used),
          'type' => 'AnnotationPage',
        ],
        'last' => [
          'id' => static::createIdUrl($request, ['page' => $max_page] + $used),
          'type' => 'AnnotationPage',
        ],
      ],
      'startIndex' => $page_size * $page,
    ];

    if ($page > 0) {
      $data['prev'] = [
        'id' => static::createIdUrl($request, ['page' => $page - 1] + $used),
        'type' => 'AnnotationPage',
      ];
    }
    if ($max_page > $page) {
      $data['next'] = [
        'id' => static::createIdUrl($request, ['page' => $page + 1] + $used),
        'type' => 'AnnotationPage',
      ];
    }

    $data['items'] = [];
    $data['annotations'] = [];

    // Get the additionally-populated property info, so we can identify what fields from the highlighted results correspond to which property.
    $info = $results->getQuery()->getOption('islandora_hocr_properties');
    // This should be an associative array mapping language codes to Solr fields,
    // which can then be found in the $highlights below.
    $language_fields = $info['islandora_hocr_field']['language_fields'];

    /** @var \Drupal\search_api\Item\ItemInterface $result */
    foreach ($results as $result) {
      $highlights = $result->getExtraData('islandora_hocr_highlights');

      /** @var \Drupal\Core\Entity\ContentEntityInterface $original */
      $original = $result->getOriginalObject();

      foreach ($language_fields as $field) {
        $field_info = $highlights[$field];
        foreach ($field_info['snippets'] as $snippet_index => $snippet) {
          foreach ($snippet['highlights'] as $highlight_group_index => $highlights) {
            foreach($highlights as $highlight_index => $highlight) {
              $data['items'][] = [
                'id' => "{$result->getId()}/{$field}/{$snippet_index}/{$highlight_group_index}/{$highlight_index}",
                'type' => 'Annotation',
                'motivation' => $motivation,
                'body' => [
                  'type' => 'TextualBody',
                  'value' => $highlight['text'],
                  'format' => 'text/plain',
                ],
                // @todo Generate the URL to the original object.
                'target' => Url::fromRoute("entity.{$parameter_name}.iiif-p.canvas", [
                  $parameter_name => $_entity->id(),
                  'canvas_type' => $original->getEntityTypeId(),
                  'canvas_id' => $original->id(),
                ])->setAbsolute()->toString(),
//                'target' => $original instanceof EntityInterface ?
//                  $original->toUrl('canonical', [
//                    'fragment' => 'xywh=' . implode(',', [
//                      $highlight['ulx'],
//                      $highlight['uly'],
//                      $highlight['lrx'] - $highlight['ulx'],
//                      $highlight['lry'] - $highlight['uly'],
//                    ]),
//                  ]):
//                  '',
              ];
            }
          }
        }
      }
    }

    return new JsonResponse($data);
  }

  /**
   * Helper; create IDs targeting the given parameters.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The base request.
   * @param array $query_params
   *   The parameters to add and return in a new instance.
   *
   * @return \Drupal\Core\Url
   *   The new URL instance.
   */
  protected static function createIdUrl(Request $request, array $query_params) : Url {
    return Url::createFromRequest($request)
      ->setAbsolute()
      ->setOption('query', $query_params);
  }

  /**
   * Title callback.
   *
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   The entity for which to obtain a title.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being served.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function titleCallback(EntityInterface $_entity, Request $request) {
    return $this->t('IIIF Content Search V2 results for @title, for query @query', [
      '@title' => $_entity->label(),
      '@query' => $request->query->get('q'),
    ]);
  }

}
