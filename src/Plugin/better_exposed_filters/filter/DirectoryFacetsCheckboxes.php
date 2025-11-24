<?php

namespace Drupal\localgov_directories\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\RadioButtons;
use Drupal\localgov_directories\Constants as Directory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Localgov directories facets widget implementation.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "localgov_directory_facets",
 *   label = @Translation("Localgov directory facets"),
 * )
 */
class DirectoryFacetsCheckboxes extends RadioButtons implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): DirectoryFacetsCheckboxes {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $filter = $this->handler;
    // Form element is designated by the element ID which is user-
    // configurable.
    $field_id = $filter->options['is_grouped'] ? $filter->options['group_info']['identifier'] : $filter->options['expose']['identifier'];

    parent::exposedFormAlter($form, $form_state);

    if (!empty($form[$field_id]['#options'])) {
      $facet_storage = $this->entityTypeManager->getStorage(Directory::FACET_CONFIG_ENTITY_ID);
      $grouped_options = [];
      foreach ($form[$field_id]['#options'] as $key => $option) {
        $facet = $facet_storage->load($key);
        $facet_type = $facet->bundle();
        $grouped_options[$facet_type][$key] = $option;
      }
      $facet_type_storage = $this->entityTypeManager->getStorage(Directory::FACET_TYPE_CONFIG_ENTITY_ID);
      foreach ($grouped_options as $facet_type => $options) {
        $facet_type_entity = $facet_type_storage->load($facet_type);
        $facet_type_label = $facet_type_entity->label();
        $grouped_options[$facet_type_label] = $options;
        unset($grouped_options[$facet_type]);
      }
      $form[$field_id]['#localgov_directory_facet_groups'] = $grouped_options;
      $form[$field_id]['#theme'] = 'bef_checkboxes_directory_facets';
    }
  }

}
