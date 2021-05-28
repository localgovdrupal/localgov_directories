<?php

namespace Drupal\Tests\localgov_directories_page\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;

/**
 * Tests pages working together with pathauto, services and topics.
 *
 * @group localgov_guides
 */
class PathIntegrationTest extends BrowserTestBase {

  use NodeCreationTrait;
  use AssertBreadcrumbTrait;

  /**
   * Test breadcrumbs in the Standard profile.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * A user with permission to bypass content access checks.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'localgov_directories',
    'localgov_directories_page',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'bypass node access',
      'administer nodes',
    ]);
    $this->nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');
  }

  /**
   * Post page into a channel.
   */
  public function testDirectoryIntegration() {
    $directory = $this->createNode([
      'title' => 'Directory 1',
      'type' => 'localgov_directory',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $directory->save();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/add/localgov_directories_page');
    $form = $this->getSession()->getPage();
    $form->fillField('edit-title-0-value', 'Page 1');
    $form->fillField('edit-body-0-summary', 'Page 1 summary');
    $form->fillField('edit-body-0-value', 'Page 1 description');
    $form->selectFieldOption('edit-localgov-directory-channels-primary-' . $directory->id(), $directory->id());
    $form->checkField('edit-status-value');
    $form->pressButton('edit-submit');

    $this->assertSession()->pageTextContains('Page 1');
    $trail = ['' => 'Home'];
    $trail += ['directory-1' => 'Directory 1'];
    $this->assertBreadcrumb(NULL, $trail);
  }

}
