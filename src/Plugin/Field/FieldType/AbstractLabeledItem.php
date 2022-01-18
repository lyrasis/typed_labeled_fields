<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Base class for Labeled field types.
 */
abstract class AbstractLabeledItem extends FieldItemBase {

  const PROPERTY_LABEL = 'label';
  
  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    $properties[self::PROPERTY_LABEL] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Label'))
      // Confusingly 'case_sensitive' is for Entity Field SQL queries.
      ->setRequired(FALSE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      self::PROPERTY_LABEL => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 255,
      ],
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function storageSettingsToConfigData(array $settings) {
    $settings = parent::storageSettingsToConfigData($settings);
    $settings[self::PROPERTY_LABEL]['max_length'] = (int) $settings[self::PROPERTY_LABEL]['max_length'];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function storageSettingsFromConfigData(array $settings) {
    $settings = parent::storageSettingsFromConfigData($settings);
    $settings[self::PROPERTY_LABEL]['max_length'] = (int) $settings[self::PROPERTY_LABEL]['max_length'];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    return [
      'columns' => [
        self::PROPERTY_LABEL => [
          'type' => $settings[self::PROPERTY_LABEL]['is_ascii'] === TRUE ? 'varchar_ascii' : 'varchar',
          'length' => (int) $settings[self::PROPERTY_LABEL]['max_length'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    $max_length = $this->getSetting(self::PROPERTY_LABEL)['max_length'];

    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('ComplexData', [
      self::PROPERTY_LABEL => [
        'Length' => [
          'max' => $max_length,
          'maxMessage' => $this->t('The label for %name: may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
            '@max' => $max_length,
          ]),
        ],
      ],
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values[self::PROPERTY_LABEL] = $random->word(mt_rand(1, $field_definition->getSetting(self::PROPERTY_LABEL)['max_length']));
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];

    $element[self::PROPERTY_LABEL] = [
      'max_length' => [
        '#type' => 'number',
        '#title' => $this->t('Label Maximum length'),
        '#default_value' => (int) $this->getSetting(self::PROPERTY_LABEL)['max_length'],
        '#required' => TRUE,
        '#description' => $this->t('The maximum length of the "Label" in characters.'),
        '#min' => 1,
        '#disabled' => $has_data,
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // Only the 'value' field is required to be considered not empty.
    $value_property = static::mainPropertyName();
    if ($this->{$value_property} !== NULL && !empty($this->{$value_property})) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'linked_data' => NULL,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);
    $element['linked_data'] = [
      '#type' => 'details',
      '#title' => $this->t('Linked Data'),
      '#open' => TRUE,
      '#tree' => TRUE,
      'default' => [
        '#type' => 'url',
        '#title' => $this->t('Default predicate'),
        '#description' => $this->t('URI to be used as linked data predicate when field instance is populated but type property is null.'),
      ],
    ];
    return $element;
  }

}
