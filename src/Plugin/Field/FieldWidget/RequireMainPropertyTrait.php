<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Makes a widget require the 'value' property.
 */
trait RequireMainPropertyTrait {

  /**
   * {@inheritdoc}
   */
  protected function formSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formSingleElement($items, $delta, $element, $form, $form_state);
    // In addition to states handle programmatically submitted forms,
    // that skip the javascript for safety.
    $class = $this->fieldDefinition->getItemDefinition()->getClass();
    $property_name = $class::mainPropertyName();
    // If explicitly required skip state based conditional requirement.
    $explicitly_required = isset($element[$property_name]['#required']) && $element[$property_name]['#required'] == TRUE;
    if (!$explicitly_required) {
      $element[$property_name]['#element_validate'][] = [
        $this,
        'conditionallyRequireMainPropertyElement',
      ];
      $this->setMainPropertyElementRequiredStates($delta, $element);
    }
    return $element;
  }

  /**
   * Make element conditionally required via #states.
   */
  protected function setMainPropertyElementRequiredStates($delta, array &$element) {
    $class = $this->fieldDefinition->getItemDefinition()->getClass();
    $field_name = $this->fieldDefinition->getName();
    $property_name = $class::mainPropertyName();
    $children = array_diff(Element::children($element), [$property_name]);

    // Assumes '#tree' is false on parents. Makes element required if any other
    // element has a value.
    $required = [];
    foreach ($children as $child) {
      $not_empty = ['!value' => ''];
      if ($element[$child]['#type'] == 'select') {
        $not_empty = ['!value' => '_none'];
      }
      $required[] = [":input[name='${field_name}[${delta}][${child}]'], :input[name='default_value_input[${field_name}][${delta}][${child}]']" => $not_empty];
    }
    $element[$property_name]['#states']['required'] = $required;
  }

  /**
   * Require 'value' if any other form element in the field is populated.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function conditionallyRequireMainPropertyElement(array $element, FormStateInterface $form_state) {
    $value_path = array_slice($element['#parents'], 0, count($element['#parents']) - 1);
    $values = NestedArray::getValue($form_state->getValues(), $value_path);
    $class = $this->fieldDefinition->getItemDefinition()->getClass();
    $property_name = $class::mainPropertyName();
    $empty_value = static::empty($element, $values[$property_name]);
    if ($empty_value) {
      $parent_path = array_slice($element['#array_parents'], 0, count($element['#array_parents']) - 1);
      $parent = NestedArray::getValue($form_state->getCompleteForm(), $parent_path);
      $children = array_diff(
        Element::children($parent),
        [$property_name, '_weight']
      );
      foreach ($children as $child) {
        $empty = static::empty($parent[$child], $values[$child]);
        if (!$empty) {
          $form_state->setError($element, t('@name field is required.', ['@name' => $element['#title']]));
          return;
        }
      }
    }
  }

  /**
   * Checks if the given value is considered empty for the given form element.
   */
  public static function empty(array $element, $value) {
    if ($element['#type'] == 'select') {
      return ($value === '_none' || $value === NULL);
    }
    else {
      return empty(trim($value));
    }
  }

}
