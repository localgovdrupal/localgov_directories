<?php

declare(strict_types=1);

namespace Drupal\localgov_directories\Plugin\facets\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\Processor\SortProcessorPluginBase;
use Drupal\facets\Result\ResultInterface;
use Drupal\localgov_directories\Constants as Directory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sort Facet items by their weight property.
 *
 * @FacetsProcessor(
 *   id = "weight_property_order",
 *   label = @Translation("Sort by weight"),
 *   description = @Translation("Sorts the widget results by their weight.  Only applies to Facet items with a *weight* property."),
 *   default_enabled = TRUE,
 *   stages = {
 *     "sort" = 50
 *   }
 * )
 */
class WeightOrderProcessor extends SortProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * Compare *Facet items* by the value of their *weight* property.
   */
  public function sortResults(ResultInterface $a, ResultInterface $b) {

    $a_facet_id = $a->getRawValue();
    $b_facet_id = $b->getRawValue();

    $this->loadFacetWeightsOnce($a_facet_id, $b_facet_id);

    $a_weight = $this->facetsWeightMap[$a_facet_id];
    $b_weight = $this->facetsWeightMap[$b_facet_id];

    if ($a_weight === $b_weight) {
      return 0;
    }

    return ($a_weight < $b_weight) ? -1 : 1;
  }

  /**
   * Loads the weight value into the facetWeight property of each Result item.
   *
   * Here we strive to load each Facet item only once for performance reasons.
   * When a Facet item is sent for comparison for the first time, we load its
   * weight and retain it as the value of its *facetWeight* property.
   * Subsequent comparisons reuse this weight value instead of reloading this
   * Facet item.
   */
  protected function loadFacetWeightsOnce(int|string $a_facet_id, int|string $b_facet_id): void {

    if (!array_key_exists($a_facet_id, $this->facetsWeightMap)) {
      $a_facet_entity = $this->dirFacetStorage->load($a_facet_id);
      $this->facetsWeightMap[$a_facet_id] = $a_facet_entity->get('weight')->value ?? 0;
    }

    if (!array_key_exists($b_facet_id, $this->facetsWeightMap)) {
      $b_facet_entity = $this->dirFacetStorage->load($b_facet_id);
      $this->facetsWeightMap[$b_facet_id] = $b_facet_entity->get('weight')->value ?? 0;
    }
  }

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->dirFacetStorage = $entity_type_manager->getStorage(Directory::FACET_CONFIG_ENTITY_ID);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * LocalGov Directory Facets entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $dirFacetStorage;

  /**
   * Mapping between facet id and facet weight.
   *
   * @var array
   */
  protected $facetsWeightMap = [];

}
