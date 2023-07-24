<?php

/**
 * @file
 * Post update hooks for LocalGov Directories.
 */

/**
 * Updates the node type visibility condition.
 *
 * This was inluded in core D9 as block_post_update_replace_node_type_condition
 * but we had installed condition plugins with this after it might have run.
 * No harm in running this multiple times.
 */
function localgov_directories_post_update_replace_node_type_condition() {
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
