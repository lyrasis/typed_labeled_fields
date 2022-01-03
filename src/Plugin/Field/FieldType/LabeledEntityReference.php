<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\typed_labeled_fields\Field\EntityReferencePropertiesInterface;
use Drupal\typed_labeled_fields\Field\EntityReferencePropertiesTrait;

/**
 * Provides a 'labeled' entity reference field.
 *
 * @FieldType(
 *   id = "labeled_entity_reference",
 *   label = @Translation("Labeled Entity Reference"),
 *   description = @Translation("An entity reference with a label."),
 *   category = @Translation("Labeled"),
 *   default_formatter = "labeled_default_formatter",
 *   default_widget = "labeled_entity_reference_widget",
 * )
 */
class LabeledEntityReference extends AbstractLabeledItem implements EntityReferencePropertiesInterface {
  use EntityReferencePropertiesTrait;

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
    return [self::PROPERTY_VALUE];
  }

  /**
   * Gets the entity this field references.
   */
  public function getEntityReference() {
    return $this->{self::PROPERTY_VALUE};
  }

}
