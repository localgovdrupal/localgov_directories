<?php

namespace Drupal\localgov_directories\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * LocalGov: Directories event subscriber.
 */
class DirectoriesConfigSubscriber implements EventSubscriberInterface {

  /**
   * The sync storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $sync;

  /**
   * DirectoriesConfigSubscriber constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $sync
   *   The sync storage.
   */
  public function __construct(StorageInterface $sync) {
    $this->sync = $sync;
  }

  /**
   * The storage is transformed for importing.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onImportTransform(StorageTransformEvent $event) {
  }

  /**
   * The storage is transformed for exporting.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onExportTransform(StorageTransformEvent $event) {
    $sync = $this->sync->read('localgov_directories.localgov_directories_facets_type');
    // Only change something if the sync storage has data.
    if (!empty($sync)) {
      $storage = $event->getStorage();
      $storage->delete('localgov_directories.localgov_directories_facets_type');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = ['onImportTransform'];
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = ['onExportTransform'];
    return $events;
  }

}
