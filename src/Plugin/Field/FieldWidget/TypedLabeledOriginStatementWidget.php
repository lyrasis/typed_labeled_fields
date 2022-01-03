<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledOriginStatement;

/**
 * Plugin implementation of the Typed Labeled widget.
 *
 * @FieldWidget(
 *   id = "typed_labeled_origin_statement_widget",
 *   label = @Translation("Typed Labeled Origin Statement"),
 *   field_types = {
 *     "typed_labeled_origin_statement",
 *   },
 *   multiple_values = FALSE
 * )
 */
class TypedLabeledOriginStatementWidget extends AbstractTypedLabeledWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = &$items[$delta];

    $first = $delta == 0;
    $is_config_form = $form_state->getBuildInfo()['base_form_id'] == 'field_config_form';
    $property_definitions = $this->fieldDefinition->getFieldStorageDefinition()->getPropertyDefinitions();
    foreach (TypedLabeledOriginStatement::nonInheritedProperties() as $property) {
      $required = FALSE;
      if ($first && !$is_config_form && $property == TypedLabeledOriginStatement::mainPropertyName()) {
        // We must have at least one title defined for the entity.
        $required = TRUE;
      }
      $element[$property] = [
        '#default_value' => isset($item->{$property}) ? $item->{$property} : '',
        '#title' => $property_definitions[$property]->getLabel(),
        '#description' => $property_definitions[$property]->getDescription(),
        '#maxlength' => $this->fieldDefinition->getSetting($property)['max_length'],
        '#required' => $required,
        '#type' => 'textfield',
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function formSingleElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formSingleElement($items, $delta, $element, $form, $form_state);
    /*
    foreach (TypedLabeledOriginStatement::REQUIRED_PROPERTIES as $property) {
      $element[$property]['#states']['required'] = [
        ":input[name='default_value_input[field_origin_statement][0][place]']" => ['value' => ''],
        ":input[name='default_value_input[field_origin_statement][0][agent]']" => ['value' => ''],
        ":input[name='default_value_input[field_origin_statement][0][date]']" => ['value' => ''],
        [
          [":input[name='default_value_input[field_origin_statement][0][label]']" => ['!value' => '']],
          'or',
          [":input[name='default_value_input[field_origin_statement][0][additional]']" => ['!value' => '']],
        ],
      ];
    }
    return $element;
    */

    foreach (TypedLabeledOriginStatement::REQUIRED_PROPERTIES as $property) {
      // If explicitly required skip state based conditional requirement.
      $explicitly_required = isset($element[$property]['#required']) && $element[$property]['#required'] == TRUE;
      if (!$explicitly_required) {
        $element[$property]['#element_validate'][] = [
          $this,
          'conditionallyRequireElements',
        ];
        $this->setRequiredElementRequiredStates($delta, $property, $element);
      }
    }
    return $element;
  }

  /**
   * Make element conditionally required via #states.
   */
  protected function setRequiredElementRequiredStates($delta, $name, array &$element) {
    $field_name = $this->fieldDefinition->getName();
    $children = array_diff(Element::children($element), TypedLabeledOriginStatement::REQUIRED_PROPERTIES);

    // Assumes '#tree' is false on parents. Makes element required if any other
    // element has a value.
    $required = [];
    // Only mark as required if none of the required fields are populated.
    foreach (TypedLabeledOriginStatement::REQUIRED_PROPERTIES as $property) {
      $required[":input[name='${field_name}[${delta}][${property}]'], :input[name='default_value_input[${field_name}][${delta}][${property}]']"] = ['value' => ''];
    }
    // Only mark as required if remaining fields are also populated.
    $required_non_empty = [];
    foreach ($children as $child) {
      $not_empty = ($element[$child]['#type'] == 'select') ? ['!value' => '_none'] : ['!value' => ''];
      $required_non_empty[][":input[name='${field_name}[${delta}][${child}]'], :input[name='default_value_input[${field_name}][${delta}][${child}]']"] = $not_empty;
      $required_non_empty[] = 'or';
    }
    array_pop($required_non_empty);
    $required[] = $required_non_empty;
    $element[$name]['#states']['required'] = $required;
  }

  /**
   * Require 'value' if any other form element in the field is populated.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function conditionallyRequireElements(array $element, FormStateInterface $form_state) {
    $value_path = array_slice($element['#parents'], 0, count($element['#parents']) - 1);
    $values = NestedArray::getValue($form_state->getValues(), $value_path);
    $empty_values = TRUE;
    foreach (TypedLabeledOriginStatement::REQUIRED_PROPERTIES as $property) {
      if (!static::empty($element, $values[$property])) {
        $empty_values = FALSE;
        break;
      }
    }
    if ($empty_values) {
      $parent_path = array_slice($element['#array_parents'], 0, count($element['#array_parents']) - 1);
      $parent = NestedArray::getValue($form_state->getCompleteForm(), $parent_path);
      $children = array_diff(
        Element::children($parent),
        array_merge(TypedLabeledOriginStatement::REQUIRED_PROPERTIES, ['_weight'])
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
