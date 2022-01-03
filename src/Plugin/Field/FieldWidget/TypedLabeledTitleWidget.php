<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledTitle;

/**
 * Plugin implementation of the Typed Labeled widget.
 *
 * @FieldWidget(
 *   id = "typed_labeled_title_widget",
 *   label = @Translation("Typed Labeled Title"),
 *   field_types = {
 *     "typed_labeled_title",
 *   },
 *   multiple_values = FALSE
 * )
 */
class TypedLabeledTitleWidget extends AbstractTypedLabeledWidget {
  use RequireMainPropertyTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = &$items[$delta];

    $first = $delta == 0;
    $is_config_form = $form_state->getBuildInfo()['base_form_id'] == 'field_config_form';
    $property_definitions = $this->fieldDefinition->getFieldStorageDefinition()->getPropertyDefinitions();
    foreach (TypedLabeledTitle::nonInheritedProperties() as $property_name) {
      $required = FALSE;
      if ($first && !$is_config_form && $property_name == TypedLabeledTitle::mainPropertyName()) {
        // We must have at least one title defined for the entity.
        $required = TRUE;
      }
      $element[$property_name] = [
        '#default_value' => isset($item->{$property_name}) ? $item->{$property_name} : '',
        '#title' => $property_definitions[$property_name]->getLabel(),
        '#description' => $property_definitions[$property_name]->getDescription(),
        '#maxlength' => $this->fieldDefinition->getSetting($property_name)['max_length'],
        '#required' => $required,
        '#type' => 'textfield',
      ];
    }

    return $element;
  }

}
