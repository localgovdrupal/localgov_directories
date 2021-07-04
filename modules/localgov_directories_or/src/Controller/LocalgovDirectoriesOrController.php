<?php

namespace Drupal\localgov_directories_or\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\localgov_geo\Entity\LocalgovGeo;
use Drupal\localgov_openreferral\MappingInformation;
use Drupal\node\NodeInterface;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Exception\NotFoundResourceException;

/**
 * Returns responses for LocalGov Directories Open Referral routes.
 */
class LocalgovDirectoriesOrController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * HTTP Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Mapping information service.
   *
   * @var \Drupal\localgov_openreferral\MappingInformation
   */
  protected $mappingInformation;

  /**
   * Controller constructor.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param \Drupal\localgov_openreferral\MappingInformation $mapping_information
   *   Mapping information helper service.
   */
  public function __construct(Request $request, MappingInformation $mapping_information) {
    $this->request = $request;
    $this->mappingInformation = $mapping_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('localgov_openreferral.mapping_information')
    );
  }

  // @todo move to localgov_openreferral - trait?
  //
  // Recreating pager functionality here because without doing something funky
  // to the pager.parameter service it's hard coded to start at 0 for 'page'
  // get param.
  private function initializePager($query) {
    $page = $this->request->query->getInt('page', 1);
    $this->pager['page'] = $page - 1;

    $per_page = $this->request->query->getInt('per_page', 50);
    $this->pager['limit'] = $per_page;

    $count_query = clone $query;
    $this->pager['total'] = $count_query->count()->execute();
    $this->pager['start'] = $this->pager['page'] * $this->pager['limit'];

    $query->range($this->pager['start'], $this->pager['limit']);
  }

  private function outputPager() {
    $total_pages = ceil($this->pager['total'] / $this->pager['limit']);
    $page_number = $this->pager['page'] + 1;
    return [
      'totalElements' => $this->pager['total'],
      'totalPages' => $total_pages,
      'number' => $page_number,
      'size' => $this->pager['limit'],
      'first' => $this->pager['page'] == 0,
      'last' => $page_number == $total_pages,
    ];
  }

  /**
   * Service endpoint.
   */
  public function service(NodeInterface $directory, NodeInterface $service) {
    $response = new ResourceResponse($service, 200);

    $response->addCacheableDependency($service);

    if ($service instanceof FieldableEntityInterface) {
      foreach ($service as $field_name => $field) {
        /** @var \Drupal\Core\Field\FieldItemListInterface $field */
        $field_access = $field->access('view', NULL, TRUE);
        $response->addCacheableDependency($field_access);

        if (!$field_access->isAllowed()) {
          $service->set($field_name, NULL);
        }
      }
    }

    return $response;
  }

  /**
   * Vocabularies list endpoint.
   */
  public function vocabulary(NodeInterface $directory) {
    $facets = $this->directoryFacets($directory);
    $response = new ResourceResponse(array_column($facets, 'bundle'), 200);
    $response->addCacheableDependency($directory);

    return $response;
  }

  private function directoryFacets(NodeInterface $directory) {
    $facets = [];

    foreach ($directory->localgov_directory_facets_enable as $localgov_facet) {
      $facets[] = [
        'entity_type' => 'localgov_directories_facets',
        'bundle' => $localgov_facet->target_id,
      ];
    }

    foreach ($directory->localgov_directory_channel_types->referencedEntities() as $entity_type) {
      // @todo retrieve all taxonomy reference fields.
    }

    // Filter for types defined to map to Open Referral taxonomy.
    $facets = array_filter(
      $facets,
      function ($facet) {
        return (
          ($type = $this->mappingInformation->getPublicType($facet['entity_type'], $facet['bundle']))
          && ($type == 'taxonomy')
        );
      }
    );

    return $facets;
  }

  /**
   * Vocabulary Taxonomy endpoint.
   */
  public function taxonomies(NodeInterface $directory) {
    $facets = $this->directoryFacets($directory);
    $vocabulary = $this->request->query->get('vocabulary');
    $facets_lookup = array_column($facets, 'entity_type', 'bundle');
    if (!isset($facets_lookup[$vocabulary])) {
      throw new NotFoundResourceException();
    }

    $entity_type = $facets_lookup[$vocabulary];
    $taxonomy_query = $this->entityTypeManager()->getStorage($entity_type)->getQuery();
    $taxonomy_query->condition('bundle', $vocabulary);
    if ($entity_type == 'taxonomy_term') {
      if ($this->request->query->get('root_only')) {
        $taxonomy_query->notExists('parent');
      }
      elseif ($parent_id = $this->request->query->get('parent_id')) {
        // @todo machine_name id for controlled vocabulary?
        $taxonomy_query->condition('parent:id', $parent_id);
      }
    }
    $this->initializePager($taxonomy_query);
    $terms = $taxonomy_query->execute();
    if ($terms) {
      $terms = $this->entityTypeManager()->getStorage($entity_type)->loadMultiple($terms);
    }

    $response_array = [];
    $response_array = $this->outputPager();
    $response_array['content'] = $terms;

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheTags([$entity_type . '_list']);

    $response = new ResourceResponse($response_array, 200);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

}
