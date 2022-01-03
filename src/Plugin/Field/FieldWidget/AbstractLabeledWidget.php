<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldWidget;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\AbstractLabeledItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Labeled field widgets.
 */
abstract class AbstractLabeledWidget extends WidgetBase {

  /**
   * Drupal\Core\Extension\ModuleHandler definition.
   *
   * @var Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ModuleHandler $module_handler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('module_handler'));
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = &$items[$delta];

    $label_property = AbstractLabeledItem::PROPERTY_LABEL;
    return [
      $label_property => [
        '#default_value' => isset($item->{$label_property}) ? $item->{$label_property} : NULL,
        '#maxlength' => $this->fieldDefinition->getSetting($label_property)['max_length'],
        '#title' => $this->t('Label'),
        '#type' => 'textfield',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function validateSelectElement(array $element, FormStateInterface $form_state) {
    if ($element['#required'] && $element['#value'] == '_none') {
      $form_state->setError($element, t('@name field is required.', ['@name' => $element['#title']]));
    }
    // Replace '_none' with NULL.
    if ($element['#value'] == '_none') {
      $form_state->setValueForElement($element, NULL);
    }
  }

}
