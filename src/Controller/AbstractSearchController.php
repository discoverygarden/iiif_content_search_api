<?php

namespace Drupal\iiif_content_search_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\ResultSetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Abstract search controller to minimize copypasta.
 */
abstract class AbstractSearchController extends ControllerBase {

  /**
   * Drupal's renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Get the index from which to query highlighting results.
   *
   * @return \Drupal\search_api\Entity\Index
   *   The index from which to query highlighting results.
   */
  protected function getIndex() : Index {
    // @todo Change up/drop the default.
    $index_id = getenv('IIIF_CONTENT_SEARCH_INDEX_ID') ?: 'default_solr_index';

    return Index::load($index_id);
  }

  /**
   * Get the field use to generate highlighting results.
   *
   * @return string
   *   The name of the field used for highlighting results.
   */
  protected function getHighlightingField() : string {
    return getenv('IIIF_CONTENT_SEARCH_HIGHLIGHTING_FIELD') ?: 'islandora_hocr_field';
  }

  /**
   * Get the ancestor field to use to filter.
   *
   * @return string
   *   The name of the ancestor field.
   */
  protected function getAncestorField() : string {
    return getenv('IIIF_CONTENT_SEARCH_ANCESTOR_FIELD') ?: 'field_ancestors';
  }

  /**
   * Get the name of the field of which to search for the current entity itself.
   *
   * @return string
   *   The name of the ID field.
   */
  protected function getDocIdField() : string {
    return getenv('IIIF_CONTENT_SEARCH_DOC_ID_FIELD') ?: 'nid';
  }

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
   *   The JSON response.
   */
  public function search(string $parameter_name, Request $request, RouteMatchInterface $route_match) {
    $context = new RenderContext();

    /** @var \Drupal\Core\Cache\CacheableJsonResponse $response */
    $response = $this->renderer->executeInRenderContext($context, function () use ($parameter_name, $request, $route_match) {
      $cache_meta = new CacheableMetadata();
      $cache_meta->addCacheContexts(['route']);

      /** @var \Drupal\Core\Entity\EntityInterface $_entity */
      $_entity = $route_match->getParameter($parameter_name);
      $cache_meta->addCacheableDependency($_entity);
      $all = $request->query->all() + [
        'motivation' => 'highlighting',
        'page_size' => 100,
        'page' => 0,
      ];
      $relevant_query_parameters = [
        'q',
        'motivation',
        'page_size',
        'page',
      ];
      $used_keys = array_fill_keys($relevant_query_parameters, TRUE);
      $unused = array_diff_key($all, $used_keys);
      $used = array_intersect_key($all, $used_keys);

      $cache_meta->addCacheContexts(array_map(function ($param) {
        return "url.query_args:{$param}";
      }, $relevant_query_parameters));

      $query_string = $used['q'];
      $page_size = $used['page_size'];
      $page = $used['page'];

      $query = $this->getIndex()->query();

      $query->keys($query_string);

      $or = $query->createConditionGroup('OR');
      $or->addCondition($this->getAncestorField(), $_entity->id());
      $or->addCondition($this->getDocIdField(), $_entity->id());
      $query->addConditionGroup($or);
      $query->setOption('islandora_hocr_properties', [
        $this->getHighlightingField() => [],
      ]);
      $query->range($page * $page_size, $page_size);

      $results = $query->execute();

      $result_count = $results->getResultCount();
      $max_page = intdiv($result_count, $page_size);

      return new CacheableJsonResponse(
        $this->processResults(
          $_entity,
          $used,
          $unused,
          $result_count,
          $page,
          $page_size,
          $max_page,
          $results,
          $request,
          $cache_meta,
        ),
        headers: [
          'Access-Control-Allow-Credentials' => 'true',
          'Access-Control-Allow-Origin' => '*',
          'Access-Control-Allow-Methods' => 'GET',
        ],
      );
    });

    if (!$context->isEmpty()) {
      $metadata = $context->pop();
      $response->addCacheableDependency($metadata);
    }

    return $response;
  }

  /**
   * Process the results.
   *
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   The entity relative to which results are being generated.
   * @param array $used
   *   The parameters used to make the query.
   * @param array $unused
   *   Parameters passed that are being ignored.
   * @param int $result_count
   *   The number of results.
   * @param int $page
   *   The page of results being processed.
   * @param int $page_size
   *   The number of results per page.
   * @param int $max_page
   *   The total number of pages.
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The set of results.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being served.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cache_meta
   *   Cacheable metadata, if we wish to add any.
   *
   * @return array
   *   The processed result structure in an array, ready to be turned into JSON.
   */
  abstract public function processResults(
    EntityInterface $_entity,
    array $used,
    array $unused,
    int $result_count,
    int $page,
    int $page_size,
    int $max_page,
    ResultSetInterface $results,
    Request $request,
    RefinableCacheableDependencyInterface $cache_meta,
  ) : array;

  /**
   * Helper; get a string representing the version.
   *
   * @return string
   *   A string representing the version.
   */
  abstract protected static function getVersion() : string;

  /**
   * Title callback.
   *
   * @param string $parameter_name
   *   The name of the parameter bearing the "context" entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request being served.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route matched for the request.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title.
   */
  public function titleCallback(string $parameter_name, Request $request, RouteMatchInterface $route_match) {
    /** @var \Drupal\Core\Entity\EntityInterface $_entity */
    $_entity = $route_match->getParameter($parameter_name);
    return $this->t('IIIF Content Search @version results for @title, for query @query', [
      '@version' => static::getVersion(),
      '@title' => $_entity->label(),
      '@query' => $request->query->get('q'),
    ]);
  }

  /**
   * Get all language-specific variants of the highlighting field.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The set of results to examine.
   *
   * @return array
   *   The list of language-specific variants.
   */
  protected function getLanguageFields(ResultSetInterface $results) : array {
    // Get the additionally-populated property info, so we can identify what
    // fields from the highlighted results correspond to which property.
    $info = $results->getQuery()->getOption('islandora_hocr_properties');
    // This should be an associative array mapping language codes to Solr
    // fields, which can then be found in the $highlights below.
    return $info[$this->getHighlightingField()]['language_fields'];
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
   * Helper; deal with cacheable metadata.
   *
   * @see ::createIdUrl()
   *
   * @return string
   *   The URL as a string.
   */
  protected static function createIdUrlString(Request $request, array $query_params, RefinableCacheableDependencyInterface $cache_meta) : string {
    $url = static::createIdUrl($request, $query_params);
    $generated_url = $url->toString(TRUE);
    $cache_meta->addCacheableDependency($generated_url);
    return $generated_url->getGeneratedUrl();
  }

  /**
   * Helper; build out highlighting fragment URLs.
   *
   * @param \Drupal\Core\Entity\EntityInterface $_entity
   *   The manifest entity.
   * @param \Drupal\Core\Entity\EntityInterface $original
   *   The canvas entity.
   * @param array $highlight
   *   Array of highlight info.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface $cache_meta
   *   Cacheable metadata to which to add.
   *
   * @return string
   *   The built URL as a string.
   */
  protected static function createEntityUrl(EntityInterface $_entity, EntityInterface $original, array $highlight, RefinableCacheableDependencyInterface $cache_meta) : string {
    $url = Url::fromRoute(
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
    )->setAbsolute();
    $generated_url = $url->toString(TRUE);
    $cache_meta->addCacheableDependency($generated_url);
    return $generated_url->getGeneratedUrl();
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->renderer = $container->get('renderer');

    return $instance;
  }

}
