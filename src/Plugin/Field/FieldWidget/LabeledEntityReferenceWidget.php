<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\LabeledEntityReference;

/**
 * Plugin implementation of 'Labeled Entity Reference'.
 *
 * @FieldWidget(
 *   id = "labeled_entity_reference_widget",
 *   label = @Translation("Labeled Entity Reference"),
 *   field_types = {
 *     "labeled_entity_reference",
 *   },
 *   multiple_values = FALSE
 * )
 */
class LabeledEntityReferenceWidget extends AbstractLabeledWidget {
  use PropertyEntityReferenceOptionsTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $item = &$items[$delta];

    $element[LabeledEntityReference::mainPropertyName()] = [
      '#type' => 'select',
      '#title' => $this->t('Value'),
      '#options' => $this->getOptions($items->getEntity(), LabeledEntityReference::PROPERTY_VALUE),
      '#default_value' => $this->getSelectedOption($item, LabeledEntityReference::PROPERTY_VALUE, LabeledEntityReference::PROPERTY_VALUE_TARGET),
      '#multiple' => FALSE,
      '#element_validate' => [
        [static::class, 'validateSelectElement'],
      ],
    ];

    return $element;
  }

}
