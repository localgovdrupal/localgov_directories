<?php

namespace Drupal\Tests\localgov_directories_page\Kernel;

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
    'system',
    'user',
    'localgov_roles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['user', 'localgov_roles']);
  }

  /**
   * Check default roles applied.
   */
  public function testEnablingRolesModule() {
    $this->container->get('module_installer')->install(['localgov_directories_page']);

    $editor = Role::load(RolesHelper::EDITOR_ROLE);
    $author = Role::load(RolesHelper::AUTHOR_ROLE);
    $permissions = [
      'create localgov_directories_page content' =>
        ['editor' => TRUE, 'author' => TRUE],
      'delete any localgov_directories_page content' =>
        ['editor' => TRUE, 'author' => FALSE],
      'delete own localgov_directories_page content' =>
        ['editor' => TRUE, 'author' => TRUE],
      'edit any localgov_directories_page content' =>
        ['editor' => TRUE, 'author' => FALSE],
      'edit own localgov_directories_page content' =>
        ['editor' => TRUE, 'author' => TRUE],
      'revert localgov_directories_page revisions' =>
        ['editor' => TRUE, 'author' => FALSE],
      'view localgov_directories_page revisions' =>
        ['editor' => TRUE, 'author' => FALSE],
    ];

    foreach ($permissions as $permission => $grant) {
      $this->assertEquals($author->hasPermission($permission), $grant['author']);
      $this->assertEquals($editor->hasPermission($permission), $grant['editor']);
    }
  }

}
