<?php

namespace Drupal\localgov_directories_or\Plugin\Field\FieldFormatter;

use Drupal\rest_views\Plugin\Field\FieldFormatter\EntityReferenceExportFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\rest_views\RenderableData;
use Drupal\rest_views\SerializedData;

/**
 * Creates a serializable rendered entity nested in an OR reference.
 *
 * Only usable with the Serializable Field views plugin.
 *
 * @FieldFormatter(
 *   id = "localgov_or_enity_reference",
 *   label = @Translation("Export: Open Referral referenced rendered entity"),
 *   description = @Translation("Export the entity rendered inside a Open Referal container."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class NestedEntityReferenceExport extends EntityReferenceExportFormatter {

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $elements = $this->viewElements($items, $langcode);
    $output = ['#items' => $items];

    $entity_key = '#' . $this->getFieldSetting('target_type');
    $extra = $this->getSetting('extra');

    foreach ($elements as $delta => $row) {
      $output[$delta] = [];

      // Entities build their fields in a pre-render function.
      if (isset($row['#pre_render'])) {
        foreach ((array) $row['#pre_render'] as $callable) {
          $row = $callable($row);
        }
      }

      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $row[$entity_key];

      if (!empty($extra['id'])) {
        $output[$delta]['id'] = $entity->id();
      }
      if (!empty($extra['uuid'])) {
        $output[$delta]['uuid'] = $entity->uuid();
      }
      if (!empty($extra['title'])) {
        $output[$delta]['title'] = $entity->label();
      }
      if (!empty($extra['url'])) {
        try {
          $output[$delta]['url'] = $entity->toUrl()->setAbsolute()->toString();
        }
        catch (\Exception $exception) {
          $output[$delta]['url'] = NULL;
        }
      }
      if (!empty($extra['type'])) {
        $output[$delta]['type'] = $entity->getEntityTypeId();
      }
      if (!empty($extra['bundle'])) {
        $output[$delta]['bundle'] = $entity->bundle();
      }

      // Traverse the fields and build a serializable array.
      foreach (Element::children($row) as $name) {
        $alias = preg_replace('/^field_/', '', $name);
        if (!empty($output[$delta][$alias])) {
          continue;
        }

        $field = $row[$name];
        foreach (Element::children($field) as $index) {
          $value = $field[$index];
          if (isset($value['#type']) && $value['#type'] === 'data') {
            $value = SerializedData::create($value['#data']);
          }
          else {
            $value = RenderableData::create($value);
          }
          $output[$delta][$alias][$index] = $value;
        }

        // If the field has no multiple cardinality, unpack the array.
        if (!empty($field['#items'])) {
          /** @var \Drupal\Core\Field\FieldItemListInterface $field_items */
          $field_items = $field['#items'];
          if (!$field_items
            ->getFieldDefinition()
            ->getFieldStorageDefinition()
            ->isMultiple()
          ) {
            $output[$delta][$alias] = reset($output[$delta][$alias]);
          }
        }
      }
      
      try {
        $parent_uuid = $items[$delta]->getParent()->getParent()->getEntity()->uuid();
      }
      catch (Exception $e) {
        $parent_uuid = 'unknown';
      }

      $output[$delta] = [
        'id' => $parent_uuid . ':' . $entity->uuid(),
        $this->getSetting('relation_name') => $output[$delta],
      ];

      $output[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create($output[$delta]),
      ];
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'extra' => [],
      'relation_name' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['extra'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Export metadata'),
      '#default_value' => $this->getSetting('extra'),
      '#options'       => [
        'id'     => $this->t('ID'),
        'uuid'   => $this->t('UUID'),
        'title'  => $this->t('Title'),
        'url'    => $this->t('URL'),
        'type'   => $this->t('Entity type'),
        'bundle' => $this->t('Entity bundle'),
      ],
    ];
    $elements['relation_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Relation fieldname'),
      '#default_value' => $this->getSetting('relation_name'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $fields = $this->getSetting('extra');
    if ($fields) {
      $summary[] = $this->t('Includes %data', ['%data' => implode(', ', $fields)]);
    }
    return $summary;
  }

}
