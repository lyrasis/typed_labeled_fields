<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a 'typed' and 'labeled' text field.
 *
 * @FieldType(
 *   id = "typed_labeled_text_short",
 *   label = @Translation("Typed Labeled Text (plain)"),
 *   description = @Translation("An entity field containing an entity reference along with a short plain text value."),
 *   category = @Translation("Labeled"),
 *   default_formatter = "typed_labeled_default_formatter",
 *   default_widget = "typed_labeled_text_short_widget",
 *   list_class = "\Drupal\typed_labeled_fields\Field\MultipleEntityReferenceFieldItemList",
 * )
 */
class TypedLabeledTextShort extends AbstractTypedLabeledItem {

  const PROPERTY_VALUE = 'value';
  const PROPERTY_VALUE_NAME = 'normalized_type_name';

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return self::PROPERTY_VALUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      self::PROPERTY_VALUE => [
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
    $settings[self::PROPERTY_VALUE]['max_length'] = (int) $settings[self::PROPERTY_LABEL]['max_length'];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function storageSettingsFromConfigData(array $settings) {
    $settings = parent::storageSettingsFromConfigData($settings);
    $settings[self::PROPERTY_VALUE]['max_length'] = (int) $settings[self::PROPERTY_LABEL]['max_length'];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    $schema = parent::schema($field_definition);
    $schema['columns'][self::PROPERTY_VALUE] = [
      'type' => 'varchar_ascii',
      'length' => (int) $settings[self::PROPERTY_VALUE]['max_length'],
      'not null' => TRUE,
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    $properties = parent::propertyDefinitions($field_definition);
    $properties[self::PROPERTY_VALUE] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Value'))
      ->setSetting('case_sensitive', TRUE)
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    $max_length = $this->getSetting(self::PROPERTY_VALUE)['max_length'];

    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('ComplexData', [
      self::PROPERTY_VALUE => [
        'Length' => [
          'max' => $max_length,
          'maxMessage' => $this->t('%name: may not be longer than @max characters.', [
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
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);

    $element[self::PROPERTY_VALUE] = [
      'max_length' => [
        '#type' => 'number',
        '#title' => $this->t('Value Maximum length'),
        '#default_value' => (int) $this->getSetting(self::PROPERTY_VALUE)['max_length'],
        '#required' => TRUE,
        '#description' => $this->t('The maximum length of the "Value" in characters.'),
        '#min' => 1,
        '#disabled' => $has_data,
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);
    $random = new Random();
    $values[self::PROPERTY_VALUE] = $random->word(mt_rand(1, $field_definition->getSetting(self::PROPERTY_VALUE)['max_length']));
    return $values;
  }

}
