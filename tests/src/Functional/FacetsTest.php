<?php

namespace Drupal\Tests\localgov_directories\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\facets\Entity\Facet;
use Drupal\localgov_directories\Constants as Directory;
use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacets;
use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacetsType;
use Drupal\node\NodeInterface;

/**
 * Tests facets on a directory channel as a filter.
 *
 * @group localgov_directories
 */
class FacetsTest extends BrowserTestBase {

  use NodeCreationTrait;
  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'localgov_search_db',
    'localgov_directories_db',
    'localgov_directories_page',
  ];

  /**
   * Facet labels.
   *
   * Used for reference in each test.
   *
   * @var array
   */
  protected $facetLabels = [];

  /**
   * Facet entities.
   *
   * Used to set facets accross each test.
   *
   * @var array
   */
  protected $facetEntities = [];

  /**
   * Channel node page.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $channelNode;

  /**
   * Set up users, node and facets.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('facet_block:localgov_directories_facets', []);

    // Set up facet types.
    $facet_types = [
      'Group 1 ' . $this->randomMachineName(8),
      'Group 2 ' . $this->randomMachineName(8),
    ];
    foreach ($facet_types as $type_id) {
      $type = LocalgovDirectoriesFacetsType::create([
        'id' => $type_id,
        'label' => $type_id,
      ]);
      $type->save();
      $facet_type_entities[] = $type;
    }

    // Set up facets.
    $facets = [
      [
        'bundle' => $facet_types[0],
        'title' => 'Facet 1 ' . $this->randomMachineName(8),
      ],
      [
        'bundle' => $facet_types[0],
        'title' => 'Facet 2 ' . $this->randomMachineName(8),
      ],
      [
        'bundle' => $facet_types[1],
        'title' => 'Facet 3' . $this->randomMachineName(8),
      ],
      [
        'bundle' => $facet_types[1],
        'title' => 'Facet 4 ' . $this->randomMachineName(8),
      ],
    ];
    foreach ($facets as $facet_item) {
      $facet = LocalgovDirectoriesFacets::create($facet_item);
      $facet->save();
      $this->facetEntities[] = $facet;
    }
    $this->facetLabels = array_column($facets, 'title');

    // Set up a directory channel and assign the facets to it.
    $body = [
      'value' => 'Science is the search for truth, that is the effort to understand the world: it involves the rejection of bias, of dogma, of revelation, but not the rejection of morality.',
      'summary' => 'One of the greatest joys known to man is to take a flight into ignorance in search of knowledge.',
    ];

    $this->channelNode = $this->createNode([
      'title' => 'Directory channel',
      'type' => 'localgov_directory',
      'status' => NodeInterface::PUBLISHED,
      'body' => $body,
      'localgov_directory_channel_types' => [
        [
          'target_id' => 'localgov_directories_page',
        ],
      ],
      'localgov_directory_facets_enable' => [
        [
          'target_id' => $facet_types[0],
        ],
        [
          'target_id' => $facet_types[1],
        ],
      ],
    ]);
  }

  /**
   * Test facets filter with And groups.
   *
   * Verifies that the correct entries are visible using an OR filter within
   * facet groups and a AND filter accross the groups.
   */
  public function testFacetsGroupFilters() {

    $node_titles_w_nid = $this->createAtestDirectory();
    $node_titles = array_values($node_titles_w_nid);

    // Run cron so the directory entries are indexed.
    $this->cronRun();

    // Check facets and check the right entries are shown.
    $directory_url = $this->channelNode->toUrl()->toString();
    $this->drupalGet($directory_url);

    // Initially all four should be available.
    $this->assertSession()->pageTextContains($node_titles[0]);
    $this->assertSession()->pageTextContains($node_titles[1]);
    $this->assertSession()->pageTextContains($node_titles[2]);
    $this->assertSession()->pageTextContains($node_titles[3]);

    // Facet 1.
    // Click facet 1, should show entry 1, 3 and 4.
    $this->getSession()->getPage()->clickLink($this->facetLabels[0]);
    $this->assertSession()->pageTextContains($node_titles[0]);
    $this->assertSession()->pageTextNotContains($node_titles[1]);
    $this->assertSession()->pageTextContains($node_titles[2]);
    $this->assertSession()->pageTextContains($node_titles[3]);

    // Facet 1 OR Facet 2.
    // Click facet 2 (with 1 still clicked), should show entry 1, 2, 3 and 4.
    $this->getSession()->getPage()->clickLink($this->facetLabels[1]);
    $this->assertSession()->pageTextContains($node_titles[0]);
    $this->assertSession()->pageTextContains($node_titles[1]);
    $this->assertSession()->pageTextContains($node_titles[2]);
    $this->assertSession()->pageTextContains($node_titles[3]);

    // Facet 1 AND Facet 3.
    // Click facet 2 to deselect, click facet 3 (with 1 still clicked),
    // should show entry 3 and 4.
    $this->getSession()->getPage()->clickLink($this->facetLabels[1]);
    $this->getSession()->getPage()->clickLink($this->facetLabels[2]);
    $this->assertSession()->pageTextNotContains($node_titles[0]);
    $this->assertSession()->pageTextNotContains($node_titles[1]);
    $this->assertSession()->pageTextContains($node_titles[2]);
    $this->assertSession()->pageTextContains($node_titles[3]);

    // Facet 1 AND (Facet 3 OR Facet 4).
    // Click facet 4 (with 1 and 3 still clicked),
    // should show entry 3 and 4.
    $this->getSession()->getPage()->clickLink($this->facetLabels[3]);
    $this->assertSession()->pageTextNotContains($node_titles[0]);
    $this->assertSession()->pageTextNotContains($node_titles[1]);
    $this->assertSession()->pageTextContains($node_titles[2]);
    $this->assertSession()->pageTextContains($node_titles[3]);

    // Facet 1 AND Facet 4.
    // Click facet 3 to deselect (with 1 and 4 still clicked),
    // should show entry 4 only.
    $this->getSession()->getPage()->clickLink($this->facetLabels[2]);
    $this->assertSession()->pageTextNotContains($node_titles[0]);
    $this->assertSession()->pageTextNotContains($node_titles[1]);
    $this->assertSession()->pageTextNotContains($node_titles[2]);
    $this->assertSession()->pageTextContains($node_titles[3]);

    // (Facet 1 OR Facet 2) AND (Facet 3 OR Facet 4).
    // Click facet 2 and 3 (with 1 and 4 still clicked),
    // all facets selected, but should only show entry 3 and 4.
    $this->getSession()->getPage()->clickLink($this->facetLabels[1]);
    $this->getSession()->getPage()->clickLink($this->facetLabels[2]);
    $this->assertSession()->pageTextNotContains($node_titles[0]);
    $this->assertSession()->pageTextNotContains($node_titles[1]);
    $this->assertSession()->pageTextContains($node_titles[2]);
    $this->assertSession()->pageTextContains($node_titles[3]);
  }

  /**
   * Test facet selection shows possible facets that could be selected.
   *
   * Verifies that when facets are selected, facets that would offer a possible
   * selection are visible and not hidden. This occurs due to the way facets are
   * generated from the result set. Since each facet group is an OR, if there
   * are entries that could be shown by selecting OR in the group whilst
   * applying the AND filter from the other groups, they need to be selectable.
   */
  public function testFacetSearchShowsAccessibleFacet() {

    // Set up some directory entries .
    $directory_nodes = [
      // Entry 1 has facet 1 and 3.
      [
        'title' => 'Entry 1 ' . $this->randomMachineName(8),
        'type' => 'localgov_directories_page',
        'body' => [
          'value' => 'Contains facet 1 and facet 3.',
        ],
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [
          [
            'target_id' => $this->channelNode->id(),
          ],
        ],
        'localgov_directory_facets_select' => [
          [
            'target_id' => $this->facetEntities[0]->id(),
          ],
          [
            'target_id' => $this->facetEntities[2]->id(),
          ],
        ],
      ],
      [
        // Entry 2 has facet 1 and 4.
        'title' => 'Entry 2 ' . $this->randomMachineName(8),
        'type' => 'localgov_directories_page',
        'body' => [
          'value' => 'Contains facet 1 and facet 4.',
        ],
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [
          [
            'target_id' => $this->channelNode->id(),
          ],
        ],
        'localgov_directory_facets_select' => [
          [
            'target_id' => $this->facetEntities[0]->id(),
          ],
          [
            'target_id' => $this->facetEntities[3]->id(),
          ],
        ],
      ],
      [
        // Entry 3 has facet 2 only.
        'title' => 'Entry 3 ' . $this->randomMachineName(8),
        'type' => 'localgov_directories_page',
        'body' => [
          'value' => 'Contains facet 2 only.',
        ],
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [
          [
            'target_id' => $this->channelNode->id(),
          ],
        ],
        'localgov_directory_facets_select' => [
          [
            'target_id' => $this->facetEntities[1]->id(),
          ],
        ],
      ],
      [
        // Entry 4 has facet 2 and 4.
        'title' => 'Entry 4 ' . $this->randomMachineName(8),
        'type' => 'localgov_directories_page',
        'body' => [
          'value' => 'Contains facet 2 and facet 4.',
        ],
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [
          [
            'target_id' => $this->channelNode->id(),
          ],
        ],
        'localgov_directory_facets_select' => [
          [
            'target_id' => $this->facetEntities[1]->id(),
          ],
          [
            'target_id' => $this->facetEntities[3]->id(),
          ],
        ],
      ],
    ];

    foreach ($directory_nodes as $node) {
      $this->createNode($node);
    }

    // Run cron so the directory entries are indexed.
    $this->cronRun();

    // Check facets and check the right entries are shown.
    $directory_url = $this->channelNode->toUrl()->toString();
    $this->drupalGet($directory_url);

    // Click facet 1.
    // Applies condition entries have facet 1.
    // For each facet group, we are testing for possible facets in each group,
    // Eg. In group 2 we look for facets which would apply with facet 1 from
    // group 1 also selected.
    // Show facets where entries would show for:-
    // - group 1 AND no restriction.
    // - group 2 AND facet 1.
    $this->getSession()->getPage()->clickLink($this->facetLabels[0]);

    // Assert that facets 1, 2, 3 and 4 are visible.
    // Because entry 2 has facet 2 which is in the same group as facet 1,
    // user could click on facet 2 as an OR condition even though entry 2
    // is not visible. Facet 4 will be visible as entry 2 has facet 1 and 4.
    $this->assertSession()->pageTextContains($this->facetLabels[0]);
    $this->assertSession()->pageTextContains($this->facetLabels[1]);
    $this->assertSession()->pageTextContains($this->facetLabels[2]);
    $this->assertSession()->pageTextContains($this->facetLabels[3]);

    // Click facet 3.
    // Applies condition entries have facet 1 AND facet 3.
    // Now each group will have a filter applied, all facets from group 1 which
    // would apply when facet 3 is selected, and all facets from group 2 which
    // would apply when facet 1 is selected, and then the results are combined.
    // Show facets where entries would show for:-
    // - group 1 AND facet 3.
    // - group 2 AND facet 1.
    $this->getSession()->getPage()->clickLink($this->facetLabels[2]);

    // Assert that facets 1, 3 and 4 are visible (2 should be hidden).
    // Since the AND condition that is now applied from facet 3 will
    // eliminate entry 2, so appling it would have no effect. Facet 4 will still
    // be visible as Entry 2 has facet 1 and facet 4, so user could click on
    // on facet 4 and see entry 2.
    $this->assertSession()->pageTextContains($this->facetLabels[0]);
    $this->assertSession()->pageTextNotContains($this->facetLabels[1]);
    $this->assertSession()->pageTextContains($this->facetLabels[2]);
    $this->assertSession()->pageTextContains($this->facetLabels[3]);

    // Click facet 4.
    // Applies condition entries have facet 1 AND (facet 3 OR facet 4).
    // Show facets where entries would show for:-
    // - group 1 AND (facet 3 or facet 4).
    // - group 2 AND facet 1.
    $this->getSession()->getPage()->clickLink($this->facetLabels[3]);

    // Assert that facets 1, 2, 3 and 4 are visible.
    // Since entry 4 has facet 2 and facet 4, when the AND condition is applied
    // from the second facet group (facets 3 & 4) this will allow facet 2 to be
    // selected as it selected it would now produce a valid result.
    $this->assertSession()->pageTextContains($this->facetLabels[0]);
    $this->assertSession()->pageTextContains($this->facetLabels[1]);
    $this->assertSession()->pageTextContains($this->facetLabels[2]);
    $this->assertSession()->pageTextContains($this->facetLabels[3]);

    // Click to deselect facet 1 and 3 and then click to select facet 2.
    // Applies condition entries have facet 2 AND facet 4.
    // Show facets where entries would show for:-
    // - group 1 AND facet 4.
    // - group 2 AND facet 2.
    $this->getSession()->getPage()->clickLink($this->facetLabels[0]);
    $this->getSession()->getPage()->clickLink($this->facetLabels[2]);
    $this->getSession()->getPage()->clickLink($this->facetLabels[1]);

    // Assert that facets 1, 2 and 4 are visible (3 should be hidden).
    // Since the AND condition from the first facet group will only apply to
    // facet 4 and the AND condition from the second group will apply to facet 1
    // OR facet 2 (entry 4 has facets 2 & 4, not shown entry 2 has facet 1 & 3
    // so facet 1 is reachable and will genrate a valid result).
    $this->assertSession()->pageTextContains($this->facetLabels[0]);
    $this->assertSession()->pageTextContains($this->facetLabels[1]);
    $this->assertSession()->pageTextNotContains($this->facetLabels[2]);
    $this->assertSession()->pageTextContains($this->facetLabels[3]);

    // Click to deselect facet 4.
    // Applies conditions entries have facet 2.
    // Show facets where entries would show for:-
    // - group 1 AND no restriction.
    // - group 2 AND facet 2.
    $this->getSession()->getPage()->clickLink($this->facetLabels[3]);

    // Assert that facet 1, 2 and 4 are visible (3 should be hidden).
    // Since no AND condition applies from the second facet group, facet 1
    // can be potentially selected in an OR group with facet 2 as the hidden
    // entry 1 has facet 1. The And condition from the first group with facet 2
    // prevents facet 3 from being reachable, as no entries have facet 2 and 3.
    $this->assertSession()->pageTextContains($this->facetLabels[0]);
    $this->assertSession()->pageTextContains($this->facetLabels[1]);
    $this->assertSession()->pageTextNotContains($this->facetLabels[2]);
    $this->assertSession()->pageTextContains($this->facetLabels[3]);
  }

  /**
   * Tests setup of the Facets form checkbox widget.
   */
  public function testFacetsFormWidget(): void {

    // Setup the Facets form checkbox widget (AKA "Checkboxes (inside form)")
    // for directory facets.
    $this->container->get('module_installer')->install(['facets_form']);

    $dir_facet = Facet::load(Directory::FACET_CONFIG_ENTITY_ID);
    $dir_facet->setOnlyVisibleWhenFacetSourceIsVisible(FALSE);
    $dir_facet->setWidget('facets_form_checkbox');
    $dir_facet->save();
    $this->drupalPlaceBlock(Directory::FACETS_FORM_DIR_FACET_BLOCK_PLUGIN_ID);

    $this->createAtestDirectory();

    // Run cron so the directory entries are indexed.
    $this->cronRun();

    // Check presence of facet checkboxes within a form.
    $directory_url = $this->channelNode->toUrl()->toString();
    $this->drupalGet($directory_url);

    $assert = $this->assertSession();
    $facets_form = $assert->elementExists('css', 'form#facets-form');
    $assert->elementExists('css', 'input#edit-localgov-directories-facets-1[type=checkbox]', $facets_form);
  }

  /**
   * Sets up a test directory.
   *
   * Creates a directory complete with a 4 directory pages assigned to various
   * LocalGov Directory facet values.
   *
   * @return array
   *   A list of node titles keyed by their node ids.
   */
  public function createAtestDirectory(): array {

    // Set up some directory entries.
    $directory_node_values = [
      // Entry 1 has facet 1 only.
      [
        'title' => 'Entry 1 ' . $this->randomMachineName(8),
        'type' => 'localgov_directories_page',
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [
          [
            'target_id' => $this->channelNode->id(),
          ],
        ],
        'localgov_directory_facets_select' => [
          [
            'target_id' => $this->facetEntities[0]->id(),
          ],
        ],
      ],
      [
        // Entry 2 has facet 2 only.
        'title' => 'Entry 2 ' . $this->randomMachineName(8),
        'type' => 'localgov_directories_page',
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [
          [
            'target_id' => $this->channelNode->id(),
          ],
        ],
        'localgov_directory_facets_select' => [
          [
            'target_id' => $this->facetEntities[1]->id(),
          ],
        ],
      ],
      // Entry 3 has facet 1 and 3.
      [
        'title' => 'Entry 3 ' . $this->randomMachineName(8),
        'type' => 'localgov_directories_page',
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [
          [
            'target_id' => $this->channelNode->id(),
          ],
        ],
        'localgov_directory_facets_select' => [
          [
            'target_id' => $this->facetEntities[0]->id(),
          ],
          [
            'target_id' => $this->facetEntities[2]->id(),
          ],
        ],
      ],
      // Entry 4 has all facets.
      [
        'title' => 'Entry 4 ' . $this->randomMachineName(8),
        'type' => 'localgov_directories_page',
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_channels' => [
          [
            'target_id' => $this->channelNode->id(),
          ],
        ],
        'localgov_directory_facets_select' => [
          [
            'target_id' => $this->facetEntities[0]->id(),
          ],
          [
            'target_id' => $this->facetEntities[1]->id(),
          ],
          [
            'target_id' => $this->facetEntities[2]->id(),
          ],
          [
            'target_id' => $this->facetEntities[3]->id(),
          ],
        ],
      ],
    ];

    foreach ($directory_node_values as $key => $node_values) {
      $new_node = $this->createNode($node_values);
      $directory_node_values[$key]['nid'] = $new_node->id();
    }

    // Get titles for comparison.
    $node_titles = array_column($directory_node_values, 'title', 'nid');

    return $node_titles;
  }

}
