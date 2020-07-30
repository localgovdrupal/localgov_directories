<?php

namespace Drupal\localgov_directories\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\localgov_directories\Entity\LocalgovDirectoriesFacetsType;
use Drupal\node\Entity\Node;

/**
 * Display available facet options by selected channel.
 *
 * Grouping by entity reference by bundle would also be solved by
 * https://www.drupal.org/project/drupal/issues/2269823
 *
 * @FieldWidget(
 *   id = "localgov_directories_facet_checkbox",
 *   module = "localgov_directories",
 *   label = @Translation("Directory entry facets"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class FacetFieldWidget extends OptionsWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $options = $this->getOptions($items->getEntity());
    $selected = $this->getSelectedOptions($items);

    $enabled = [];
    if ($user_input = $form_state->getValue('localgov_directory_channels')) {
      foreach ($user_input as $user_input_nid) {
        if ($user_input_nid['target_id'] && ($channel = Node::load($user_input_nid['target_id']))) {
          foreach ($channel->localgov_directory_facets_enable as $facet_item) {
            $facet = $facet_item->entity;
            assert($facet instanceof LocalgovDirectoriesFacetsType);
            $enabled[$facet->label()] = $facet->label();
          }
        }
      }
    }
    else {
      foreach ($items->getEntity()->localgov_directory_channels as $channel) {
        foreach ($channel->entity->localgov_directory_facets_enable as $facet_item) {
          $facet = $facet_item->entity;
          assert($facet instanceof LocalgovDirectoriesFacetsType);
          $enabled[$facet->label()] = $facet->label();
        }
      }
    }
    // And only allow these.
    $options = array_intersect_key($options, $enabled);

    if ($this->required && count($options) == 1) {
      reset($options);
      $selected = [key($options)];
    }

    $element += [
      '#type' => 'fieldset',
    ];

    if (empty($options)) {
      $element['#description'] = $this->t('Select directory channels to add facets');
    }
    foreach ($options as $bundle_label => $bundle_options) {
      $element[$bundle_label] = [
        '#title' => $bundle_label,
        '#type' => 'checkboxes',
        '#default_value' => $selected,
        '#options' => $bundle_options,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsGroups() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    // Flatten the array again.
    $values = $form_state->getValue($element['#field_name']);
    if ($values) {
      $element['#value'] = [];
      foreach ($values as $options) {
        foreach ($options as $key => $value) {
          $element['#value'][$key] = $value;
        }
      }
    }

    parent::validateElement($element, $form_state);
  }

}
