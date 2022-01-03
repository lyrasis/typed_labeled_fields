<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldType;

use Drupal\typed_labeled_fields\Field\EntityReferencePropertiesInterface;
use Drupal\typed_labeled_fields\Field\EntityReferencePropertiesTrait;

/**
 * Base class for Typed Labeled field types.
 */
abstract class AbstractTypedLabeledItem extends AbstractLabeledItem implements EntityReferencePropertiesInterface {
  use EntityReferencePropertiesTrait;

  const PROPERTY_TYPE = 'type';
  const PROPERTY_TYPE_TARGET = 'type_target_id';
  const PROPERTY_TYPE_NAME = 'normalized_type_name';

  /**
   * {@inheritdoc}
   */
  public static function getEntityReferencePropertyNames() {
    return [self::PROPERTY_TYPE];
  }

}
