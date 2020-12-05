<?php

namespace Drupal\token_formatters\Plugin\Field\FieldFormatter;

use Drupal\text\Plugin\Field\FieldFormatter\TextTrimmedFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'token_formatters_text_summary' formatter.
 *
 * @FieldFormatter(
 *   id = "token_formatters_text_summary",
 *   label = @Translation("Tokenized text (summary)"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 */
class TextSummaryFormatter extends TextTrimmedFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'tokenized_text' => '',
      'trim_length' => '600',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    
    $element['trim_length'] = [
      '#title' => t('Trimmed limit'),
      '#type' => 'number',
      '#field_suffix' => t('characters'),
      '#default_value' => $this->getSetting('trim_length'),
      '#description' => t('If the summary is not set, the trimmed %label field will end at the last full sentence before this character limit.', ['%label' => $this->fieldDefinition->getLabel()]),
      '#min' => 1,
      '#required' => TRUE,
    ];

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
    $summary[] = t('Trimmed limit: @trim_length characters', ['@trim_length' => $this->getSetting('trim_length')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $render_as_summary = function (&$element) {
      // Make sure any default #pre_render callbacks are set on the element,
      // because text_pre_render_summary() must run last.
      $element += \Drupal::service('element_info')->getInfo($element['#type']);
      // Add the #pre_render callback that renders the text into a summary.
      $element['#pre_render'][] = [TextTrimmedFormatter::class, 'preRenderSummary'];
      // Pass on the trim length to the #pre_render callback via a property.
      $element['#text_summary_trim_length'] = $this->getSetting('trim_length');
    };

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => NULL,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];

      $entity = $item->getEntity();

      if ($this->getPluginId() == 'text_summary_or_trimmed' && !empty($item->summary)) {
        $elements[$delta]['#text'] = $item->summary;
        if ($tokenized_text = $this->getSetting('tokenized_text')) {
          $elements[$delta]['#text'] = \Drupal::token()->replace($tokenized_text, [$entity->getEntityType()->id() => $entity]);
        }
      }
      else {
        $elements[$delta]['#text'] = $item->value;
        if ($tokenized_text = $this->getSetting('tokenized_text')) {
          $elements[$delta]['#text'] = \Drupal::token()->replace($tokenized_text, [$entity->getEntityType()->id() => $entity]);
        }
        $render_as_summary($elements[$delta]);
      }
    }

    return $elements;
  }

  /**
   * Pre-render callback: Renders a processed text element's #markup as a summary.
   *
   * @param array $element
   *   A structured array with the following key-value pairs:
   *   - #markup: the filtered text (as filtered by filter_pre_render_text())
   *   - #format: containing the machine name of the filter format to be used to
   *     filter the text. Defaults to the fallback format. See
   *     filter_fallback_format().
   *   - #text_summary_trim_length: the desired character length of the summary
   *     (used by text_summary())
   *
   * @return array
   *   The passed-in element with the filtered text in '#markup' trimmed.
   *
   * @see filter_pre_render_text()
   * @see text_summary()
   */
  public static function preRenderSummary(array $element) {
    $element['#markup'] = text_summary($element['#markup'], $element['#format'], $element['#text_summary_trim_length']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderSummary'];
  }

}
