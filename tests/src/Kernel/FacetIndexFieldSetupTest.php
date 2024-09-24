<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_directories\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index as SearchIndex;

/**
 * Tests that indexing has been setup on the Facet selection field.
 *
 * @group localgov_directories
 */
class FacetIndexFieldSetupTest extends KernelTestBase {

  use NodeCreationTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * Skip schema checks.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    // Missing schema:
    // - 'content.location.settings.reset_map.position'.
    // - 'content.location.settings.weight'.
    'core.entity_view_display.localgov_geo.area.default',
    'core.entity_view_display.localgov_geo.area.embed',
    'core.entity_view_display.localgov_geo.area.full',
    'core.entity_view_display.geo_entity.area.default',
    'core.entity_view_display.geo_entity.area.embed',
    'core.entity_view_display.geo_entity.area.full',
    // Missing schema:
    // - content.location.settings.geometry_validation.
    // - content.location.settings.multiple_map.
    // - content.location.settings.leaflet_map.
    // - content.location.settings.height.
    // - content.location.settings.height_unit.
    // - content.location.settings.hide_empty_map.
    // - content.location.settings.disable_wheel.
    // - content.location.settings.gesture_handling.
    // - content.location.settings.popup.
    // - content.location.settings.popup_content.
    // - content.location.settings.leaflet_popup.
    // - content.location.settings.leaflet_tooltip.
    // - content.location.settings.map_position.
    // - content.location.settings.weight.
    // - content.location.settings.icon.
    // - content.location.settings.leaflet_markercluster.
    // - content.location.settings.feature_properties.
    'core.entity_form_display.geo_entity.address.default',
    'core.entity_form_display.geo_entity.address.inline',
    // Missing schema:
    // - content.postal_address.settings.providers.
    // - content.postal_address.settings.geocode_geofield.
    'core.entity_form_display.localgov_geo.address.default',
    'core.entity_form_display.localgov_geo.address.inline',
  ];


  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'block',
    'facets',
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

    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');
    $this->installConfig([
      'node',
      'search_api',
      'localgov_directories',
    ]);
  }

  /**
   * Test the existence of the Facet index field.
   *
   * The Search api index field for the Facet field is added when the
   * localgov_directory_facets_select field is added to a content type for the
   * first time.  This content type must be already part of the search index.
   */
  public function testFacetIndexFieldCreation() {

    $search_index = SearchIndex::load('localgov_directories_index_default');
    $facet_index_field = $search_index->getField('localgov_directory_facets_filter');
    $this->assertNull($facet_index_field);

    $dir_entry_content_type = strtolower($this->randomMachineName());
    NodeType::create(['type' => $dir_entry_content_type])->save();

    // Add content type to Search index.
    $this->createEntityReferenceField('node', $dir_entry_content_type, 'localgov_directory_channels', $this->randomString(), 'node');
    // Setup indexing on the Facet field.
    $this->createEntityReferenceField('node', $dir_entry_content_type, 'localgov_directory_facets_select', $this->randomString(), 'localgov_directories_facets');

    $search_index = SearchIndex::load('localgov_directories_index_default');
    $new_facet_index_field = $search_index->getField('localgov_directory_facets_filter');
    $this->assertNotNull($new_facet_index_field);
  }

}
