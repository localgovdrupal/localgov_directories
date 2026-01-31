<?php

namespace Drupal\Tests\localgov_directories\Traits;

/**
 * Trait for setting up directory fields.
 *
 * Expects the methods from \Drupal\Tests\field\Traits\EntityReferenceTestTrait
 * to be present.
 */
trait DirectoryFieldsCreationTrait {

  /**
   * Creates a directory channel selection field.
   *
   * @param string $node_type
   *   The ID of node type to create the field on.
   */
  public function createDirectoryChannelsSelectionField(string $node_type): void {
    $handler_settings = [
      'sort' => [
        'field' => 'title',
        'direction' => 'DESC',
      ],
    ];
    $this->createEntityReferenceField('node', $node_type, 'localgov_directory_channels', $this->randomString(), 'node', 'localgov_directories_channels_selection', $handler_settings);
  }

}

