<?php

namespace Drupal\localgov_directories\Plugin\search_api\processor;

use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Allows you to define stopwords which will be ignored in strings used for sorting.
 *
 * @SearchApiProcessor(
 *   id = "localgov_string_stopwords",
 *   label = @Translation("Stopwords in strings"),
 *   description = @Translation("Allows you to define stopwords which will be ignored in a string field."),
 *   stages = {
 *     "pre_index_save" = 0,
 *     "preprocess_index" = -5,
 *     "preprocess_query" = -2,
 *   }
 * )
 */
class Stopwords extends FieldsProcessorPluginBase {

  /**
   * An array whose keys and values are the stopwords set for this processor.
   *
   * @var string[]
   */
  protected $stopwords;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();

    $configuration += [
      'stopwords' => [
        'a', 'an', 'and', 'for', 'if', 'in', 'into', 'of', 'on', 'or', 's',
        't', 'that', 'the', 'this', 'to', 'was',
      ],
    ];

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    unset($this->stopwords);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $stopwords = $this->getConfiguration()['stopwords'];
    if (is_array($stopwords)) {
      $default_value = implode("\n", $stopwords);
    }
    else {
      $default_value = $stopwords;
    }
    $description = $this->t('Enter a list of stopwords, each on a separate line, that will be removed from content before it is indexed. <a href=":url">More info about stopwords.</a>.', [':url' => 'https://en.wikipedia.org/wiki/Stop_words']);

    $form['stopwords'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Stopwords'),
      '#description' => $description,
      '#default_value' => $default_value,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Convert our text input to an array.
    $form_state->setValue('stopwords', array_filter(array_map('trim', explode("\n", $form_state->getValues()['stopwords'])), 'strlen'));

    parent::submitConfigurationForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    return $this->getDataTypeHelper()->isTextType($type, ['string']);
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    $stopwords = $this->getStopWords();
    if (empty($stopwords) || !is_string($value)) {
      return;
    }
    $value = preg_replace($stopwords, '', $value);
    return trim($value);
  }

  /**
   * Gets the stopwords for this processor.
   *
   * @return string[]
   *   An array whose keys and values are the stopwords set for this processor.
   */
  protected function getStopWords() {
    if (!isset($this->stopwords)) {
      $stopwords = $this->configuration['stopwords'];
      array_walk($stopwords, function (&$word) {
        $word = '/(\s|^)' . $word . '\s/i';
      });
      $this->stopwords = array_combine($this->configuration['stopwords'], $stopwords);
    }
    return $this->stopwords;
  }

}
