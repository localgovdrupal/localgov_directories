<?php

namespace Drupal\Tests\localgov_directories\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\NodeInterface;

/**
 * Tests the configuration of channel widget.
 *
 * @group localgov_directories
 */
class ChannelFacetsWidgetAdminTest extends BrowserTestBase {

  use FieldUiTestTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * A user with minimum permissions for test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Directory nodes.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $directories = [];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'localgov_directories',
    'field_ui',
    'block',
  ];


  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    for ($j = 1; $j < 3; $j++) {
      $directory = $this->createNode([
        'title' => 'Directory ' . $j,
        'type' => 'localgov_directory',
        'status' => NodeInterface::PUBLISHED,
        'localgov_directory_facets_enable' => [],
      ]);
      $directory->save();
      $this->directories[$j] = $directory;
    }

    // Content type configured to reference directories, and have the
    // facet selector.
    $this->createContentType(['type' => 'entry_1']);
    $this->createContentType(['type' => 'entry_2']);

    // Configure directory channels to allow different entry types.
    $this->directories[1]->localgov_directory_channel_types = [
      'target_id' => 'entry_1',
    ];
    $this->directories[1]->save();
    $this->directories[2]->localgov_directory_channel_types = [
      ['target_id' => 'entry_1'],
      ['target_id' => 'entry_2'],
    ];
    $this->directories[2]->save();

    // Create a test user.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Test selecting channels and facets appearing.
   */
  public function testDirectoryChannelWidget() {
    // Create the fields with the selector.
    $this->fieldUIAddNewField(
      'admin/structure/types/manage/entry_1',
      'channels',
      'Channels',
      'field_ui:entity_reference:node',
      [],
      [
        'settings[handler]' => 'localgov_directories_channels_selection',
        // No javascript update; and fieldUIAddNewField is too fast for it with.
        // The field is in fact removed and not required (even if it is fill
        // out. See LocalgovDirectoriesChannelsSelection::buildConfigurationForm
        // and validateConfigurationForm.
        'settings[handler_settings][target_bundles][localgov_directory]' => TRUE,
      ]
    );
    $this->fieldUIAddExistingField(
      'admin/structure/types/manage/entry_2',
      'field_channels',
      'Channels',
      [
        'settings[handler]' => 'localgov_directories_channels_selection',
        // This loads correctly first time without javascript flash of required
        // bundles.
      ]
    );
    // Set the widget.
    $this->drupalGet('/admin/structure/types/manage/entry_1/form-display');
    $this->submitForm(['fields[field_channels][type]' => 'localgov_directories_channel_selector'], 'edit-submit');
    $this->drupalGet('/admin/structure/types/manage/entry_2/form-display');
    $this->submitForm(['fields[field_channels][type]' => 'localgov_directories_channel_selector'], 'edit-submit');

    // Check the correct channels are on the different entry forms.
    $this->drupalGet('/node/add/entry_1');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Directory 1');
    $assert_session->pageTextContains('Directory 2');
    $this->drupalGet('/node/add/entry_2');
    $assert_session->pageTextNotContains('Directory 1');
    $assert_session->pageTextContains('Directory 2');

    // Set a default.
    $this->drupalGet('/admin/structure/types/manage/entry_1/fields/node.entry_1.field_channels');
    $this->submitForm(
      [
        'set_default_value' => TRUE,
        'default_value_input[field_channels][primary]' => $this->directories[2]->id(),
      ],
      'edit-submit'
    );
    $this->drupalGet('/admin/structure/types/manage/entry_2/fields/node.entry_2.field_channels');
    $this->submitForm(
      [
        'set_default_value' => TRUE,
        'default_value_input[field_channels][primary]' => $this->directories[2]->id(),
      ],
      'edit-submit'
    );

    // Check default applied.
    $this->drupalGet('/node/add/entry_1');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $radio = $page->findField('field_channels[primary]');
    $this->assertEquals($radio->getValue(), $this->directories[2]->id());
    $this->drupalGet('/node/add/entry_2');
    $radio = $page->findField('field_channels[primary]');
    $this->assertEquals($radio->getValue(), $this->directories[2]->id());
    $assert_session->fieldNotExists('edit-field-channels-secondary-1');
  }

}
