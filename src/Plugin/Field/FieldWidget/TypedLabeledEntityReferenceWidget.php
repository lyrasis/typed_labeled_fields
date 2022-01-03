<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledEntityReference;

/**
 * Plugin implementation of the Typed Labeled widget.
 *
 * @FieldWidget(
 *   id = "typed_labeled_entity_reference_widget",
 *   label = @Translation("Typed Labeled Entity Reference"),
 *   field_types = {
 *     "typed_labeled_entity_reference",
 *   },
 *   multiple_values = FALSE
 * )
 */
class TypedLabeledEntityReferenceWidget extends AbstractTypedLabeledWidget {
  use RequireMainPropertyTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = &$items[$delta];
    $main_property = TypedLabeledEntityReference::mainPropertyName();
    $element[$main_property] = [
      '#type' => 'select',
      '#title' => $this->t('Value'),
      '#options' => $this->getOptions($items->getEntity(), TypedLabeledEntityReference::PROPERTY_VALUE),
      '#default_value' => $this->getSelectedOption($item, TypedLabeledEntityReference::PROPERTY_VALUE, TypedLabeledEntityReference::PROPERTY_VALUE_TARGET),
      '#multiple' => FALSE,
      '#element_validate' => [
        [static::class, 'validateSelectElement'],
      ],
    ];

    return $element;
  }

}
