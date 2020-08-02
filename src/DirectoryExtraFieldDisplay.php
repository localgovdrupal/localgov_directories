<?php

namespace Drupal\localgov_directories;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacets;
use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacetsType;
use Drupal\node\NodeInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds views display for the directory channel.
 */
class DirectoryExtraFieldDisplay implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $pluginBlockManager;

  /**
   * DirectoryExtraFieldDisplay constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   Entity repository.
   * @param \Drupal\Core\Block\BlockManagerInterface $plugin_manager_block
   *   Plugin Block Manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, BlockManagerInterface $plugin_manager_block) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->pluginBlockManager = $plugin_manager_block;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('plugin.manager.block')
    );
  }

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
    $fields['node']['localgov_directory']['display']['localgov_directory_facets'] = [
      'label' => $this->t('Directory facets'),
      'description' => $this->t("Output facets block, field alternative to enabling the block."),
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
    if ($display->getComponent('localgov_directory_facets')) {
      $build['localgov_directory_facets'] = $this->getFacetsBlock($node);
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

  /**
   * Retrieves the facets block for a directory.
   */
  protected function getFacetsBlock(NodeInterface $node) {
    // The facet manager build needs the results of the query. Which might not
    // have been run by our nicely lazy loaded views render array.
    $view = Views::getView('localgov_directory_channel');
    $view->setArguments([$node->id()]);
    $view->execute('node_embed');

    $block = $this->pluginBlockManager->createInstance('facet_block' . PluginBase::DERIVATIVE_SEPARATOR . 'localgov_directories_facets');
    return $block->build();
  }

  /**
   * Prepares variables for our bundle grouped facets item list template.
   *
   * @see templates/facets-item-list--links--localgov-directories-facets.tpl.php
   * @see localgov_directories_preprocess_facets_item_list()
   */
  public function preprocessFacetList(array &$variables) {
    $facet_storage = $this->entityTypeManager
      ->getStorage('localgov_directories_facets');
    $group_items = [];
    foreach ($variables['items'] as $key => $item) {
      if ($entity = $facet_storage->load($item['value']['#attributes']['data-drupal-facet-item-value'])) {
        assert($entity instanceof LocalgovDirectoriesFacets);
        $group_items[$entity->bundle()]['items'][$key] = $item;
      }
    }
    $type_storage = $this->entityTypeManager
      ->getStorage('localgov_directories_facets_type');
    foreach ($group_items as $bundle => $items) {
      $entity = $type_storage->load($bundle);
      assert($entity instanceof LocalgovDirectoriesFacetsType);
      $group_items[$bundle]['title'] = Html::escape($this->entityRepository->getTranslationFromContext($entity)->label());
    }
    $variables['items'] = $group_items;
  }

}
