<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a 'typed' and 'labeled' origin statement field.
 *
 * @FieldType(
 *   id = "typed_labeled_origin_statement",
 *   label = @Translation("Typed Labeled Origin Statement"),
 *   description = @Translation("An collection of properties that describe the origin of the content."),
 *   category = @Translation("Labeled"),
 *   default_formatter = "typed_labeled_default_formatter",
 *   default_widget = "typed_labeled_origin_statement_widget",
 *   list_class = "\Drupal\typed_labeled_fields\Field\MultipleEntityReferenceFieldItemList",
 * )
 */
class TypedLabeledOriginStatement extends AbstractTypedLabeledItem {
  const PROPERTY_ADDITIONAL = 'additional';
  const PROPERTY_AGENT = 'agent';
  const PROPERTY_DATE = 'date';
  const PROPERTY_PLACE = 'place';

  const PROPERTIES = [
    self::PROPERTY_LABEL,
    self::PROPERTY_TYPE_TARGET,
    self::PROPERTY_TYPE,
    self::PROPERTY_PLACE,
    self::PROPERTY_AGENT,
    self::PROPERTY_DATE,
    self::PROPERTY_ADDITIONAL,
  ];

  const INHERITED_PROPERTIES = [
    self::PROPERTY_LABEL,
    self::PROPERTY_TYPE_TARGET,
    self::PROPERTY_TYPE,
  ];

  // At least one of these properties must be defined for the field to be valid.
  const REQUIRED_PROPERTIES = [
    self::PROPERTY_PLACE,
    self::PROPERTY_AGENT,
    self::PROPERTY_DATE,
  ];

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    // At least one of 'agent', 'date', 'place', is required.
    return NULL;
  }

  /**
   * Gets the list of property names that have not been inherited.
   */
  public static function nonInheritedProperties() {
    return array_diff(self::PROPERTIES, self::INHERITED_PROPERTIES);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      self::PROPERTY_ADDITIONAL => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 1024,
      ],
      self::PROPERTY_AGENT => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 255,
      ],
      self::PROPERTY_DATE => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 255,
      ],
      self::PROPERTY_PLACE => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 512,
      ],

    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function storageSettingsToConfigData(array $settings) {
    $settings = parent::storageSettingsToConfigData($settings);
    foreach (static::nonInheritedProperties() as $property) {
      $settings[$property]['max_length'] = (int) $settings[$property]['max_length'];
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function storageSettingsFromConfigData(array $settings) {
    $settings = parent::storageSettingsFromConfigData($settings);
    foreach (static::nonInheritedProperties() as $property) {
      $settings[$property]['max_length'] = (int) $settings[$property]['max_length'];
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    $schema = parent::schema($field_definition);
    foreach (static::nonInheritedProperties() as $property) {
      $schema['columns'][$property] = [
        'type' => $settings[$property]['is_ascii'] === TRUE ? 'varchar_ascii' : 'varchar',
        'length' => (int) $settings[$property]['max_length'],
        'binary' => $settings[$property]['case_sensitive'],
        'not null' => TRUE,
      ];
    }
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_storage_definition) {
    $field_storage_settings = $field_storage_definition->getSettings();
    $properties = parent::propertyDefinitions($field_storage_definition);

    $properties[self::PROPERTY_ADDITIONAL] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Additional'))
      ->setDescription(new TranslatableMarkup('Additional information associated with the event.'));
    $properties[self::PROPERTY_AGENT] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Agent'))
      ->setDescription(new TranslatableMarkup('Name of an agent associated with the event.'));
    $properties[self::PROPERTY_DATE] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Date'))
      ->setDescription(new TranslatableMarkup('A date associated with the event.'));
    $properties[self::PROPERTY_PLACE] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Place'))
      ->setDescription(new TranslatableMarkup('Name of a place associated with the event.'));

    /** @var \Drupal\Core\TypedData\DataDefinition[] $properties */
    foreach (static::nonInheritedProperties() as $property) {
      $properties[$property]
        ->setSetting('case_sensitive', $field_storage_settings[$property]['case_sensitive'])
        ->setRequired($property == static::mainPropertyName() ? TRUE : FALSE);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    foreach (static::nonInheritedProperties() as $property) {
      $max_length = $this->getSetting($property)['max_length'];
      $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
      $constraints[] = $constraint_manager->create('ComplexData', [
        $property => [
          'Length' => [
            'max' => $max_length,
            'maxMessage' => $this->t('%name: may not be longer than @max characters.', [
              '%name' => $this->getFieldDefinition()->getLabel(),
              '@max' => $max_length,
            ]),
          ],
        ],
      ]);
    }
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = parent::storageSettingsForm($form, $form_state, $has_data);
    $property_definitions = $this->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions();
    foreach (static::nonInheritedProperties() as $property) {
      $label = $property_definitions[$property]->getLabel();
      $element[$property] = [
        'max_length' => [
          '#type' => 'number',
          '#title' => $this->t('@label Maximum length', ['@label' => $label]),
          '#default_value' => (int) $this->getSetting($property)['max_length'],
          '#required' => TRUE,
          '#description' => $this->t('The maximum length of the "@label" in characters.', ['@label' => $label]),
          '#min' => 1,
          '#disabled' => $has_data,
        ],
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);
    $field_settings = $field_definition->getSettings();

    $random = new Random();
    foreach (static::nonInheritedProperties() as $property) {
      $values[$property] = $random->word(mt_rand(1, $field_settings[$property]['max_length']));
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // If any required property is present the field is not empty.
    foreach (self::REQUIRED_PROPERTIES as $property) {
      if ($this->{$property} !== NULL && !empty($this->{$property})) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    $string = trim($this->{self::PROPERTY_PLACE});
    $agent = trim($this->{self::PROPERTY_AGENT});
    $has_agent = !empty($agent);
    if (!empty($string) && $has_agent) {
      $string .= ": $agent";
    }
    elseif ($has_agent) {
      $string = $agent;
    }
    $date = trim($this->{self::PROPERTY_DATE});
    $has_date = !empty($date);
    if (!empty($string) && $has_date) {
      $string .= ", $date";
    }
    elseif ($has_date) {
      $string = $date;
    }
    $additional = trim($this->{self::PROPERTY_ADDITIONAL});
    if (!empty($additional)) {
      $string .= " ($additional)";
    }
    return $string;
  }

}
