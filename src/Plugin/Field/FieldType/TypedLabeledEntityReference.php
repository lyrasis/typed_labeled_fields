<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Provides a 'typed' and 'labeled' entity reference field.
 *
 * @FieldType(
 *   id = "typed_labeled_entity_reference",
 *   label = @Translation("Typed Labeled Entity Reference"),
 *   description = @Translation("An entity reference along with an optional type entity reference and label."),
 *   category = @Translation("Labeled"),
 *   default_formatter = "typed_labeled_default_formatter",
 *   default_widget = "typed_labeled_entity_reference_widget",
 *   list_class = "\Drupal\typed_labeled_fields\Field\MultipleEntityReferenceFieldItemList",
 * )
 */
class TypedLabeledEntityReference extends AbstractTypedLabeledItem {

  const PROPERTY_VALUE = 'value';
  const PROPERTY_VALUE_TARGET = 'value_target_id';

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return self::PROPERTY_VALUE_TARGET;
  }

  /**
   * {@inheritdoc}
   */
  public static function getEntityReferencePropertyNames() {
    return array_merge(parent::getEntityReferencePropertyNames(), [self::PROPERTY_VALUE]);
  }

  /**
   * Gets the entity this field references.
   */
  public function getEntityReference() {
    return $this->{self::PROPERTY_VALUE};
  }

  /**
   * What.
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_storage_definition) {
    $properties = parent::propertyDefinitions($field_storage_definition);
    file_put_contents('/tmp/properties.txt', print_r(array_keys($properties), TRUE));
    return $properties;
  }

}
