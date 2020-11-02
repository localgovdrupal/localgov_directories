<?php

namespace Drupal\localgov_directories\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the directory facets entity edit forms.
 */
class LocalgovDirectoriesFacetsForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * Adds the "weight" field to the mix.
   */
  public function form(array $form, FormStateInterface $form_state) {

    $facet_item = $this->entity;

    $form['weight'] = [
      '#type'          => 'weight',
      '#title'         => $this->t('Weight'),
      '#description'   => $this->t('Facets are displayed in ascending order by weight.'),
      '#default_value' => $facet_item->getWeight() ?? 0,
      '#delta'         => 50,
      '#weight'        => 100,
    ];

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New directory facets %label has been created.', $message_arguments));
      $this->logger('localgov_directories')->notice('Created new directory facets %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The directory facets %label has been updated.', $message_arguments));
      $this->logger('localgov_directories')->notice('Updated new directory facets %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.localgov_directories_facets.collection');
  }

}
