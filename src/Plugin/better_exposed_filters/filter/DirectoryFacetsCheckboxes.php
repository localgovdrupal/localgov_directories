<?php

namespace Drupal\localgov_directories\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\RadioButtons;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\localgov_directories\DirectoryExtraFieldDisplay;

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
   * Class resolver service.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Constructs a DirectoryFacetsCheckboxes object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   Class resolver service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, ClassResolverInterface $class_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): DirectoryFacetsCheckboxes {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('class_resolver'),
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

      // Use the groupDirFacetItems from the DirectoryExtraFieldDisplay class.
      // This is the same as the facets block, which will group and filter
      // the avalible facets to the relevant directory channel.
      $grouped_items = $this->classResolver
        ->getInstanceFromDefinition(DirectoryExtraFieldDisplay::class)
        ->groupDirFacetItems($form[$field_id]['#options']);
      $form[$field_id]['#localgov_directory_facet_groups'] = $grouped_items;
      $form[$field_id]['#theme'] = 'bef_checkboxes_directory_facets';
    }
  }

}
