<?php

namespace Drupal\Tests\localgov_directories\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacets;
use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacetsType;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index as SearchIndex;

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
   * Directory Channel Node Id.
   *
   * @var string|int|null
   */
  protected $directoryChannelNodeId;

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
    $facet_type_id = $this->randomMachineName();
    $facet_type = LocalgovDirectoriesFacetsType::create([
      'id' => $facet_type_id,
      'label' => $facet_type_id,
    ]);
    $facet_type->save();

    $facet = LocalgovDirectoriesFacets::create([
      'bundle' => $facet_type_id,
      'title' => $this->randomMachineName(),
    ]);
    $facet->save();

    // Directory Channel.
    $directory = $this->createNode([
      'title' => 'Test Channel',
      'type' => 'localgov_directory',
      'status' => NodeInterface::PUBLISHED,
      'localgov_directory_facets_enable' => [$facet_type_id],
    ]);

    $directory->save();

    $this->directoryChannelNodeId = $directory->id();

    // Directory pages.
    for ($j = 1; $j < 3; $j++) {
      $directory_page = $this->createNode([
        'title' => 'Page ' . $j,
        'type' => 'localgov_directories_page',
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [$directory->id()],
        'localgov_directory_facets_select' => [$facet->id()],
      ]);
      $directory_page->save();
    }

    // Directory listings are sourced from a search index.
    SearchIndex::load('localgov_directories_index_default')->indexItems();

    $this->drupalPlaceBlock('facet_block:localgov_directories_facets');
  }

  /**
   * Grab the localgov_directories_facets facet edit page.
   */
  public function testShowResetFilterLink() {

    $id = 'localgov_directories_facets';
    $this->drupalLogin($this->adminUser);

    // Not displaying the reset link..
    $this->drupalGet('node/' . $this->directoryChannelNodeId);
    $this->assertSession()->ElementNotExists('css', '.facets-reset');

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
      'widget_config[hide_reset_when_no_selection]' => FALSE,
    ];

    $this->submitForm($edit, 'Save');
    $this->drupalGet('admin/config/search/facets/localgov_directories_facets/edit');

    $this->assertSession()->checkboxChecked('widget_config[show_numbers]');
    $this->assertSession()->checkboxChecked('widget_config[show_reset_link]');

    // Now displaying the reset link.
    $this->drupalGet('node/' . $this->directoryChannelNodeId);
    $this->assertSession()->ElementExists('css', '.facets-reset');
  }

}
