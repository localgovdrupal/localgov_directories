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
    $field_identifier = $this->facet->getFieldIdentifier();

    // Get passed in facets.
    $temp_query = $this->query->getOriginalQuery();
    $temp_query->preExecute();
    $conditions = &$temp_query->getConditionGroup()->getConditions();

    // Find all the facet condition groups.
    $facet_conditions = [];
    foreach ($conditions as $key => $condition) {
      if ($condition instanceof ConditionGroupInterface) {
        $tags = $condition->getTags();
        foreach($tags as $tag) {
          if (strpos($tag, 'facet:' . $field_identifier . '.') === 0) {
            $facet_conditions[$key] = $tag;
          }
        }
      }
    }

    // Run query elimnating each facet group and return the resulting facets.
    $results = $this->results;
    foreach($facet_conditions as $key => $filter_tag) {
      $group_facets = $this->getResultingFacetsFromFacetGroupExceptOwn($filter_tag);
      
      // Remove any duplicate facets, or they show up multiple times.
      $group_facets_filtered = array_filter($group_facets, function($item) use ($results) {
        $facet_ids = array_column($results, 'filter');
        if (in_array($item['filter'], $facet_ids)) {
          return FALSE;
        }
        return TRUE;
      });
      $results = array_merge($results, $group_facets_filtered);
    }

    // Build facets.
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

  /**
   * Get facets from a facet group except it's own with a corresponding tag
   * 
   * Re-run the search api query, this time removing all the other facet groups
   * except the one specified by the filter tag. The purpose of this is to get
   * the other facets that would be limited by setting of the passed in groups
   * facets. It allows us to see the other possible facets that could be 
   * selected as part of an 'OR' group 'AND' the facets selected in the passed
   * in group.
   *
   * @param String $filter_tag
   *   The search api tag of the facet group.
   * @return Array
   *   Search api query facet results.
   */
  protected function getResultingFacetsFromFacetGroupExceptOwn(String $filter_tag):Array {

    // Set up a special filter query which needs to clone the original.
    $filter_query = clone $this->query->getOriginalQuery();
    $filter_query->preExecute();

    // Find conditions
    $conditions = &$filter_query->getConditionGroup()->getConditions();

    // Store removed conditions so we can reset them.
    $removed_conditions = [];

    // Loop through each conditions, removing ones that are not this filter tag.
    foreach ($conditions as $tag => $condition) {
      if ($condition instanceof ConditionGroupInterface) {
        $tags = $condition->getTags();

        // @todo Check that we are only removing facet conditions.
        if (!in_array($filter_tag, $tags)) {

          // Store the removed conditions and remove it.
          $removed_conditions[$tag] = $conditions[$tag];
          unset($conditions[$tag]);
        }
      }
    }

    // Execute the filter query and get the facets returned.
    $filter_query->execute();
    $facets = $filter_query->getResults()->getExtraData('search_api_facets');
    $filter_query->postExecute();

    // Beacuse for reasons unknown, removing the conditions removes it from all
    // the following queries, even though we are fetching the original query.
    // We can get around that by readding the conditions back in now that we 
    // have the facets we want.
    $conditions = array_merge($conditions, $removed_conditions);

    // Since we will get every facet from the passed in facet group,
    // we need to filter those out so checks with other facet groups will
    // show us only the ones that are reachable from this facet group.
    $facet_type_id = substr($filter_tag, 39);
    $group_facet_ids = \Drupal::entityTypeManager()
      ->getStorage('localgov_directories_facets')
      ->getQuery()
      ->condition('bundle', $facet_type_id)
      ->execute();
    $found_facets = $facets['localgov_directory_facets_filter'] ?? [];
    $found_facets = array_filter($found_facets, function($item) use($group_facet_ids) {
      if (!in_array(intval(trim($item['filter'], '"')), $group_facet_ids)) {
        return TRUE;
      }
      return FALSE;
    });

    // Finally return the found facets.
    return $found_facets;
  }

}
