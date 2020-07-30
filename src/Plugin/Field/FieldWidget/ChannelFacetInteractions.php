<?php

namespace Drupal\localgov_directories\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interactions and calculations based on enabled channels and facets.
 */
class ChannelFacetInteractions {

  /**
   * AJAX callback to rebuild form fields dependent on selected channels.
   *
   * Presently hard codes the one field - by name.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Replacements for form.
   */
  public static function updateFields(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();

    $renderer = \Drupal::service('renderer');
    $facets_field = $renderer->render($form['localgov_directory_facets_select']);

    $response = new AjaxResponse();
    #$response->addCommand(new ReplaceCommand('#localgov-directory-channel-facet-options', $facets_field));
    $response->addCommand(new ReplaceCommand('[data-drupal-selector=edit-localgov-directory-facets-select-wrapper', $facets_field));

    return $response;
  }

}
