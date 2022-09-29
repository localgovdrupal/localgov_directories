<?php

declare(strict_types = 1);

namespace Drupal\localgov_directories;

use Drupal\block\BlockInterface;
use Drupal\Core\Config\FileStorage as ConfigFileStorage;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field as SearchIndexField;
use Drupal\views\ViewEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Update index and block configurations for changed entities and fields.
 */
class ConfigurationHelper implements ContainerInjectionInterface {

  /**
   * The Search API directory index.
   *
   * @var \Drupal\search_api\Entity\IndexInterface
   */
  protected ?IndexInterface $index;

  /**
   * The directory view.
   *
   * @var \Drupal\views\ViewEntityInterface
   */
  protected ?ViewEntityInterface $view;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * DirectoryExtraFieldDisplay constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Get index to work on.
   */
  public function getIndex(): IndexInterface {
    return $this->index ?? $this->entityTypeManager->getStorage('search_api_index')->load(Constants::DEFAULT_INDEX);
  }

  /**
   * Get directory view to work on.
   */
  public function getView(): ViewEntityInterface {
    return $this->view ?? $this->entityTypeManager->getStorage('view')->load('localgov_directory_channel');
  }

  /**
   * Act on a Directory channel field being added.
   */
  public function insertedDirectoryChannelField(FieldConfigInterface $field) {
    // Only working for nodes at the moment.
    $entity_type_id = $field->getTargetEntityTypeId();
    $entity_bundle = $field->getTargetBundle();
    // Index changes.
    if ($index = $this->getIndex()) {
      $this->indexAddBundle($index, $entity_type_id, $entity_bundle);
      $this->renderedItemAddBundle($index, $entity_type_id, $entity_bundle);
      $this->indexAddChannelsField($index);
      $index->save();
    }
    if ($view = $this->getView()) {
      $this->viewSetViewMode($view, $entity_type_id, $entity_bundle);
      $view->save();
    }
    $this->addBlockToContentType(Constants::CHANNEL_SEARCH_BLOCK, $entity_bundle);
  }

  /**
   * Act on Directory facet field being added.
   */
  public function insertedFacetField(FieldConfigInterface $field) {
    if ($index = $this->getIndex()) {
      $this->indexAddFacetField($index);
      $index->save();
    }
    $this->createFacet();
  }

  /**
   * Act on Directory title sort field being added.
   */
  public function insertedTitleSortField(FieldConfigInterface $field) {
    if ($index = $this->getIndex()) {
      $this->indexAddTitleSortField($index);
      $index->save();
    }
  }

  /**
   * Create new config entity from given config file.
   *
   * @param string $entity_type
   *   Example: facets_facet.
   * @param string $config_path
   *   Example: modules/foo/config/bar.
   * @param string $config_filename
   *   Example: views.view.bar.
   */
  public function importConfigEntity(string $entity_type, string $config_path, string $config_filename): bool {
    $config_src = new ConfigFileStorage($config_path);
    if (empty($config_src)) {
      return FALSE;
    }

    $config_values = $config_src->read($config_filename);
    if (empty($config_values)) {
      return FALSE;
    }

    try {
      $this->entityTypeManager->getStorage($entity_type)->create($config_values)->save();
    }
    catch (Exception $e) {
      Drupal::service('logger.factory')->get('localgov_directories')->error('Failed to create new config entity: %filename.  Error: %msg', [
        '%filename' => $config_filename,
        '%msg' => $e->getMessage(),
      ]);

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Setup indexing on the Facet selection field of Directory entries.
   *
   * This assumes that the localgov_directory_facets_select field is part of a
   * Directory entry content type.
   */
  protected function indexAddFacetField(IndexInterface $index) {
    if ($index->getField(Constants::FACET_INDEXING_FIELD)) {
      return;
    }

    $field = new SearchIndexField($index, Constants::FACET_INDEXING_FIELD);
    $field->setLabel('Facets');
    $field->setDataSourceId('entity:node');
    $field->setPropertyPath(Constants::FACET_SELECTION_FIELD);
    $field->setType('integer');
    $field->setDependencies([
      'config' => [
        'field.storage.node.' . Constants::FACET_SELECTION_FIELD,
      ],
    ]);
    $index->addField($field);
  }

  /**
   * Setup indexing on the Title Sort field of Directory entries.
   */
  protected function indexAddTitleSortField(IndexInterface &$index) {
    if ($index->getField(Constants::TITLE_SORT_FIELD)) {
      return;
    }

    $field = new SearchIndexField($index, Constants::TITLE_SORT_FIELD);
    $field->setLabel('Title (sort)');
    $field->setDataSourceId('entity:node');
    $field->setPropertyPath(Constants::TITLE_SORT_FIELD);
    $field->setType('string');
    $field->setDependencies([
      'config' => [
        'field.storage.node.' . Constants::TITLE_SORT_FIELD,
      ],
    ]);
    $index->addField($field);
  }

  /**
   * Setup indexing on the Directory channels field of Directory entries.
   */
  protected function indexAddChannelsField(IndexInterface $index) {
    if ($index->getField(Constants::CHANNEL_SELECTION_FIELD)) {
      return;
    }

    $field = new SearchIndexField($index, Constants::CHANNEL_SELECTION_FIELD);
    $field->setLabel('Directory channels');
    $field->setDataSourceId('entity:node');
    $field->setPropertyPath(Constants::CHANNEL_SELECTION_FIELD);
    $field->setType('string');
    $field->setDependencies([
      'config' => [
        'field.storage.node.' . Constants::CHANNEL_SELECTION_FIELD,
      ],
    ]);
    $index->addField($field);
  }

  /**
   * Import config entity for the directory Facet.
   */
  public function createFacet() {
    if ($this->entityTypeManager->getStorage('facets_facet')->load(Constants::FACET_CONFIG_ENTITY_ID)) {
      return;
    }

    $conditional_config_path = \Drupal::service('extension.list.module')->getPath('localgov_directories') . '/config/conditional';
    if ($this->importConfigEntity('facets_facet', $conditional_config_path, Constants::FACET_CONFIG_FILE)) {
      \Drupal::service('config.installer')->installOptionalConfig();
    }
  }

  /**
   * Update a block's visibility.
   *
   * @todo logger
   *
   * The given block should appear sidebar pages for the given content type.
   */
  public function addBlockToContentType(string $block_id, string $content_type): bool {
    $block_config = $this->entityTypeManager->getStorage('block')->load($block_id);
    if (!$block_config instanceof BlockInterface) {
      \Drupal::service('logger.factory')->get('localgov_directories')->error('Block %block-id is missing.  Cannot update its visibility settings.', [
        '%block-id' => $block_id,
      ]);

      return FALSE;
    }

    try {
      $visibility = $block_config->getVisibility();
      $visibility['node_type']['bundles'][$content_type] = $content_type;
      $block_config->setVisibilityConfig('node_type', $visibility['node_type']);
      $block_config->save();
    }
    catch (Exception $e) {
      \Drupal::service('logger.factory')->get('localgov_directories')->error('Failed to add %content-type content type to %block-id block: %error-msg', [
        '%content-type' => $content_type,
        '%block-id' => $block_id,
        '%error-msg' => $e->getMessage(),
      ]);

      return FALSE;
    }

    \Drupal::service('logger.factory')->get('localgov_directories')->notice('Added %content-type content type to %block-id block.', [
      '%content-type' => $content_type,
      '%block-id' => $block_id,
    ]);

    return TRUE;
  }

  protected function indexAddBundle(IndexInterface $index, $entity_type_id, $entity_bundle) {
    $datasource = $this->indexGetDatasource($index, $entity_type_id);
    if (!$datasource) {
      \Drupal::messenger()->addMessage(t('Failed to update the directories search index with new bundle'), MessengerInterface::TYPE_ERROR);
      return;
    }

    $configuration = $datasource->getConfiguration();
    $configuration['bundles']['default'] = FALSE;
    if (!in_array($entity_bundle, $configuration['bundles']['selected'])) {
      $configuration['bundles']['selected'][] = $entity_bundle;
    }
    $datasource->setConfiguration($configuration);
  }

  protected function viewSetViewMode(ViewEntityInterface $view, $entity_type_id, $entity_bundle) {
    // Also set the default view mode for the directory view listing.
    $display = $view->get('display');
    if (isset($display['node_embed']['display_options']['row'])) {
      $display['node_embed']['display_options']['row']['options']['view_modes']['entity:' . $entity_type_id][$entity_bundle] = 'teaser';
    }
    elseif (isset($display['default']['display_options']['row'])) {
      $display['default']['display_options']['row']['options']['view_modes']['entity:' . $entity_type_id][$entity_bundle] = 'teaser';
    }
    $view->set('display', $display);
  }

  protected function renderedItemAddBundle(IndexInterface $index, $entity_type_id, $entity_bundle) {
    $index_field = $index->getField('rendered_item');
    if ($index_field) {
      $configuration = $index_field->getConfiguration();
      $configuration['view_mode']['entity:' . $entity_type_id][$entity_bundle] = 'directory_index';
      $index_field->setConfiguration($configuration);
    }
  }

  protected function indexGetDatasource(IndexInterface $index, $entity_type_id) {
    $datasource = $index->getDatasource('entity:' . $entity_type_id);
    if (!$datasource) {
      // If the content:node datasource has been lost so have the fields most
      // probably and it's more of a mess. But leaving this here anyway.
      $pluginHelper = \Drupal::service('search_api.plugin_helper');
      $datasource = $pluginHelper->createDatasourcePlugins($index, 'entity:' . $entity_type_id);
    }

    return $datasource;
  }

  /**
   * Act on Directory channel field being removed.
   */

  /**
   * Act on Directory facet field being removed.
   */

  /**
   * Act on Directory title sort field being removed.
   */

}
