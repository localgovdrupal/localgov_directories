<?php

namespace Drupal\Tests\localgov_directories\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;

/**
 * Tests population of the search sort field.
 *
 * @group localgov_directories
 */
class SearchApiConfigTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'block',
    'field',
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('search_api_task');
    $this->installConfig([
      'node',
      'user',
      'search_api',
      'localgov_directories',
    ]);
  }

  /**
   * Test Search API configuration is applied.
   */
  public function testDirectoryIndexDatasourceConfig() {

    $index = Index::load('localgov_directories_index_default');
    $datasource = $index->getDatasource('entity:node');
    $config = $datasource->getConfiguration();
    $selected = [
      'localgov_directories_page',
    ];
    $this->assertSame($selected, $config['bundles']['selected']);

    $this->container->get('module_installer')->install([
      'localgov_directories_venue',
    ]);

    $index = Index::load('localgov_directories_index_default');
    $datasource = $index->getDatasource('entity:node');
    $config = $datasource->getConfiguration();
    $selected = [
      'localgov_directories_page',
      'localgov_directories_venue',
    ];
    $this->assertSame($selected, $config['bundles']['selected']);
  }

}
