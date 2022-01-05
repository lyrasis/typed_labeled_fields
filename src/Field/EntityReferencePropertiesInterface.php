<?php

namespace Drupal\typed_labeled_fields\Field;

/**
 * Helper function to get all property names which reference Entities.
 */
interface EntityReferencePropertiesInterface {

  /**
   * Gets a list of all entity reference property names.
   *
   * @return string[]
   *   The property names of all entity reference types.
   */
  public static function getEntityReferencePropertyNames();

}
