<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Provides a 'typed' and 'labeled' url field.
 *
 * @FieldType(
 *   id = "typed_labeled_url",
 *   label = @Translation("Typed Labeled URL"),
 *   description = @Translation("An entity field containing an entity reference along with a url value."),
 *   category = @Translation("Labeled"),
 *   default_formatter = "typed_labeled_default_formatter",
 *   default_widget = "typed_labeled_url_widget",
 *   list_class = "\Drupal\typed_labeled_fields\Field\MultipleEntityReferenceFieldItemList",
 * )
 */
class TypedLabeledUrl extends AbstractTypedLabeledItem {

  const PROPERTY_VALUE = 'value';

  const URL_LENGTH = 2048;

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return self::PROPERTY_VALUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns'][self::PROPERTY_VALUE] = [
      'type' => 'varchar',
      'length' => self::URL_LENGTH,
      'not null' => TRUE,
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties[self::PROPERTY_VALUE] = DataDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Value'))
      ->setRequired(TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);
    $random = new Random();

    // Set of possible top-level domains.
    $tlds = ['com', 'net', 'gov', 'org', 'edu', 'biz', 'info'];
    // Set random length for the domain name.
    $domain_length = mt_rand(7, 15);
    $values[self::PROPERTY_VALUE] = 'http://www.' . $random->word($domain_length) . '.' . $tlds[mt_rand(0, (count($tlds) - 1))];

    return $values;
  }

}
