<?php

namespace Drupal\typed_labeled_fields\Field;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;

/**
 * Supports one or more Entity References properties on a field item.
 */
trait EntityReferencePropertiesTrait {

  /**
   * {@inheritdoc}
   */
  abstract public static function mainPropertyName();

  /**
   * Gets a list of all the Entity Reference property names.
   */
  abstract public static function getEntityReferencePropertyNames();

  /**
   * Get the Entity Reference property name from the Target property name.
   */
  public static function getEntityReferencePropertyName($property_target_name) {
    return str_replace('_target_id', '', $property_target_name);
  }

  /**
   * Get the Target property name from the Entity Reference property name.
   */
  public static function getTargetPropertyName($property_entity_name) {
    return "{$property_entity_name}_target_id";
  }

  /**
   * Get the Target UUID property name from the Entity Reference property name.
   *
   * Only used for serializing/deserializing the default value form, because
   * configuration should only use UUID values for content.
   */
  public static function getTargetUuidPropertyName($property_entity_name) {
    return "{$property_entity_name}_target_uuid";
  }

  /**
   * Checks if it has this field has an entity reference.
   */
  public function hasEntityReferenceTarget($property_entity_name) {
    return $this->{static::getTargetPropertyName($property_entity_name)} !== NULL;
  }

  /**
   * Checks if it has this field has an entity reference.
   */
  public function getEntityReferenceTarget($property_entity_name) {
    return $this->{static::getTargetPropertyName($property_entity_name)};
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_storage_definition) {
    $properties = parent::propertyDefinitions($field_storage_definition);

    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      $property_entity_field_storage_settings = $field_storage_definition->getSetting($property_entity_name);
      $target_type = $property_entity_field_storage_settings['target_type'];
      $target_type_info = \Drupal::entityTypeManager()->getDefinition($target_type);

      $target_id_data_type = 'string';
      if ($target_type_info->entityClassImplements(FieldableEntityInterface::class)) {
        $id_definition = \Drupal::service('entity_field.manager')->getBaseFieldDefinitions($target_type)[$target_type_info->getKey('id')];
        if ($id_definition->getType() === 'integer') {
          $target_id_data_type = 'integer';
        }
      }

      if ($target_id_data_type === 'integer') {
        $target_id_definition = DataReferenceTargetDefinition::create('integer')
          ->setLabel(new TranslatableMarkup('@name Target ID', ['@name' => ucfirst($property_entity_name)]))
          ->setSetting('unsigned', TRUE);
      }
      else {
        $target_id_definition = DataReferenceTargetDefinition::create('string')
          ->setLabel(new TranslatableMarkup('@name Target ID', ['@name' => ucfirst($property_entity_name)]));
      }
      // Only require the the Entity Reference Target property if it is the
      // main property.
      $property_target_name = static::getTargetPropertyName($property_entity_name);
      $required = ($property_target_name == static::mainPropertyName()) ? TRUE : FALSE;
      $target_id_definition->setRequired($required);
      $properties[$property_target_name] = $target_id_definition;

      $properties[$property_entity_name] = DataReferenceDefinition::create('entity')
        ->setLabel(new TranslatableMarkup('@name', ['@name' => ucfirst($property_entity_name)]))
        ->setDescription(new TranslatableMarkup('The referenced entity for the Type property'))
        // The entity object is computed out of the entity ID.
        ->setComputed(TRUE)
        ->setReadOnly(FALSE)
        ->setTargetDefinition(EntityDataDefinition::create($target_type))
        // We can add a constraint for the target entity type. The list of
        // referenceable bundles is a field setting, so the corresponding
        // constraint is added dynamically in ::getConstraints().
        ->addConstraint('EntityType', $target_type);

    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = parent::defaultStorageSettings();
    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      $settings[$property_entity_name] = [
        'target_type' => 'taxonomy_term',
      ];
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = parent::defaultFieldSettings();
    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      $settings[$property_entity_name] = [
        'handler' => 'default',
        'handler_settings' => [],
      ];
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    parent::setValue($values, $notify);
    if (is_array($values)) {
      foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
        $property_target_name = static::getTargetPropertyName($property_entity_name);
        if (is_array($values) && array_key_exists($property_target_name, $values) && !isset($values[$property_entity_name])) {
          $this->onChange($property_target_name, FALSE);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      // Make sure that the type target ID and the computed target property
      // type stay in sync.
      $property_target_name = static::getTargetPropertyName($property_entity_name);
      if ($property_name == $property_target_name) {
        $this->writePropertyValue($property_entity_name, $this->{$property_target_name});
      }
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_storage_definition) {
    $schema = parent::schema($field_storage_definition);
    $property_definitions = $field_storage_definition->getPropertyDefinitions();
    $field_storage_settings = $field_storage_definition->getSettings();
    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      $label = $property_definitions[$property_entity_name]->getLabel();
      $property_target_name = static::getTargetPropertyName($property_entity_name);
      $target_type = $field_storage_settings[$property_entity_name]['target_type'];
      $target_type_info = \Drupal::entityTypeManager()->getDefinition($target_type);

      if ($target_type_info->entityClassImplements(FieldableEntityInterface::class) && $property_definitions[$property_target_name]->getDataType() === 'integer') {
        $schema['columns'][$property_target_name] = [
          'description' => "The ID of the target entity for {$label} property.",
          'type' => 'int',
          'unsigned' => TRUE,
        ];
      }
      else {
        $schema['columns'][$property_target_name] = [
          'description' => "The ID of the target entity for {$label} property.",
          'type' => 'varchar_ascii',
          // If the target entities act as bundles for another entity type,
          // their IDs should not exceed the maximum length for bundles.
          'length' => $target_type_info->getBundleOf() ? EntityTypeInterface::BUNDLE_MAX_LENGTH : 255,
        ];
      }

      $schema['indexes'][$property_target_name] = [$property_target_name];
    }
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\field_ui\Form\FieldConfigEditForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Field\Entity\FieldConfig $field */
    $field = $form_object->getEntity();

    $field_storage_definition = $field->getFieldStorageDefinition();

    $form = [
      '#type' => 'container',
      '#process' => [[static::class, 'fieldSettingsAjaxProcess']],
      '#element_validate' => [[static::class, 'fieldSettingsFormValidate']],
    ];

    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      // Each entity has it's own target and handler / settings.
      $property_entity_field_settings = $this->getSetting($property_entity_name);

      // Get all selection plugins for this entity type.
      $target_type = $field_storage_definition->getSetting($property_entity_name)['target_type'];
      $selection_plugins = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionGroups($target_type);
      $handlers_options = [];
      foreach (array_keys($selection_plugins) as $selection_group_id) {
        // We only display base plugins (e.g. 'default', 'views', ...) and not
        // entity type specific plugins (e.g. 'default:node', 'default:user',
        // ...).
        if (array_key_exists($selection_group_id, $selection_plugins[$selection_group_id])) {
          $handlers_options[$selection_group_id] = Html::escape($selection_plugins[$selection_group_id][$selection_group_id]['label']);
        }
        elseif (array_key_exists($selection_group_id . ':' . $target_type, $selection_plugins[$selection_group_id])) {
          $selection_group_plugin = $selection_group_id . ':' . $target_type;
          $handlers_options[$selection_group_plugin] = Html::escape($selection_plugins[$selection_group_id][$selection_group_plugin]['base_plugin_label']);
        }
      }

      $form[$property_entity_name]['handler'] = [
        '#type' => 'details',
        '#title' => $this->t('@label: Reference Settings', [
          '@label' => $field_storage_definition->getPropertyDefinitions()[$property_entity_name]->getLabel(),
        ]),
        '#open' => TRUE,
        '#tree' => TRUE,
        '#process' => [[static::class, 'formProcessMergeParent']],
      ];

      $form[$property_entity_name]['handler']['handler'] = [
        '#type' => 'select',
        '#title' => $this->t('Reference method'),
        '#options' => $handlers_options,
        '#default_value' => $property_entity_field_settings['handler'],
        '#required' => TRUE,
        '#ajax' => TRUE,
        '#limit_validation_errors' => [],
      ];

      $form[$property_entity_name]['handler']['handler_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Change handler'),
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['js-hide'],
        ],
        '#submit' => [[static::class, 'settingsAjaxSubmit']],
      ];

      $form[$property_entity_name]['handler']['handler_settings'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['entity_reference-settings']],
      ];

      $instance_options = $property_entity_field_settings['handler_settings'] ?: [];
      $instance_options += [
        'target_type' => $target_type,
        'handler' => $property_entity_field_settings['handler'],
        'entity' => NULL,
      ];
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($instance_options);
      $form[$property_entity_name]['handler']['handler_settings'] += $handler->buildConfigurationForm([], $form_state);

      // Prevent auto-creation for optional entity references,
      // the entity must first exist to be referenced.
      $form[$property_entity_name]['handler']['handler_settings']['auto_create']['#access'] = FALSE;
      $form[$property_entity_name]['handler']['handler_settings']['auto_create']['#default_value'] = FALSE;
      $form[$property_entity_name]['handler']['handler_settings']['auto_create_bundle']['#access'] = FALSE;
      $form[$property_entity_name]['handler']['handler_settings']['auto_create_bundle']['#default_value'] = NULL;
    }

    return $form;
  }

  /**
   * Form element validation handler; Invokes selection plugin's validation.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\field_ui\Form\FieldConfigEditForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\Field\Entity\FieldConfig $field */
    $field = $form_object->getEntity();
    $field_storage_definition = $field->getFieldStorageDefinition();

    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      // Each entity has it's own target and handler / settings.
      $property_entity_field_settings = $field->getSetting($property_entity_name);

      $instance_options = $property_entity_field_settings['handler_settings'] ?: [];
      $instance_options += [
        'target_type' => $field_storage_definition->getSetting($property_entity_name)['target_type'],
        'handler' => $property_entity_field_settings['handler'],
        'entity' => NULL,
      ];
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($instance_options);
      $handler->validateConfigurationForm($form, $form_state);
    }

  }

  /**
   * Render API callback.
   *
   * Moves entity_reference specific Form API elements
   * (i.e. 'handler_settings') up a level for easier processing by the
   * validation and submission handlers.
   *
   * @see _entity_reference_field_settings_process()
   */
  public static function formProcessMergeParent($element) {
    $parents = $element['#parents'];
    array_pop($parents);
    $element['#parents'] = $parents;
    return $element;
  }

  /**
   * Ajax callback for the handler settings form.
   *
   * @see static::fieldSettingsForm()
   */
  public static function settingsAjax($form, FormStateInterface $form_state) {
    return NestedArray::getValue($form, $form_state->getTriggeringElement()['#ajax']['element']);
  }

  /**
   * Submit handler for the non-JS case.
   *
   * @see static::fieldSettingsForm()
   */
  public static function settingsAjaxSubmit($form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Render API callback.
   *
   * Processes the field settings form and allows access to the form state.
   *
   * @see static::fieldSettingsForm()
   */
  public static function fieldSettingsAjaxProcess($form, FormStateInterface $form_state) {
    static::fieldSettingsAjaxProcessElement($form, $form);
    return $form;
  }

  /**
   * Adds entity_reference specific properties to AJAX form elements.
   *
   * @see static::fieldSettingsAjaxProcess()
   */
  public static function fieldSettingsAjaxProcessElement(&$element, $main_form) {
    if (!empty($element['#ajax'])) {
      $element['#ajax'] = [
        'callback' => [static::class, 'settingsAjax'],
        'wrapper' => $main_form['#id'],
        'element' => $main_form['#array_parents'],
      ];
    }

    foreach (Element::children($element) as $key) {
      static::fieldSettingsAjaxProcessElement($element[$key], $main_form);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);

    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      $selection_handler = static::getSelectionHandler($field_definition, $property_entity_name);

      // Select a random number of references between the last 50 referenceable
      // entities created.
      $property_target_name = static::getTargetPropertyName($property_entity_name);
      if ($referenceable = $selection_handler->getReferenceableEntities(NULL, 'CONTAINS', 50)) {
        $group = array_rand($referenceable);
        $values[$property_target_name] = array_rand($referenceable[$group]);
      }
      else {
        // Do not generate referenced entities, instead just set to NULL.
        $values[$property_target_name] = NULL;
      }
    }
    return $values;
  }

  /**
   * Get selection handler for the given entity reference property name.
   *
   * @param \FieldDefinitionInterface $field_definition
   *   The field definition the property belongs to.
   * @param string $property_entity_name
   *   An property of this field that is an Entity Reference.
   *
   * @return \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface
   *   The selection plugin.
   */
  protected static function getSelectionHandler(FieldDefinitionInterface $field_definition, $property_entity_name) {
    // Each entity property has it's own target and handler / settings.
    $manager = \Drupal::service('plugin.manager.entity_reference_selection');
    // Instead of calling $manager->getSelectionHandler($field_definition)
    // replicate the behavior to be able to override the sorting settings.
    $property_entity_field_settings = $field_definition->getSetting($property_entity_name);
    $options = [
      'target_type' => $field_definition->getFieldStorageDefinition()->getSetting($property_entity_name)['target_type'],
      'handler' => $property_entity_field_settings['handler'],
    ] + $property_entity_field_settings['handler_settings'] ?: [];

    $entity_type = \Drupal::entityTypeManager()->getDefinition($options['target_type']);
    $options['sort'] = [
      'field' => $entity_type->getKey('id'),
      'direction' => 'DESC',
    ];
    return $manager->getInstance($options);
  }

  /**
   * Returns an array of values with labels for display.
   */
  public static function getOptions(FieldDefinitionInterface $field_definition, $property_entity_name) {
    if (!$options = static::getSelectionHandler($field_definition, $property_entity_name)->getReferenceableEntities()) {
      return [];
    }
    // Rebuild the array by changing the bundle key into the bundle label.
    $target_type = $field_definition->getFieldStorageDefinition()->getSetting($property_entity_name)['target_type'];
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($target_type);
    $return = [];
    foreach ($options as $bundle => $entity_ids) {
      // The label does not need sanitizing since it is used as an optgroup
      // which is only supported by select elements and auto-escaped.
      $bundle_label = (string) $bundles[$bundle]['label'];
      $return[$bundle_label] = $entity_ids;
    }
    return count($return) == 1 ? reset($return) : $return;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(FieldDefinitionInterface $field_definition) {
    $dependencies = parent::calculateDependencies($field_definition);

    $field_storage_definition = $field_definition->getFieldStorageDefinition();
    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      // Each entity has it's own target and handler / settings.
      $property_entity_field_settings = $field_definition->getSetting($property_entity_name);

      $entity_type_manager = \Drupal::entityTypeManager();
      $target_entity_type = $entity_type_manager->getDefinition($field_storage_definition->getSetting($property_entity_name)['target_type']);

      // Depend on default values entity types configurations.
      $target_key = static::getTargetUuidPropertyName($property_entity_name);
      if ($default_value = $field_definition->getDefaultValueLiteral()) {
        $entity_repository = \Drupal::service('entity.repository');
        foreach ($default_value as $value) {
          if (is_array($value) && isset($value[$target_key])) {
            $entity = $entity_repository->loadEntityByUuid($target_entity_type->id(), $value[$target_key]);
            // If the entity does not exist do not create the dependency.
            // @see \Drupal\Core\Field\EntityReferenceFieldItemList::processDefaultValue()
            if ($entity) {
              $dependencies[$target_entity_type->getConfigDependencyKey()][] = $entity->getConfigDependencyName();
            }
          }
        }
      }

      // Depend on target bundle configurations.
      $handler_settings = $property_entity_field_settings['handler_settings'];
      if (!empty($handler_settings['target_bundles'])) {
        if ($bundle_entity_type_id = $target_entity_type->getBundleEntityType()) {
          if ($storage = $entity_type_manager->getStorage($bundle_entity_type_id)) {
            foreach ($storage->loadMultiple($handler_settings['target_bundles']) as $bundle) {
              $dependencies[$bundle->getConfigDependencyKey()][] = $bundle->getConfigDependencyName();
            }
          }
        }
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateStorageDependencies(FieldStorageDefinitionInterface $field_storage_definition) {
    $dependencies = parent::calculateStorageDependencies($field_storage_definition);
    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      $target_entity_type = \Drupal::entityTypeManager()->getDefinition($field_storage_definition->getSetting($property_entity_name)['target_type']);
      $dependencies['module'][] = $target_entity_type->getProvider();
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public static function onDependencyRemoval(FieldDefinitionInterface $field_definition, array $dependencies) {
    $changed = parent::onDependencyRemoval($field_definition, $dependencies);

    $field_storage_definition = $field_definition->getFieldStorageDefinition();
    foreach (static::getEntityReferencePropertyNames() as $property_entity_name) {
      // Each entity has it's own target and handler / settings.
      $property_entity_field_settings = $field_definition->getSetting($property_entity_name);

      $entity_type_manager = \Drupal::entityTypeManager();
      $target_entity_type = \Drupal::entityTypeManager()->getDefinition($field_storage_definition->getSetting($property_entity_name)['target_type']);

      // Try to update the default value config dependency, if possible.
      $target_key = static::getTargetUuidPropertyName($property_entity_name);
      if ($default_value = $field_definition->getDefaultValueLiteral()) {
        $entity_repository = \Drupal::service('entity.repository');
        foreach ($default_value as $key => $value) {
          if (is_array($value) && isset($value[$target_key])) {
            $entity = $entity_repository->loadEntityByUuid($target_entity_type->id(), $value[$target_key]);
            // @see \Drupal\Core\Field\EntityReferenceFieldItemList::processDefaultValue()
            if ($entity && isset($dependencies[$entity->getConfigDependencyKey()][$entity->getConfigDependencyName()])) {
              unset($default_value[$key]);
              $changed = TRUE;
            }
          }
        }
        if ($changed) {
          /** @var Drupal\Core\Field\FieldConfigInterface $field_definition */
          $field_definition->setDefaultValue($default_value);
        }
      }

      // Update the 'target_bundles' handler setting if a bundle config
      // dependency has been removed.
      $bundles_changed = FALSE;
      $handler_settings = &$property_entity_field_settings['handler_settings'];
      if (!empty($handler_settings['target_bundles'])) {
        if ($bundle_entity_type_id = $target_entity_type->getBundleEntityType()) {
          if ($storage = $entity_type_manager->getStorage($bundle_entity_type_id)) {
            foreach ($storage->loadMultiple($handler_settings['target_bundles']) as $bundle) {
              if (isset($dependencies[$bundle->getConfigDependencyKey()][$bundle->getConfigDependencyName()])) {
                unset($handler_settings['target_bundles'][$bundle->id()]);

                // If this bundle is also used in the 'auto_create_bundle'
                // setting, disable the auto-creation feature completely.
                $auto_create_bundle = !empty($handler_settings['auto_create_bundle']) ? $handler_settings['auto_create_bundle'] : FALSE;
                if ($auto_create_bundle && $auto_create_bundle == $bundle->id()) {
                  $handler_settings['auto_create'] = FALSE;
                  $handler_settings['auto_create_bundle'] = NULL;
                }

                $bundles_changed = TRUE;
              }
            }
          }
        }
      }
      if ($bundles_changed) {
        /** @var Drupal\Core\Field\FieldConfigInterface $field_definition */
        $field_definition->setSetting($property_entity_name, $property_entity_field_settings);
      }
      $changed |= $bundles_changed;
    }
    return $changed;
  }

}
