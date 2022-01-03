<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Models a orderable set of options that can be displayed as a table.
 *
 * Used to rank preferences across several options.
 */
abstract class AbstractPreference {
  use StringTranslationTrait;

  // CSS classes used to bind table-drag behavior to.
  const WEIGHT_FIELD_CLASS = 'field-weight';
  const DISPLAY_FIELD_CLASS = 'field-display';

  // Regions in the table which denote if a given field
  // is visible or not.
  const REGION_VISIBLE = 'visible';
  const REGION_HIDDEN = 'hidden';

  /**
   * The name of the field which uses these preferences for display, etc.
   *
   * @var string
   */
  protected $field;

  /**
   * The current preferences from configuration or submitted form values.
   *
   * @var string[]
   */
  protected $preferences;

  /**
   * Constructs a AbstractPreference object.
   */
  public function __construct($field, array $preferences) {
    $this->field = $field;
    $this->preferences = $preferences;
  }

  /**
   * Gets the preferences from the form state or existing configuration.
   */
  public static function getPreferences($field, FormStateInterface $form_state, array $settings) {
    $parents = [
      'fields',
      $field,
      'settings_edit_form',
      'settings',
      static::getSetting(),
    ];
    if ($form_state->hasValue($parents)) {
      return $form_state->getValue($parents);
    }
    else {
      return $settings[static::getSetting()];
    }
  }

  /**
   * Creates a AbstractPreference object with the given field and form state.
   */
  public static function fromFormState($field, FormStateInterface $form_state, array $settings) {
    return new static($field, static::getPreferences($field, $form_state, $settings));
  }

  /**
   * Creates a AbstractPreference object with the given settings.
   */
  public static function fromSettings($field, array $settings) {
    return new static($field, $settings[static::getSetting()]);
  }

  /**
   * The name used to identify this setting.
   *
   * @return string
   *   The setting name.
   */
  abstract public static function getSetting();

  /**
   * Default setting value if non has been given before.
   *
   * @return array
   *   List of settings values that are to be used if none have been specified.
   */
  abstract public static function getDefaults();

  /**
   * Description to display in the administration form.
   *
   * @return array
   *   Renderable array that describes this setting.
   */
  abstract protected static function getDescription();

  /**
   * Map of options to descriptions for display in the administration form.
   *
   * @return array
   *   Map of setting values to human readable labels.
   */
  abstract protected function getOptions();

  /**
   * The option to use in the case where no other options are present.
   *
   * @return string
   *   The option which must not be ignored.
   */
  abstract protected static function getRequired();

  /**
   * Get regions of table to display.
   *
   * @return array
   *   The properties of each region used for building the table of fields.
   */
  protected static function getRegions() {
    // Classes for select fields like 'weight' and 'display' are hard-coded
    // and used in js/typed_labeled_fields.field.admin.js.
    return [
      self::REGION_VISIBLE => [
        'title' => t('Visible'),
        'invisible' => TRUE,
        'message' => t('No option is visible.'),
        'weight' => self::WEIGHT_FIELD_CLASS . '-visible',
        'display' => self::DISPLAY_FIELD_CLASS . '-visible',
      ],
      self::REGION_HIDDEN => [
        'title' => t('Ignore'),
        'invisible' => FALSE,
        'message' => t('No option is ignored.'),
        'weight' => self::WEIGHT_FIELD_CLASS . '-hidden',
        'display' => self::DISPLAY_FIELD_CLASS . '-hidden',
      ],
    ];
  }

  /**
   * Options for field display derived from the available regions.
   *
   * @return array
   *   Display select field options.
   */
  protected static function getDisplayOptions() {
    $options = [];
    foreach (self::getRegions() as $region => $settings) {
      $options[$region] = $settings['title'];
    }
    return $options;
  }

  /**
   * Creates a table element to embedded in the administrator form.
   *
   * @return array
   *   A table element that allows for re-ordering the preferences.
   */
  public function buildForm() {
    // All possible preferences.
    $options = $this->getOptions();

    // Range of weights.
    $weight_delta = round(count($options) / 2);

    // Group each field into a region given our current configuration.
    $regions = self::getRegions();
    $display_options = self::getDisplayOptions();

    // Rows are grouped by the region in which they are displayed.
    $region_rows = array_fill_keys(array_keys($regions), []);

    // Limit the display to those blah.
    foreach ($options as $identifier => $label) {
      // If a field exists in the settings than it is 'visible' and
      // its weight is equivalent to its order in the table,
      // i.e. its index.
      $weight = array_search($identifier, $this->preferences);
      $visible = $weight !== FALSE;
      $region = $visible ? self::REGION_VISIBLE : self::REGION_HIDDEN;
      $region_rows[$region][$identifier] = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
        'label' => ['#markup' => $label],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $visible ? $weight : 0,
          '#delta' => $weight_delta,
          '#attributes' => [
            'class' => [self::WEIGHT_FIELD_CLASS, $regions[$region]['weight']],
          ],
        ],
        'display' => [
          '#type' => 'select',
          '#title' => $this->t('Display'),
          '#title_display' => 'invisible',
          '#options' => static::getRequired() != $identifier ? $display_options : [self::REGION_VISIBLE => $display_options[self::REGION_VISIBLE]],
          '#default_value' => $region,
          '#attributes' => [
            'class' => [self::DISPLAY_FIELD_CLASS, $regions[$region]['display']],
          ],
        ],
      ];
    }
    // Sort the visible rows by their weight.
    uasort($region_rows[self::REGION_VISIBLE], function ($a, $b) {
      $a = $a['weight']['#default_value'];
      $b = $b['weight']['#default_value'];
      if ($a == $b) {
        return 0;
      }
      return ($a < $b) ? -1 : 1;
    });

    // Build Rows.
    $rows = [];
    $table_drag = [];
    foreach ($regions as $region => $properties) {
      $rows += [
        // Conditionally display region title as a row.
        "region-$region" => $properties['invisible'] ? NULL : [
          '#attributes' => [
            'class' => ['region-title', "region-title-$region"],
          ],
          'label' => [
            '#plain_text' => $properties['title'],
            '#wrapper_attributes' => [
              'colspan' => 4,
            ],
          ],
        ],
        // Will dynamically display if the region has fields or not controlled
        // by Drupal behaviors in js/typed_labeled_fields.field.admin.js.
        "region-$region-message" => [
          '#attributes' => [
            'class' => [
              'region-message',
              "region-$region-message",
              empty($region_rows[$region]) ? 'region-empty' : 'region-populated',
            ],
          ],
          'message' => [
            '#markup' => '<em>' . $properties['message'] . '</em>',
            '#wrapper_attributes' => [
              'colspan' => 4,
            ],
          ],
        ],
      ];

      // Include region rows in this region.
      $rows += $region_rows[$region];

      // Configure order by weight field in region.
      $table_drag[] = [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => self::WEIGHT_FIELD_CLASS,
        'subgroup' => $properties['weight'],
        'source' => self::WEIGHT_FIELD_CLASS,
      ];

      // Configure drag action for display field in region.
      $table_drag[] = [
        'action' => 'match',
        'relationship' => 'sibling',
        'group' => self::DISPLAY_FIELD_CLASS,
        'subgroup' => $properties['display'],
        'source' => self::DISPLAY_FIELD_CLASS,
      ];
    }

    return [
      '#type' => 'table',
      '#attributes' => [
        // Identifier is used by js/typed_labeled_fields.field.admins.js.
        'id' => HTML::getId($this->field . '-' . static::getSetting()),
      ],
      '#header' => [
        static::getDescription(),
        $this->t('Weight'),
        $this->t('Display'),
      ],
      '#value_callback' => [self::class, 'valueCallback'],
      '#element_validate' => [
        [self::class, 'validateTableElement'],
      ],
      '#empty' => $this->t('Empty'),
      '#tabledrag' => $table_drag,
      '#required_option' => static::getRequired(),
    ] + $rows;
  }

  /**
   * Determines how user input is mapped to an element's #value property.
   *
   * Removes 'ignored' values.
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if (is_array($input)) {
      $visible = array_filter($input, function ($row) {
        return $row['display'] == self::REGION_VISIBLE;
      });
      uasort($visible, '\Drupal\Component\Utility\SortArray::sortByWeightElement');
      return array_keys($visible);
    }
    return [];
  }

  /**
   * Form validation handler for table elements.
   *
   * Normalizes the value to something that can be stored in the fields
   * display settings.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateTableElement(array $element, FormStateInterface $form_state) {
    if (!in_array($element['#required_option'], $element['#value'])) {
      $row = $element[$element['#required_option']];
      $message = t("The option '@option' cannot be ignored", ['@option' => $row['label']['#markup']]);
      $form_state->setError($row, $message);
    }
    $form_state->setValueForElement($element, $element['#value']);
  }

}
