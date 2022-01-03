<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledTextLong;

/**
 * Plugin implementation of the Typed Labeled Text Long widget.
 *
 * @FieldWidget(
 *   id = "typed_labeled_text_long_widget",
 *   label = @Translation("Typed Labeled Text Long"),
 *   field_types = {
 *     "typed_labeled_text_long",
 *   },
 *   multiple_values = FALSE
 * )
 */
class TypedLabeledTextLongWidget extends AbstractLabeledWidget {
  use RequireMainPropertyTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = &$items[$delta];
    $main_property = TypedLabeledTextLong::mainPropertyName();
    $element[$main_property] = [
      '#default_value' => isset($item->{$main_property}) ? $item->{$main_property} : '',
      '#title' => $this->t('Value'),
      '#type' => 'textarea',
    ];
    return $element;
  }

}
