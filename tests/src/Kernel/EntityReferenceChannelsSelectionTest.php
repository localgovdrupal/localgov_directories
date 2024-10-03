<?php

namespace Drupal\Tests\localgov_directories\Kernel;

use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;

/**
 * Tests channels entity reference selection handler.
 *
 * @group localgov_directories
 */
class EntityReferenceChannelsSelectionTest extends KernelTestBase {

  use NodeCreationTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'block',
    'facets',
    'field',
    'filter',
    'image',
    'link',
    'node',
    'media',
    'search_api',
    'search_api_db',
    'system',
    'telephone',
    'text',
    'user',
    'views',
    'localgov_directories',
  ];

  /**
   * Nodes for testing.
   *
   * @var string[][]
   */
  protected $nodes = [];

  /**
   * The selection handler.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
   */
  protected $selectionHandler;

  /**
   * Directory nodes for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $directoryNodes;

  /**
   * Page type for testing.
   *
   * @var string
   */
  protected $pageType;

  /**
   * Other type for testing.
   *
   * @var string
   */
  protected $otherType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'facets',
      'filter',
      'node',
      'search_api',
      'localgov_directories',
    ]);

    // Create test nodes.
    $node1 = $this->createNode(['type' => 'localgov_directory']);
    $node2 = $this->createNode(['type' => 'localgov_directory']);
    $node3 = $this->createNode(['type' => 'localgov_directory']);

    $this->directoryNodes = [];
    foreach ([$node1, $node2, $node3] as $node) {
      $this->directoryNodes[$node->id()] = $node;
    }

    $this->pageType = strtolower($this->randomMachineName());
    NodeType::create(['type' => $this->pageType])->save();
    $handler_settings = [
      'sort' => [
        'field' => 'title',
        'direction' => 'DESC',
      ],
    ];
    $this->createEntityReferenceField('node', $this->pageType, 'localgov_directory_channels', $this->randomString(), 'node', 'localgov_directories_channels_selection', $handler_settings);

    $this->otherType = strtolower($this->randomMachineName());
    NodeType::create(['type' => $this->otherType])->save();
    $handler_settings = [
      'sort' => [
        'field' => 'title',
        'direction' => 'DESC',
      ],
    ];
    $this->createEntityReferenceField('node', $this->otherType, 'localgov_directory_channels', $this->randomString(), 'node', 'localgov_directories_channels_selection', $handler_settings);
  }

  /**
   * Tests the selection handler.
   */
  public function testSelectionHandler() {
    // Check the three directory nodes are returned.
    $field_config = FieldConfig::loadByName('node', $this->pageType, 'localgov_directory_channels');
    $page = $this->createNode(['type' => $this->pageType]);
    $this->selectionHandler = $this->container->get('plugin.manager.entity_reference_selection')->getSelectionHandler($field_config, $page);
    $selection = $this->selectionHandler->getReferenceableEntities();
    foreach ($selection as $node_type => $values) {
      foreach ($values as $nid => $label) {
        $this->assertSame($node_type, $this->directoryNodes[$nid]->bundle());
        $this->assertSame(trim(strip_tags($label)), Html::escape($this->directoryNodes[$nid]->label()));
      }
    }

    // Remove one directory node and make it only accessible to the other type.
    $directory = array_pop($this->directoryNodes);
    $directory->localgov_directory_channel_types = [['target_id' => $this->otherType]];
    $directory->save();
    $selection = $this->selectionHandler->getReferenceableEntities();
    foreach ($selection as $node_type => $values) {
      foreach ($values as $nid => $label) {
        $this->assertSame($node_type, $this->directoryNodes[$nid]->bundle());
        $this->assertSame(trim(strip_tags($label)), Html::escape($this->directoryNodes[$nid]->label()));
      }
    }

    // Check the removed node is accessible to the other type.
    $field_config = FieldConfig::loadByName('node', $this->otherType, 'localgov_directory_channels');
    $other = $this->createNode(['type' => $this->otherType]);
    $this->selectionHandler = $this->container->get('plugin.manager.entity_reference_selection')->getSelectionHandler($field_config, $other);
    $other_selection = $this->selectionHandler->getReferenceableEntities();
    $this->directoryNodes[$directory->id()] = $directory;
    foreach ($other_selection as $node_type => $values) {
      foreach ($values as $nid => $label) {
        $this->assertSame($node_type, $this->directoryNodes[$nid]->bundle());
        $this->assertSame(trim(strip_tags($label)), Html::escape($this->directoryNodes[$nid]->label()));
      }
    }

  }

}
