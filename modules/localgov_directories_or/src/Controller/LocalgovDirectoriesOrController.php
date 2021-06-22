<?php

namespace Drupal\localgov_directories_or\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\localgov_geo\Entity\LocalgovGeo;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for LocalGov Directories Open Referral routes.
 */
class LocalgovDirectoriesOrController extends ControllerBase implements ContainerInjectionInterface {

  protected Request $request;

  public function __construct(Request $request) {
    $this->request = $request;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
    );
  }

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
   * Services list `/service` endpoint.
   */
  public function servicesList(NodeInterface $directory) {
    $params = $this->serviceParameters();
    // @todo use some params as query conditions.

    $node_query = $this->entityTypeManager()->getStorage('node')->getQuery();
    $node_query->condition('status', 1)
      ->condition('localgov_directory_channels', $directory->id())
      ->sort('changed', 'DESC');
    $this->initializePager($node_query);
    $nodes = $node_query->execute();

    $content = [];
    if ($nodes) {
      $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($nodes);
      foreach ($nodes as $node) {
        $content[] = $this->toService($node);
      }
    }

    $response_array = [];
    $response_array = $this->outputPager();
    $response_array['content'] = $content;

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheTags(['node_list']);

    $response = new CacheableJsonResponse($response_array);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  /**
   * Service endpoint.
   */
  public function service(NodeInterface $directory, NodeInterface $service) {
    $response_array = $this->toService($service);

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheTags($service->getCacheTags());

    $response = new CacheableJsonResponse($response_array);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  /**
   * Field mapping for Directory Entry -> Service.
   *
   * This wants to be better.
   *
   * Thoughts:-
   *   Use RDF to mark out fields, and then serialize/normalize based on them.
   *   The trouble is we don't have a 1:1 entity relationship. There are fields
   *   that become full entities; and enties like location that will have
   *   location and address enties.
   *
   *   Make TypedData and serialize/normalizer for each. Still need to make a
   *   way of mapping from entity -> typeddata.
   */
  private function toService(NodeInterface $node) {
    $service = [];
    $service['id'] = $node->uuid();
    // Original idea here was to make a custom entity type for Organization. But
    // maybe it wants to be something that could be put into directories itself.
    // Then it's a Node. We could still make it a, by default, unlinked title
    // only Node that can be created on the fly?
    $service['organization'] = $this->toOrganization($node);
    $service['name'] = $node->getTitle();
    $service['description'] = $node->body->value;
    // So we have these down as contact.
    // Name and email is maybe more contact.
    // Website is probably more the entry itself?
    $service['email'] = $node->localgov_directory_email ? $node->localgov_directory_email->value : NULL;
    $service['url'] = $node->localgov_directory_website ? $node->localgov_directory_website->first()->uri : NULL;

    // This one is for sure, it's defined as the way locations are added.
    foreach ($node->localgov_location as $location) {
      $service['service_at_locations'][] = [
        'id' => "service:{$service['id']}:location:{$location->entity->uuid()}",
        'location' => $this->toLocation($location->entity),
      ];
    }

    // So there will be different taxonomy fields. This one, but also taxonomies
    // that are added by people configuring the site.
    $taxonomy_fields = ['localgov_directory_facets_select'];
    foreach ($taxonomy_fields as $taxonomy_field) {
      foreach ($node->$taxonomy_field as $taxonomy) {
        $service['service_taxonomys'][] = [
          'id' => "service:{$service['id']}:taxonomy:{$taxonomy->entity->uuid()}",
          'taxonomy' => $this->toTaxonomy($taxonomy->entity),
        ];
      }
    }

    return $service;
  }

  private function toOrganization(NodeInterface $node) {
    $organization = [];
    $organization['id'] = $node->uuid();
    $organization['name'] = $node->getTitle();
    return $organization;
  }

  // So this is toLocationForService. 
  //
  // As a location itself you then query to the services at the location.
  //
  // Here we could pass context, and query and go deeper. How would that
  // work with a serializer?!
  private function toLocation(LocalgovGeo $geo) {
    $location = [];
    $location['id'] = $geo->uuid();
    $location['name'] = $geo->label();
    if (
      $geo->location &&
      $geo->location->first()->geo_type == 'Point'
    ) {
      $location['latitude'] = $geo->location->first()->lat;
      $location['longitude'] = $geo->location->first()->lon;
    }
    if ($address = $geo->postal_address->first()) {
      $location['physical_addresses'][] = [
        'id' => 'address:' . $geo->id(),
        'location_id' => $geo->uuid(),
        'address_1' => $address->address_line1,
        'city' => $address->locality,
        'state_province' => $address->administrative_area,
        'postal_code' => $address->postal_code,
        'country' => $address->country_code,
      ];
    }

    return $location;
  }

  // So we have the 'content' taxonomies from directories,
  // and 'defined' taxonomies drupal ones.
  //
  // Can assume drupal ones will be the only ones with an actual 
  private function toTaxonomy(EntityInterface $taxonomy) {
    return [
      'id' => 'siteid:' . $taxonomy->uuid(),
      'name' => $taxonomy->label(),
      'vocabulary' => $taxonomy->bundle(),
    ];
  }

  // circumstance
  // array[string]
  // An array of identifiers from the Circumstances taxonomy. The query will return services of all service types that are mapped against any given circumstance. Using an OR. THIS IS AN EXTENSION FOR USERS OF LGA TAXONOMIES ONLY
  //
  // coverage
  // string	
  // The postcode to use to check that a service applies to the specified area.
  //
  // day
  // array[string]
  // An array of days. This will match services that have a day equal to or more that the specified value, using the OR operator. If there is more than one of the start_time, end_time and day parameters specified there must be the same number of each.
  //
  // end_time
  // array[string]
  // An array of end times. This will match services that have a end time equal to or more that the specified value, using the OR operator. If there is more than one of the start_time, end_time and day parameters specified there must be the same number of each.
  //
  // include
  // array[string]
  // Enter the name of a service field and it will be included in the result. e.g. service_at_locations
  // maximum_age
  // number($float)
  //  Return services with a maximum age of at equal to or below the specified value.
  //
  // minimum_age
  // number($float)
  // Return services with a minimum age of at least the specified value.
  //
  // need
  // array[string]
  // An array of identifiers from the Needs taxonomy. The query will return services of all service types that are mapped against any given need. THIS IS AN EXTENSION FOR USERS OF LGA TAXONOMIES ONLY
  //
  // HANDLED BY THE PAGER:
  // page
  // integer($int32)
  // The page of the results to show. Page numbers start at 1.
  //
  // HANDLED BY THE PAGER:
  // per_page
  // integer($int32)
  // The number of results per page to show.
  //
  // postcode
  // string
  // The postcode of the person who wishes to use the service. In order to find services that are within a reasonable distance.
  // 
  // proximity
  // number($double)
  // The distance in metres that the person is willing to travel from the target postcode.
  //
  // service_type
  // array[string]
  // An array of service type identifiers. The query will return all services of ANY given service type. THIS EXTENSION MAY BE REPLACED BY A GENERIC SYNTAX FOR COMBINING TERMS FROM ANY TAXONOMY
  //
  // start_time
  // array[string]
  // An array of start times. This will match services that have a start time equal to or more that the specified value, using the OR operator. If there is more than one of the start_time, end_time and day parameters specified there must be the same number of each.
  //
  // taxonomy_id
  // array[string]
  // An array of taxonomy identifiers to filter by. If there is more than one of the taxonomy_type, taxonomy_id and vocabulary parameters specified there must be the same number of each.
  //
  // taxonomy_type
  // array[string]
  // An array of types of taxonomy to filter by. If there is more than one of the taxonomy_type, taxonomy_id and vocabulary parameters specified there must be the same number of each.
  //
  // text
  // string
  // Use text to perform a keyword search on services. This performs a full text search on the service title and description and ranks the results by relevancy.
  //
  // vocabulary
  // array[string]
  // An array of vocabulary identifiers to filter by. If there is more than one of the taxonomy_type, taxonomy_id and vocabulary parameters specified there must be the same number of each. This parameter may not be required if all taxonomy identifiers are unique across all vocabularies used, as will be the case where all taxonomy identifiers are prefixed with a CURIE.
  private function serviceParameters() {
    $param = [];
    return $param;
  }

}
