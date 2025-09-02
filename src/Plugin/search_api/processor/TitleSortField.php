<?php

namespace Drupal\localgov_directories\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Populates directories sort order field if empty.
 *
 * @SearchApiProcessor(
 *   id = "localgov_directories_sort_field",
 *   label = @Translation("LocalGov Directories sort field"),
 *   description = @Translation("Populates default title value into optional sort field if it is empty."),
 *   stages = {
 *     "preprocess_index" = 0,
 *   }
 * )
 */
class TitleSortField extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    foreach ($items as $item) {
      if ($field = $item->getField('localgov_directory_title_sort')) {
        $sort_value = $field->getValues();
        if (empty($sort_value) || empty($sort_value[0])) {
          // If the field is empty use the item title.
          $sort_value = [trim($item->getOriginalObject()->getEntity()->label())];
          $field->setValues($sort_value);
        }
      }
    }
  }

}
