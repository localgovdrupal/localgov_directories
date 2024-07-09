<?php

namespace Drupal\Tests\localgov_directories\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\localgov_directories\Traits\DirectoryFieldsCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests the view extra field on directory channel nodes.
 *
 * @group localgov_directories
 */
class ViewExtraFieldTest extends KernelTestBase {

  use ContentTypeCreationTrait;

  use NodeCreationTrait;

  use EntityReferenceTestTrait;

  use DirectoryFieldsCreationTrait;

  /**
   * The modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'media',
    'field',
    'filter',
    'text',
    'address',
    'image',
    'link',
    'node',
    'path',
    'path_alias',
    'telephone',
    'views',
    'viewfield',
    'token',
    'search_api',
    'facets',
    'localgov_core',
    'localgov_media',
    'localgov_directories',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig([
      'node',
      'search_api',
      'localgov_directories',
    ]);

    $this->entityTypeManager->getStorage('date_format')->create([
      'id' => 'fallback',
      'label' => 'Fallback',
      'pattern' => 'Y-m-d',
    ])->save();
  }

  /**
   * Tests the directory channel view extra field shows the expected view.
   */
  public function testDirectoryChannelView() {
    // Create a directory channel node.
    $directory_node = $this->createNode([
      'title' => 'Directory',
      'type' => 'localgov_directory',
      'status' => NodeInterface::PUBLISHED,
      'localgov_directory_facets_enable' => [],
      // Need this to stop NodeCreationTrait from trying to set a default text
      // format which doesn't exist.
      'body' => [],
    ]);
    $directory_node->save();

    $view_builder = $this->entityTypeManager->getHandler('node', 'view_builder');
    $build = $view_builder->view($directory_node);
    $build = $view_builder->build($build);

    // Without a view set in the reference field, the default is shown.
    $this->assertEquals('localgov_directory_channel', $build['localgov_directory_view']['#name']);
    $this->assertEquals('node_embed', $build['localgov_directory_view']['#display_id']);

    // Create a view to specifically select. We can just copy the built-in
    // default, since it doesn't actually need to be different.
    $view = $this->entityTypeManager->getStorage('view')->load('localgov_directory_channel');
    $other_view = clone($view);
    $other_view->set('id', 'other_view');
    $other_view->addDisplay('embed', 'Cake', 'other_embed');
    $other_view->save();

    $directory_node->localgov_directory_channel_view->set(0, [
      'target_id' => 'other_view',
      'display_id' => 'other_embed',
    ]);
    $directory_node->save();

    $view_builder = $this->entityTypeManager->getHandler('node', 'view_builder');
    $build = $view_builder->view($directory_node);
    $build = $view_builder->build($build);

    // The shown view is the one set in the view reference field.
    $this->assertEquals('other_view', $build['localgov_directory_view']['#name']);
    $this->assertEquals('other_embed', $build['localgov_directory_view']['#display_id']);
  }


}
