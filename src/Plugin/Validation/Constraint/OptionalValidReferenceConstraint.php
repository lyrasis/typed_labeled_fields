<?php

namespace Drupal\typed_labeled_fields\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;

/**
 * Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid.
 *
 * @Constraint(
 *   id = "OptionalValidReference",
 *   label = @Translation("Optional Entity Reference valid reference", context = "Validation")
 * )
 */
class OptionalValidReferenceConstraint extends ValidReferenceConstraint {}
