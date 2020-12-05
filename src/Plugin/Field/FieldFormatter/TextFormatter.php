<?php

namespace Drupal\token_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'token_formatters_text' formatter.
 *
 * @FieldFormatter(
 *   id = "token_formatters_text",
 *   label = @Translation("Tokenized text"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 */
class TextFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'tokenized_text' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();

    $element['tokenized_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern'),
      '#default_value' => $this->getSetting('tokenized_text'),
      '#size' => 65,
      '#maxlength' => 1280,
      '#token_types' => [$entity_type],
      '#min_tokens' => 1,
    ];

    $element['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => [$entity_type],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($pattern = $this->getSetting('tokenized_text')) {
      $summary[] = $pattern;
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();
    foreach ($items as $delta => $item) {
      $value = $item->value;
      if ($tokenized_text = $this->getSetting('tokenized_text')) {
        $value = \Drupal::token()->replace($tokenized_text, [$entity->getEntityType()->id() => $entity]);
      }
      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => $value,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];
    }

    return $elements;
  }

}
