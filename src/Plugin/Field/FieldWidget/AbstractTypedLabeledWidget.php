<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\AbstractTypedLabeledItem;

/**
 * Base class for Typed Labeled field widgets.
 */
abstract class AbstractTypedLabeledWidget extends AbstractLabeledWidget {
  use PropertyEntityReferenceOptionsTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $item = &$items[$delta];

    $element[AbstractTypedLabeledItem::PROPERTY_TYPE_TARGET] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $this->getOptions($items->getEntity(), AbstractTypedLabeledItem::PROPERTY_TYPE),
      '#default_value' => $this->getSelectedOption($item, AbstractTypedLabeledItem::PROPERTY_TYPE, AbstractTypedLabeledItem::PROPERTY_TYPE_TARGET),
      '#multiple' => FALSE,
      '#weight' => -1,
      '#element_validate' => [
        [static::class, 'validateSelectElement'],
      ],
    ];
    return $element;
  }

}
