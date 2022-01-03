<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\AbstractTypedLabeledItem;

/**
 * Helpers for working with EntityReference properties.
 */
trait PropertyEntityReferenceOptionsTrait {

  /**
   * Options for select fields keyed by the property name.
   *
   * @var string[]
   */
  protected $options = [];

  /**
   * Returns the array of options for the given property of the field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for which to return options.
   * @param string $property
   *   The property name to fetch the options for.
   *
   * @return array
   *   The array of options for the widget.
   */
  protected function getOptions(FieldableEntityInterface $entity, $property) {
    if (!isset($this->options[$property])) {
      $options = AbstractTypedLabeledItem::getOptions($this->fieldDefinition, $property);

      // Add an empty option.
      $options = ['_none' => $this->t('- None -')] + $options;

      $context = [
        'fieldDefinition' => $this->fieldDefinition,
        'entity' => $entity,
      ];
      $this->moduleHandler->alter('options_list', $options, $context);

      array_walk_recursive($options, [$this, 'sanitizeLabel']);

      $this->options[$property] = $options;
    }
    return $this->options[$property];
  }

  /**
   * Determines selected options from the incoming field values.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field values.
   * @param string $property
   *   The property name to fetch the selected option for.
   * @param string $property_target_id
   *   The property name to that contains the entity target identifier.
   *
   * @return array
   *   The array of corresponding selected options.
   */
  protected function getSelectedOption(FieldItemInterface $item, $property, $property_target_id) {
    // We need to check against a flat list of options.
    $flat_options = OptGroup::flattenOptions($this->getOptions($item->getEntity(), $property));

    // Keep the value if it actually is in the list of options (needs to be
    // checked against the flat list).
    $value = $item->{$property_target_id};
    if (isset($flat_options[$value])) {
      return $value;
    }

    return '_none';
  }

  /**
   * Sanitizes a string label to display as an option.
   *
   * @param string $label
   *   The label to sanitize.
   */
  protected function sanitizeLabel(&$label) {
    // Select form inputs allow unencoded HTML entities, but no HTML tags.
    $label = Html::decodeEntities(strip_tags($label));
  }

}
