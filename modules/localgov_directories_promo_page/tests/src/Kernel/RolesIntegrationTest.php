<?php

namespace Drupal\Tests\localgov_directories_promo_page\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\localgov_roles\RolesHelper;
use Drupal\user\Entity\Role;

/**
 * Tests default roles.
 *
 * @group localgov_directories
 */
class RolesIntegrationTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'address',
    'block',
    'entity_reference_revisions',
    'facets',
    'field',
    'field_formatter_class',
    'field_group',
    'file',
    'filter',
    'image',
    'layout_discovery',
    'layout_paragraphs',
    'link',
    'media',
    'media_library',
    'menu_ui',
    'node',
    'options',
    'paragraphs',
    'path',
    'path_alias',
    'pathauto',
    'role_delegation',
    'search_api',
    'search_api_db',
    'system',
    'telephone',
    'text',
    'token',
    'toolbar',
    'user',
    'views',
    'localgov_roles',
    'localgov_directories',
    'localgov_directories_promo_page',
    'localgov_paragraphs',
    'localgov_paragraphs_layout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installSchema('node', ['node_access']);
    $this->installConfig([
      'node',
      'search_api',
      'localgov_roles',
      'localgov_paragraphs_layout',
      'localgov_directories',
      'localgov_directories_promo_page',
    ]);
  }

  /**
   * Check default roles applied.
   */
  public function testEnablingRolesModule() {
    RolesHelper::assignModuleRoles('localgov_directories_promo_page');

    $editor = Role::load(RolesHelper::EDITOR_ROLE);
    $author = Role::load(RolesHelper::AUTHOR_ROLE);
    $contributor = Role::load(RolesHelper::CONTRIBUTOR_ROLE);
    $permissions = [
      'create localgov_directory_promo_page content' =>
        ['editor' => TRUE, 'author' => TRUE, 'contributor' => TRUE],
      'delete any localgov_directory_promo_page content' =>
        ['editor' => TRUE, 'author' => FALSE, 'contributor' => FALSE],
      'delete own localgov_directory_promo_page content' =>
        ['editor' => TRUE, 'author' => TRUE, 'contributor' => TRUE],
      'edit any localgov_directory_promo_page content' =>
        ['editor' => TRUE, 'author' => FALSE, 'contributor' => FALSE],
      'edit own localgov_directory_promo_page content' =>
        ['editor' => TRUE, 'author' => TRUE, 'contributor' => TRUE],
      'revert localgov_directory_promo_page revisions' =>
        ['editor' => TRUE, 'author' => TRUE, 'contributor' => FALSE],
      'view localgov_directory_promo_page revisions' =>
        ['editor' => TRUE, 'author' => TRUE, 'contributor' => TRUE],
    ];

    foreach ($permissions as $permission => $grant) {
      $this->assertEquals($author->hasPermission($permission), $grant['author']);
      $this->assertEquals($contributor->hasPermission($permission), $grant['contributor']);
      $this->assertEquals($editor->hasPermission($permission), $grant['editor']);
    }
  }

}
