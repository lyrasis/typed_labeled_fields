<?php

namespace Drupal\typed_labeled_fields\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraintValidator;
use Drupal\typed_labeled_fields\Field\EntityReferencePropertiesTrait;
use Symfony\Component\Validator\Constraint;

/**
 * Optional Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid, but only if specified.
 *
 * This is as close as possible to 1-1 of ValidReferenceConstraintValidator.
 */
class OptionalValidReferenceConstraintValidator extends ValidReferenceConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $value */
    /** @var ValidReferenceConstraint $constraint */
    if (!isset($value)) {
      return;
    }

    // Collect new entities and IDs of existing entities across the field items.
    // Group by property that references the entity.
    $grouped_target_ids = [];
    foreach ($value as $delta => $item) {
      foreach ($item->getEntityReferencePropertyNames() as $property_entity_name) {
        $target_type = $item->getFieldDefinition()->getFieldStorageDefinition()->getSetting($property_entity_name)['target_type'];
        $property_target_name = EntityReferencePropertiesTrait::getTargetPropertyName($property_entity_name);
        $target_id = $item->{$property_target_name};
        // '0' or NULL are considered valid empty references.
        if (!empty($target_id)) {
          $grouped_target_ids[$property_entity_name][$delta] = $target_id;
        }
      }
    }

    // Early opt-out if nothing to validate.
    if (!$grouped_target_ids) {
      return;
    }

    // Target ID's are grouped by property name which references them, as we
    // can have multiple. Each property can configure a different handler, etc.
    $field_definition = $item->getFieldDefinition();
    $field_storage_definition = $field_definition->getFieldStorageDefinition();
    foreach ($grouped_target_ids as $property_entity_name => $target_ids) {
      $property_entity_field_settings = $field_definition->getSetting($property_entity_name);
      $property_target_name = EntityReferencePropertiesTrait::getTargetPropertyName($property_entity_name);
      $target_type = $field_storage_definition->getSetting($property_entity_name)['target_type'];

      $instance_options = $property_entity_field_settings['handler_settings'] ?: [];
      $instance_options += [
        'target_type' => $target_type,
        'handler' => $property_entity_field_settings['handler'],
        'entity' => NULL,
      ];
      $handler = $this->selectionManager->getInstance($instance_options);

      // Get a list of pre-existing references.
      $previously_referenced_ids = [];
      if ($value->getParent() && ($entity = $value->getEntity()) && !$entity->isNew()) {
        $existing_entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
        foreach ($existing_entity->{$value->getFieldDefinition()->getName()}->getValue() as $item) {
          $previously_referenced_ids[$item[$property_target_name]] = $item[$property_target_name];
        }
      }

      $valid_target_ids = $handler->validateReferenceableEntities($target_ids);
      if ($invalid_target_ids = array_diff($target_ids, $valid_target_ids)) {
        // For accuracy of the error message, differentiate non-referenceable
        // and non-existent entities.
        $existing_entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($invalid_target_ids);
        foreach ($invalid_target_ids as $delta => $target_id) {
          // Check if any of the invalid existing references are simply not
          // accessible by the user, in which case they need to be excluded
          // from validation.
          if (isset($previously_referenced_ids[$target_id]) && isset($existing_entities[$target_id]) && !$existing_entities[$target_id]->access('view')) {
            continue;
          }

          $message = isset($existing_entities[$target_id]) ? $constraint->message : $constraint->nonExistingMessage;
          $this->context->buildViolation($message)
            ->setParameter('%type', $target_type)
            ->setParameter('%id', $target_id)
            ->atPath((string) $delta . ".{$property_target_name}")
            ->setInvalidValue($target_id)
            ->addViolation();
        }
      }
    }
  }

}
