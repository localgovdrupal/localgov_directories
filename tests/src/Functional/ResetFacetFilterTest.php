<?php

namespace Drupal\Tests\localgov_directories\Functional;

use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacets;
use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacetsType;
use Drupal\Tests\BrowserTestBase;
use Drupal\node\NodeInterface;

/**
 * Tests the existence of the localgov_directories_facets facet edit page.
 *
 * @group localgov_directories
 */
class ResetFacetFilterTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * A user with permission to bypass content access checks.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'localgov_directories',
    'localgov_directories_db',
    'localgov_directories_page',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer facets',
    ]);

    // To submit a directory we need a facet.
    $type_id = $this->randomMachineName();
    $type = LocalgovDirectoriesFacetsType::create([
      'id' => $type_id,
      'label' => $type_id,
    ]);
    $type->save();

    $facet = LocalgovDirectoriesFacets::create([
      'bundle' => $type_id,
      'title' => $this->randomMachineName(),
    ]);

    $facet->save();

    // Directory Channel.
    $directory = $this->createNode([
      'title' => 'Test Channel',
      'type' => 'localgov_directory',
      'status' => NodeInterface::PUBLISHED,
      'localgov_directory_facets_enable' => [$type_id],
    ]);

    $directory->save();

    $this->directory_channel_node_id = $directory->id();

    // Directory Venue.
    for ($j = 1; $j < 3; $j++) {
      $directory_venue = $this->createNode([
        'title' => 'Page ' . $j,
        'type' => 'localgov_directories_page',
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => ['target_id' => $directory->id()],
        'localgov_directory_facets_select' => [$facet->id()],
      ]);
      $directory_venue->save();

      $this->drupalPlaceBlock('facet_block:localgov_directories_facets');

    }

  }

  /**
   * Grab the localgov_directories_facets facet edit page.
   */
  public function testShowResetFilterLink() {

    $id = 'localgov_directories_facets';
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/config/search/facets/' . $id . '/edit');
    $this->assertSession()->pageTextContains('Edit Facets facet');

    $this->assertSession()->fieldExists('widget_config[show_numbers]');
    $this->assertSession()->checkboxNotChecked('widget_config[show_numbers]');

    $this->assertSession()->fieldExists('widget_config[show_reset_link]');
    $this->assertSession()->checkboxNotChecked('widget_config[show_reset_link]');

    $this->assertSession()->fieldExists('widget_config[hide_reset_when_no_selection]');
    $this->assertSession()->checkboxNotChecked('widget_config[hide_reset_when_no_selection]');

    // Change the facet settings.
    $edit = [
      'widget_config[show_numbers]' => TRUE,
      'widget_config[show_reset_link]' => TRUE,
      'widget_config[hide_reset_when_no_selection]' => TRUE,
    ];

    $this->submitForm($edit, 'Save');
    $this->drupalGet('admin/config/search/facets/localgov_directories_facets/edit');

    $this->assertSession()->checkboxChecked('widget_config[show_numbers]');
    $this->assertSession()->checkboxChecked('widget_config[show_reset_link]');
    $this->assertSession()->checkboxChecked('widget_config[hide_reset_when_no_selection]');

    $this->drupalGet('node/' . $this->directory_channel_node_id);

    $this->assertSession()->pageTextContains('facet-item__count');

  }

}
