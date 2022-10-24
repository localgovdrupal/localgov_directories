<?php

namespace Drupal\localgov_directories\Plugin\facets\query_type;

use Drupal\facets\QueryType\QueryTypePluginBase;
use Drupal\facets\Result\Result;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ConditionGroupInterface;

/**
 * AND facet groups while keeping the operator within a facets as an OR.
 *
 * @FacetsQueryType(
 *   id = "localgov_directories_query_type",
 *   label = @Translation("LocalGov Directories Facet Groups AND Query Type"),
 * )
 */
class LocalGovDirectoriesQueryType extends QueryTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $query = $this->query;

    // Only alter the query when there's an actual query object to alter.
    if (!empty($query)) {
      $operator = $this->facet->getQueryOperator();
      $field_identifier = $this->facet->getFieldIdentifier();
      $exclude = $this->facet->getExclude();

      if ($query->getProcessingLevel() === QueryInterface::PROCESSING_FULL) {
        // Set the options for the actual query.
        $options = &$query->getOptions();
        $options['search_api_facets'][$field_identifier] = $this->getFacetOptions();
      }

      // Add the filter to the query if there are active values.
      $active_items = $this->facet->getActiveItems();

      if (count($active_items)) {

        $type_storage = \Drupal::entityTypeManager()
          ->getStorage('localgov_directories_facets');
        $chosen_facets = $type_storage->loadMultiple($active_items);
        foreach ($chosen_facets as $directory_facet) {
          $bundle[$directory_facet->bundle()][] = $directory_facet->id();
        }

        $filter = NULL;
        foreach ($bundle as $bundle_name => $group_items) {
          unset($filter);
          $filter = $query->createConditionGroup($operator, ['facet:' . $field_identifier . '.' . $bundle_name]);
          foreach ($group_items as $value) {
            $filter->addCondition($this->facet->getFieldIdentifier(), $value, $exclude ? '<>' : '=');
          }
          $query->addConditionGroup($filter);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $results = $this->results;
    $query_operator = $this->facet->getQueryOperator();
    $temp_query = $this->query->getOriginalQuery();
    $temp_query->preExecute();
    $conditions = &$temp_query->getConditionGroup()->getConditions();
    foreach ($conditions as $key => $condition) {
      if ($condition instanceof \Drupal\search_api\Query\ConditionGroupInterface) {
        $tags = $condition->getTags();
        foreach($tags as $tag) {
          if (strpos($tag, 'facet:localgov_directory_facets_filter.') === 0) {
            // unset($conditions[$key]);
            $results = array_merge($results, $this->getGroupFacets($tag));
          }
        }
      }
    }
    $filtered_results[] = reset($results);
    foreach ($results as $result) {
      $current_results = array_column($filtered_results, 'filter');
      if (!in_array($result['filter'], $current_results)) {
        $filtered_results[] = $result;
      }
    }
    $results = $filtered_results;
    // $temp_query->execute();
    // $avalible_facets = $temp_query->getResults()->getExtraData('search_api_facets');
    // $results = $avalible_facets['localgov_directory_facets_filter'] ?? $this->results;

    if (!empty($results)) {
      $facet_results = [];
      foreach ($results as $result) {
        if ($result['count'] || $query_operator === 'or') {
          $result_filter = $result['filter'] ?? '';
          if ($result_filter[0] === '"') {
            $result_filter = substr($result_filter, 1);
          }
          if ($result_filter[strlen($result_filter) - 1] === '"') {
            $result_filter = substr($result_filter, 0, -1);
          }
          $count = $result['count'];
          $result = new Result($this->facet, $result_filter, $result_filter, $count);
          $facet_results[] = $result;
        }
      }
      $this->facet->setResults($facet_results);
    }

    return $this->facet;
  }

  protected function getGroupFacets($filter_tag) {
    $filter_query = $this->query->getOriginalQuery();
    $filter_query->preExecute();
    $conditions = &$filter_query->getConditionGroup()->getConditions();
    foreach ($conditions as $tag => $condition) {
      if ($condition instanceof ConditionGroupInterface && $condition->hasTag($filter_tag)) {
        unset($conditions[$tag]);
      }
    }
    $filter_query->execute();
    $facets = $filter_query->getResults()->getExtraData('search_api_facets');
    return $facets['localgov_directory_facets_filter'] ?? [];
  }

}
