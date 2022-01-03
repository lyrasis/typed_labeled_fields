<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledUrl;

/**
 * Plugin implementation of the Typed Labeled widget.
 *
 * @FieldWidget(
 *   id = "typed_labeled_url_widget",
 *   label = @Translation("Typed Labeled Url"),
 *   field_types = {
 *     "typed_labeled_url",
 *   },
 *   multiple_values = FALSE
 * )
 */
class TypedLabeledUrlWidget extends AbstractLabeledWidget {
  use RequireMainPropertyTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $item = &$items[$delta];
    $main_property = TypedLabeledUrl::mainPropertyName();
    $element[$main_property] = [
      '#default_value' => isset($item->{$main_property}) ? $item->{$main_property} : '',
      '#description' => $this->t('This must be an external URL such as http://example.com.'),
      '#maxlength' => TypedLabeledUrl::URL_LENGTH,
      '#title' => $this->t('Value'),
      '#type' => 'url',
    ];
    return $element;
  }

}
