<?php

namespace Drupal\token_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'token_formatters_string' formatter.
 *
 * @FieldFormatter(
 *   id = "token_formatters_string",
 *   label = @Translation("Tokenized string"),
 *   field_types = {
 *     "string_long",
 *     "string",
 *   }
 * )
 */
class StringFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a StringFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'tokenized_text' => '',
      'link_to_entity' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($pattern = $this->getSetting('tokenized_text')) {
      $summary[] = $pattern;
    }
    if ($this->getSetting('link_to_entity')) {
      $entity_type = $this->entityTypeManager->getDefinition($this->fieldDefinition->getTargetEntityTypeId());
      $summary[] = $this->t('Linked to the @entity_label', ['@entity_label' => $entity_type->getLabel()]);
    }
    return $summary;
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

    $entity_type = $this->entityTypeManager->getDefinition($this->fieldDefinition->getTargetEntityTypeId());

    $element['link_to_entity'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to the @entity_label', ['@entity_label' => $entity_type->getLabel()]),
      '#default_value' => $this->getSetting('link_to_entity'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $url = NULL;
    if ($this->getSetting('link_to_entity')) {
      $url = $this->getEntityUrl($items->getEntity());
    }

    foreach ($items as $delta => $item) {
      $view_value = $this->viewValue($item);
      if ($url) {
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $view_value,
          '#url' => $url,
        ];
      }
      else {
        $elements[$delta] = $view_value;
      }
    }
    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return array
   *   The textual output generated as a render array.
   */
  protected function viewValue(FieldItemInterface $item) {
    $entity = $item->getEntity();
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    if ($tokenized_text = $this->getSetting('tokenized_text')) {
      $tokenized_value = \Drupal::token()->replace($tokenized_text, [$entity->getEntityType()->id() => $entity]);
    }
    else {
      $field_name = $this->fieldDefinition->getFieldStorageDefinition()->getName();
      $tokenized_value = \Drupal::token()->replace('[' . $entity_type . ':' . $field_name . ']', [$entity_type => $entity]);
    }
    return [
      '#type' => 'inline_template',
      '#template' => '{{ value|nl2br }}',
      '#context' => ['value' => $tokenized_value],
    ];
  }

  /**
   * Gets the URI elements of the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\Core\Url
   *   The URI elements of the entity.
   */
  protected function getEntityUrl(EntityInterface $entity) {
    // For the default revision, the 'revision' link template falls back to
    // 'canonical'.
    // @see \Drupal\Core\Entity\Entity::toUrl()
    $rel = $entity->getEntityType()->hasLinkTemplate('revision') ? 'revision' : 'canonical';
    return $entity->toUrl($rel);
  }

}
