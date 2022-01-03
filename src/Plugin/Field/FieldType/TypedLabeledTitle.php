<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a 'typed' and 'labeled' title field.
 *
 * @FieldType(
 *   id = "typed_labeled_title",
 *   label = @Translation("Typed Labeled Title"),
 *   description = @Translation("An collection of properties that describe a title."),
 *   category = @Translation("Labeled"),
 *   default_formatter = "typed_labeled_default_formatter",
 *   default_widget = "typed_labeled_title_widget",
 *   list_class = "\Drupal\typed_labeled_fields\Field\MultipleEntityReferenceFieldItemList",
 * )
 */
class TypedLabeledTitle extends AbstractTypedLabeledItem {
  const PROPERTY_NONSORT = 'nonsort';
  const PROPERTY_TITLE = 'title';
  const PROPERTY_SUBTITLE = 'subtitle';
  const PROPERTY_PARTNUMBER = 'partnumber';
  const PROPERTY_PARTNAME = 'partname';

  const PROPERTIES = [
    self::PROPERTY_LABEL,
    self::PROPERTY_NONSORT,
    self::PROPERTY_TITLE,
    self::PROPERTY_SUBTITLE,
    self::PROPERTY_PARTNUMBER,
    self::PROPERTY_PARTNAME,
    self::PROPERTY_TYPE_TARGET,
    self::PROPERTY_TYPE,
  ];

  const INHERITED_PROPERTIES = [
    self::PROPERTY_LABEL,
    self::PROPERTY_TYPE_TARGET,
    self::PROPERTY_TYPE,
  ];

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return self::PROPERTY_TITLE;
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
      self::PROPERTY_NONSORT => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 32,
      ],
      self::PROPERTY_PARTNAME => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 255,
      ],
      self::PROPERTY_PARTNUMBER => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 255,
      ],
      self::PROPERTY_SUBTITLE => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 1024,
      ],
      self::PROPERTY_TITLE => [
        'case_sensitive' => FALSE,
        'is_ascii' => FALSE,
        'max_length' => 1024,
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

    $properties[self::PROPERTY_NONSORT] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Non-Sort'))
      ->setDescription(new TranslatableMarkup('Characters, including initial articles, punctuation, and spaces that appear at the beginning of a title that should be ignored for indexing of titles.'));
    $properties[self::PROPERTY_PARTNAME] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Part Name'))
      ->setDescription(new TranslatableMarkup('A part or section name of a title.'));
    $properties[self::PROPERTY_PARTNUMBER] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Part Number'))
      ->setDescription(new TranslatableMarkup('A part or section number of a title.'));
    $properties[self::PROPERTY_SUBTITLE] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Subtitle'))
      ->setDescription(new TranslatableMarkup('A word, phrase, character, or group of characters that contains the remainder of the title information after the title proper.'));
    $properties[self::PROPERTY_TITLE] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setDescription(new TranslatableMarkup('A word, phrase, character, or group of characters that constitutes the chief title of a resource, i.e., the title normally used when citing the resource.'));

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
    $property_definitions = $this->fieldDefinition->getFieldStorageDefinition()->getPropertyDefinitions();
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
  public function __toString() {
    $string = $this->{self::PROPERTY_NONSORT};
    $string .= $this->{self::PROPERTY_TITLE};
    if (!empty($this->{self::PROPERTY_SUBTITLE})) {
      // Remove trailing ' : ' allowing for any number of spaces
      // preceding or following the ':'.
      $string = preg_replace('/ *: *$/', '', $string);
      $string .= ': ' . $this->{self::PROPERTY_SUBTITLE};
    }
    if (!empty($this->{self::PROPERTY_PARTNUMBER})) {
      if (preg_match('/[….!-?] *$/', $string)) {
        $string .= ' ' . $this->{self::PROPERTY_PARTNUMBER};
      }
      else {
        $string .= '. ' . $this->{self::PROPERTY_PARTNUMBER};
      }
      if (!empty($this->{self::PROPERTY_PARTNAME})) {
        if (preg_match('/[,] *$/', $string)) {
          $string .= ' ' . $this->{self::PROPERTY_PARTNAME};
        }
        else {
          $string .= ', ' . $this->{self::PROPERTY_PARTNAME};
        }
      }
    }
    elseif (!empty($this->{self::PROPERTY_PARTNAME})) {
      if (preg_match('/[….!-?] *$/', $string)) {
        $string .= ' ' . $this->{self::PROPERTY_PARTNAME};
      }
      else {
        $string .= '. ' . $this->{self::PROPERTY_PARTNAME};
      }
    }
    return $string;
  }

}
