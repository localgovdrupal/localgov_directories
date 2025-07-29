<?php

/**
 * @file
 * Post update hooks for LocalGov Directories.
 */

use Drupal\search_api\Entity\Index;

/**
 * Updates the node type visibility condition.
 *
 * This was inluded in core D9 as block_post_update_replace_node_type_condition
 * but we had installed condition plugins with this after it might have run.
 * No harm in running this multiple times.
 */
function localgov_directories_post_update_replace_node_type_condition(): void {
  $config_factory = \Drupal::configFactory();
  foreach ($config_factory->listAll('block.block.') as $block_config_name) {
    $block = $config_factory->getEditable($block_config_name);

    if ($block->get('visibility.node_type')) {
      $configuration = $block->get('visibility.node_type');
      $configuration['id'] = 'entity_bundle:node';
      $block->set('visibility.entity_bundle:node', $configuration);
      $block->clear('visibility.node_type');
      $block->save(TRUE);
    }
  }
}

/**
 * Updates the node type visibility condition again.
 *
 * Because we'd still been installing old config.
 * https://github.com/localgovdrupal/localgov_directories/pull/342/files.
 */
function localgov_directories_post_update_replace_node_type_condition_again(): void {
  $config_factory = \Drupal::configFactory();
  foreach ($config_factory->listAll('block.block.') as $block_config_name) {
    $block = $config_factory->getEditable($block_config_name);

    if ($block->get('visibility.node_type')) {
      $configuration = $block->get('visibility.node_type');
      if ($configuration['id'] == 'node_type') {
        $configuration['id'] = 'entity_bundle:node';
        $block->set('visibility.entity_bundle:node', $configuration);
        $block->clear('visibility.node_type');
        $block->save(TRUE);
      }
    }
  }
}

/**
 * Enables the new Sort Field processor.
 *
 * Existing functionality has moved into a processor that needs to enabled.
 */
function localgov_directories_post_update_enable_sort_processor(): void {
  $index = Index::load('localgov_directories_index_default');
  $processors = $index->getProcessors();
  if (!isset($processors['localgov_directories_sort_field'])) {
    $weight = array_reduce(
      $processors,
      fn ($weight, $processor) => $processor->getWeight('preprocess_index') < $weight ? $processor->getWeight('preprocess_index') : $weight,
      0
    );
    $processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($index, 'localgov_directories_sort_field');
    $processor->setWeight('preprocess_index', $weight);
    $index->addProcessor($processor);
    $index->save();
  }
}
