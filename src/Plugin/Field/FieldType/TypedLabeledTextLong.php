<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a 'typed' and 'labeled' text field.
 *
 * @FieldType(
 *   id = "typed_labeled_text_long",
 *   label = @Translation("Typed Labeled Text (plain, long)"),
 *   description = @Translation("An entity field containing an entity reference along with a long plain text value."),
 *   category = @Translation("Labeled"),
 *   default_formatter = "typed_labeled_default_formatter",
 *   default_widget = "typed_labeled_text_long_widget",
 *   list_class = "\Drupal\typed_labeled_fields\Field\MultipleEntityReferenceFieldItemList",
 * )
 */
class TypedLabeledTextLong extends AbstractTypedLabeledItem {

  const PROPERTY_VALUE = 'value';

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
      ],
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    $schema = parent::schema($field_definition);
    $schema['columns'][self::PROPERTY_VALUE] = [
      'type' => $settings[self::PROPERTY_VALUE]['case_sensitive'] ? 'blob' : 'text',
      'size' => 'big',
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
      ->setSetting('case_sensitive', $settings[self::PROPERTY_VALUE]['case_sensitive'])
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);
    $random = new Random();
    $values[self::PROPERTY_VALUE] = $random->paragraphs();
    return $values;
  }

}
