<?php

namespace Drupal\localgov_directories;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\views\Views;

/**
 * Adds views display for the directory channel.
 */
class DirectoryChannelDisplay {

  use StringTranslationTrait;

  /**
   * Gets the "extra fields" for a bundle.
   *
   * @see hook_entity_extra_field_info()
   */
  public function entityExtraFieldInfo() {
    $fields = [];
    $fields['node']['localgov_directory']['display']['localgov_directory_view'] = [
      'label' => $this->t('Directory listing'),
      'description' => $this->t("Output from the embedded view for this channel."),
      'weight' => -20,
      'visible' => TRUE,
    ];

    return $fields;
  }

  /**
   * Adds view with arguements to view render array if required.
   *
   * @see localgov_directories_node_view()
   */
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, $view_mode) {
    // Add view if enabled.
    if ($display->getComponent('localgov_directory_view')) {
      $build['localgov_directory_view'] = $this->getViewEmbed($node);
    }
  }

  /**
   * Retrieves view, and sets render array.
   */
  protected function getViewEmbed(NodeInterface $node) {
    $view = Views::getView('localgov_directory_channel');
    if (!$view || !$view->access('node_embed')) {
      return;
    }
    return [
      '#type' => 'view',
      '#name' => 'localgov_directory_channel',
      '#display_id' => 'node_embed',
      '#arguments' => [$node->id()],
    ];
  }

}
