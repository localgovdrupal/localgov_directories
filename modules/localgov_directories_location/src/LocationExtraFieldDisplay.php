<?php

namespace Drupal\localgov_directories_location;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\views\Views;

/**
 * Adds views display for the directory channel.
 */
class LocationExtraFieldDisplay {

  use StringTranslationTrait;

  /**
   * Gets the "extra fields" for a bundle.
   *
   * @see hook_entity_extra_field_info()
   */
  public function entityExtraFieldInfo() {
    $fields = [];
    $fields['node']['localgov_directory']['display']['localgov_directory_map'] = [
      'label' => $this->t('Directory map'),
      'description' => $this->t("Output from the embedded map view for this channel."),
      'weight' => 0,
      'visible' => TRUE,
    ];

    return $fields;
  }

  /**
   * Adds view with arguments to view render array if required.
   *
   * @see localgov_directories_node_view()
   */
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, $view_mode) {
    // Add view if enabled.
    if ($display->getComponent('localgov_directory_map')) {
      $build['localgov_directory_map'] = $this->getViewEmbed($node);
    }
  }

  /**
   * Retrieves view, and sets render array.
   */
  protected function getViewEmbed(NodeInterface $node) {
    $view = Views::getView('localgov_directory_channel');
    if (!$view || !$view->access('embed_map')) {
      return;
    }
    return [
      '#type' => 'view',
      '#name' => 'localgov_directory_channel',
      '#display_id' => 'embed_map',
      '#arguments' => [$node->id()],
    ];
  }

}
