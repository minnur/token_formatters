<?php

namespace Drupal\token_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'token_formatters_reference' formatter.
 *
 * @FieldFormatter(
 *   id = "token_formatters_reference",
 *   label = @Translation("Tokenized label"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceFormatter extends EntityReferenceLabelFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'tokenized_text' => '',
      'link' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    $settings = $this->fieldDefinition->getSettings();
    $token_types = [];
    $token_types[] = $entity_type;
    $target_entity_type = $settings['target_type'];
    if ($target_entity_type == 'taxonomy_term') {
      $token_types[] = 'term';
    }
    else {
      $token_types[] = $target_entity_type;
    }

    $element['tokenized_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern'),
      '#default_value' => $this->getSetting('tokenized_text'),
      '#size' => 65,
      '#maxlength' => 1280,
      '#token_types' => $token_types,
      '#min_tokens' => 1,
    ];

    $element['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => $token_types,
    ];

    $element['link'] = [
      '#title' => t('Link label to the referenced entity'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('link'),
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
    $summary[] = $this->getSetting('link') ? t('Link to the referenced entity') : t('No link');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $output_as_link = $this->getSetting('link');

    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    $settings = $this->fieldDefinition->getSettings();
    $target_entity_type = $settings['target_type'];
    if ($target_entity_type == 'term') {
      $target_entity_type == 'term';
    }

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      if ($tokenized_text = $this->getSetting('tokenized_text')) {
        if ($target_entity_type != $entity_type) {
          $label = \Drupal::token()->replace($tokenized_text, [
            $entity_type => $items->getEntity(),
            $entity->getEntityType()->id() => $entity
          ]);
        }
        else {
          $label = \Drupal::token()->replace($tokenized_text, [$entity->getEntityType()->id() => $entity]);
        }
      }
      else {
        $label = $entity->label();
      }

      // If the link is to be displayed and the entity has a uri, display a
      // link.
      if ($output_as_link && !$entity->isNew()) {
        try {
          $uri = $entity->toUrl();
        }
        catch (UndefinedLinkTemplateException $e) {
          // This exception is thrown by \Drupal\Core\Entity\Entity::urlInfo()
          // and it means that the entity type doesn't have a link template nor
          // a valid "uri_callback", so don't bother trying to output a link for
          // the rest of the referenced entities.
          $output_as_link = FALSE;
        }
      }

      if ($output_as_link && isset($uri) && !$entity->isNew()) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $uri,
          '#options' => $uri->getOptions(),
        ];

        if (!empty($items[$delta]->_attributes)) {
          $elements[$delta]['#options'] += ['attributes' => []];
          $elements[$delta]['#options']['attributes'] += $items[$delta]->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and shouldn't be rendered in the field template.
          unset($items[$delta]->_attributes);
        }
      }
      else {
        $elements[$delta] = ['#plain_text' => $label];
      }
      $elements[$delta]['#cache']['tags'] = $entity->getCacheTags();
    }

    return $elements;
  }

}
