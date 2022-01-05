<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedIdentifier;

/**
 * Plugin implementation of the Typed identifier widget.
 *
 * @FieldWidget(
 *   id = "typed_identifier_widget",
 *   label = @Translation("Typed identifier"),
 *   field_types = {
 *     "typed_identifier",
 *   },
 *   multiple_values = FALSE
 * )
 */
class TypedIdentifierWidget extends AbstractLabeledWidget {
  use RequireMainPropertyTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = &$items[$delta];
    $main_property = TypedIdentifier::mainPropertyName();
    $element[$main_property] = [
      '#default_value' => isset($item->{$main_property}) ? $item->{$main_property} : '',
      '#title' => $this->t('Value'),
      '#type' => 'textarea',
    ];
    return $element;
  }

}
