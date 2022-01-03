<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledTextShort;

/**
 * Plugin implementation of the Typed Labeled widget.
 *
 * @FieldWidget(
 *   id = "typed_labeled_text_short_widget",
 *   label = @Translation("Typed Labeled Text Short"),
 *   field_types = {
 *     "typed_labeled_text_short",
 *   },
 *   multiple_values = FALSE
 * )
 */
class TypedLabeledTextShortWidget extends AbstractTypedLabeledWidget {
  use RequireMainPropertyTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = &$items[$delta];
    $main_property = TypedLabeledTextShort::mainPropertyName();
    $element[$main_property] = [
      '#default_value' => isset($item->{$main_property}) ? $item->{$main_property} : '',
      '#title' => $this->t('Value'),
      '#maxlength' => $this->fieldDefinition->getSetting($main_property)['max_length'],
      '#type' => 'textfield',
    ];
    return $element;
  }

}
