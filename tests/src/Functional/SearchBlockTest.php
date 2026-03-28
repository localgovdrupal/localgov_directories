<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_directories\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\NodeInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests for the Directory search block.
 *
 * Tests include:
 * - Prepopulated search field bug scenario.
 */
class SearchBlockTest extends BrowserTestBase {

  use FieldUiTestTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * Tests the search field.
   *
   * When a Directory channel page is loaded, the search field in the channel
   * search block should be empty.
   */
  public function testIsSearchFieldEmpty(): void {

    $dir_channel_node = $this->createNode([
      'title' => 'I am a directory channel page',
      'type'  => 'localgov_directory',
    ]);
    $dir_channel_node->save();
    $dir_channel_page_path = $dir_channel_node->toUrl()->toString();

    $this->drupalPlaceBlock('localgov_directories_channel_search_block', [
      'context_mapping' => ['node' => '@node.node_route_context:node'],
    ]);

    $search_text = 'Nothing in particular';
    $this->drupalGet($dir_channel_page_path, ['query' => ['search_api_fulltext' => $search_text]]);

    // Reload directory channel, but don't search anything.
    $this->drupalGet($dir_channel_page_path);
    // The above mentioned search text should *not* be present on the page.
    $this->assertSession()->elementExists('css', '[data-drupal-selector=edit-search-api-fulltext][value=""]');
  }

  /**
   * Test the search block is added to a new directory entry content type.
   */
  public function testSearchBlockAddedToEntryContentTypes() :void {

    $adminUser = $this->drupalCreateUser([
      'bypass node access',
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'administer nodes',
    ]);
    $this->drupalLogin($adminUser);

    $dir_channel_node = $this->createNode([
      'title' => 'I am a directory channel page',
      'type'  => 'localgov_directory',
      'status' => NodeInterface::PUBLISHED,
      'localgov_directory_facets_enable' => [],
    ]);
    $dir_channel_node->save();

    $this->drupalPlaceBlock('localgov_directories_channel_search_block', [
      'id' => 'localgov_directories_channel_search_block_stark',
      'context_mapping' => ['node' => '@node.node_route_context:node'],
      'visibility' => [
        'entity_bundle:node' => [
          'id' => 'entity_bundle:node',
          'negate' => FALSE,
          'context_mapping' => [
            'node' => '@node.node_route_context:node',
          ],
          'bundles' => [
            'localgov_directory', 'localgov_directory',
          ],
        ],
      ],
    ]);

    // Create a directory entry content type.
    $this->createContentType(['type' => 'directory_entry']);

    $field_storage = FieldStorageConfig::load('node.localgov_directory_channels');
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'directory_entry',
      'label' => 'Channels',
    ]);
    $field->save();

    // Remove the search from manage display.
    // @todo Check if this should be removed after adjusting the block.
    $entity_view_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_view_display')
      ->load('node.directory_entry.default');
    if ($entity_view_display) {
      $entity_view_display->removeComponent('localgov_directory_search')->save();
    }

    // Allow directory entry to be inside directory channel.
    $dir_channel_node->localgov_directory_channel_types = [
      'target_id' => 'directory_entry',
    ];
    $dir_channel_node->save();

    // Create a directory entry.
    $dir_entry_node = $this->createNode([
      'title' => 'I am a directory entry page',
      'type'  => 'directory_entry',
      'status' => NodeInterface::PUBLISHED,
      'localgov_directory_channels' => [
        'target_id' => $dir_channel_node->id(),
      ],
    ]);
    $dir_entry_node->save();

    // Verify the search block is present on the entry.
    $dir_entry_node_path = $dir_entry_node->toUrl()->toString();
    $this->drupalGet($dir_entry_node_path);
    $this->assertSession()->pageTextContains('Search I am a directory channel page');

  }

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'localgov_directories',
    'field_ui',
    'block',
    'localgov_directories_db',
  ];

}
