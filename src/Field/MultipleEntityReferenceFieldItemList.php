<?php

namespace Drupal\typed_labeled_fields\Field;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataReferenceDefinition;

/**
 * Unlike EntityReferenceFieldItemList it supports multiple entity references.
 */
class MultipleEntityReferenceFieldItemList extends FieldItemList {

  /**
   * Helper function to get all property names which reference Entities.
   *
   * @return string[]
   *   The property names of all entity reference types.
   */
  public static function getEntityReferencePropertyNames(FieldStorageDefinitionInterface $field_storage_definition) {
    $property_definitions = $field_storage_definition->getPropertyDefinitions();
    return array_keys(array_filter($property_definitions, function ($property_definition) {
        return $property_definition instanceof DataReferenceDefinition;
    }));
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('OptionalValidReference', []);
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    $default_value = parent::processDefaultValue($default_value, $entity, $definition);

    if ($default_value) {
      // Convert UUIDs to numeric IDs.
      $field_storage_definition = $definition->getFieldStorageDefinition();
      foreach (static::getEntityReferencePropertyNames($field_storage_definition) as $property_entity_name) {
        // Each entity has it's own target and handler / settings.
        $property_entity_field_storage_settings = $field_storage_definition->getSetting($property_entity_name);
        $target_id_key = EntityReferencePropertiesTrait::getTargetPropertyName($property_entity_name);
        $target_uuid_key = EntityReferencePropertiesTrait::getTargetUuidPropertyName($property_entity_name);

        $uuids = [];
        foreach ($default_value as $delta => $properties) {
          if (isset($properties[$target_uuid_key])) {
            $uuids[$delta] = $properties[$target_uuid_key];
          }
        }
        if ($uuids) {
          $target_type = $property_entity_field_storage_settings['target_type'];
          $entity_ids = \Drupal::entityQuery($target_type)
            ->accessCheck(TRUE)
            ->condition('uuid', $uuids, 'IN')
            ->execute();
          $entities = \Drupal::entityTypeManager()
            ->getStorage($target_type)
            ->loadMultiple($entity_ids);

          $entity_uuids = [];
          foreach ($entities as $id => $entity) {
            $entity_uuids[$entity->uuid()] = $id;
          }
          foreach ($uuids as $delta => $uuid) {
            if (isset($entity_uuids[$uuid])) {
              $default_value[$delta][$target_id_key] = $entity_uuids[$uuid];
              unset($default_value[$delta][$target_uuid_key]);
            }
            else {
              unset($default_value[$delta]);
            }
          }
        }
      }

      // Ensure we return consecutive deltas, in case we removed unknown UUIDs.
      $default_value = array_values($default_value);
    }
    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormSubmit(array $element, array &$form, FormStateInterface $form_state) {
    $default_value = parent::defaultValuesFormSubmit($element, $form, $form_state);

    $field_storage_definition = $this->getFieldDefinition()->getFieldStorageDefinition();
    foreach (static::getEntityReferencePropertyNames($field_storage_definition) as $property_entity_name) {
      // Each entity has it's own target and handler / settings.
      // Each entity has it's own target and handler / settings.
      $property_entity_field_storage_settings = $field_storage_definition->getSetting($property_entity_name);
      $target_type = $property_entity_field_storage_settings['target_type'];
      $target_id_key = EntityReferencePropertiesTrait::getTargetPropertyName($property_entity_name);
      $target_uuid_key = EntityReferencePropertiesTrait::getTargetUuidPropertyName($property_entity_name);

      // Convert numeric IDs to UUIDs to ensure config deployability.
      $ids = [];
      foreach ($default_value as $delta => $properties) {
        $ids[] = $default_value[$delta][$target_id_key];
      }

      $entities = \Drupal::entityTypeManager()
        ->getStorage($target_type)
        ->loadMultiple($ids);

      foreach ($default_value as $delta => $properties) {
        if (isset($default_value[$delta][$target_id_key])) {
          unset($default_value[$delta][$target_id_key]);
          $default_value[$delta][$target_uuid_key] = $entities[$properties[$target_id_key]]->uuid();
        }
      }
    }

    return $default_value;
  }

}
