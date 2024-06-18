<?php

namespace Drupal\localgov_directories\Plugin\PreviewLinkAutopopulate;

use Drupal\node\NodeInterface;
use Drupal\preview_link\PreviewLinkAutopopulatePluginBase;

/**
 * Auto-populate directory preview links.
 *
 * @PreviewLinkAutopopulate(
 *   id = "localgov_directories",
 *   label = @Translation("Add all the pages for this directory channel"),
 *   description = @Translation("Add all directory nodes for this channel to preview link."),
 *   supported_entities = {
 *     "node" = {
 *       "localgov_directory",
 *     }
 *   },
 * )
 */
class DirectoryChannel extends PreviewLinkAutopopulatePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPreviewEntities(): array {
    $nodes = [];
    $channel = $this->getEntity();

    // Find all directory pages.
    $pages = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'localgov_directory_channels' => $channel->id(),
      ]);
    foreach ($pages as $page) {
      if ($page instanceof NodeInterface && $page->access('view')) {
        $nodes[] = $page;
      }
    }

    return $nodes;
  }

}
