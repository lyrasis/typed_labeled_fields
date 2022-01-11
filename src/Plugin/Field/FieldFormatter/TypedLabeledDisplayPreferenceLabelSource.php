<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledEntityReference;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledOriginStatement;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledTextLong;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledTextShort;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledTitle;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledUrl;

/**
 * Options for the display label source for this field formatter.
 */
class TypedLabeledDisplayPreferenceLabelSource extends AbstractPreference {

  // The setting field used to store the user selected configuration.
  const SETTING = 'display_label_source';

  // The possible options for the display label source.
  const SOURCE_LABEL = 'label';
  const SOURCE_TYPE  = 'type';
  const SOURCE_FIELD = 'field';

  /**
   * {@inheritdoc}
   */
  public static function getSetting() {
    return self::SETTING;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults() {
    return [
      self::SOURCE_TYPE,
      self::SOURCE_FIELD,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getDescription() {
    return t('Display label source preferred order');
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions() {
    return [
      self::SOURCE_LABEL => $this->t('Prefer the fields <strong>"Label value"</strong> when present'),
      self::SOURCE_TYPE => $this->t('Prefer the fields <strong>"Type value"</strong> when present'),
      self::SOURCE_FIELD => $this->t('Prefer the fields <strong>"Field name"</strong>'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getRequired() {
    return self::SOURCE_FIELD;
  }

  /**
   * Gets the preferred available display label for the given item.
   *
   * @return array
   *   A pair where the first item is the preference type, and the second is
   *   the display label to use.
   */
  public function getDisplayLabel(FieldItemInterface $item) {
    foreach ($this->preferences as $preference) {
      switch ($preference) {
        case self::SOURCE_LABEL:
          if (!empty($item->label)) {
            return [$preference, $item->label];
          }
          break;

        case self::SOURCE_TYPE:
          if (!empty($item->type)) {
            return [$preference, $item->type->label()];
          }
          break;

        case  self::SOURCE_FIELD:
        default:
          /** @var \Drupal\field\Entity\FieldConfig $config */
          $config = $item->getFieldDefinition();
          return [$preference, $config->label()];
      }
    }
  }

}

/**
 * Options for the display value prefix source for this field formatter.
 *
 * This class represents all possible values it is however limited in practice
 * by the chosen order for `DisplayLabelSourcePreference`.
 */
abstract class AbstractTypedLabeledDisplayPreferenceValuePrefixSource extends AbstractPreference {

  // The possible options for the display value prefix source.
  const SOURCE_LABEL = 'label';
  const SOURCE_LABEL_TYPE = 'label_and_type';
  const SOURCE_TYPE = 'type';
  const SOURCE_TYPE_LABEL = 'type_and_label';
  const SOURCE_NONE = 'none';

  /**
   * The DisplayLabelSourcePreference setting value.
   *
   * @var array
   */
  protected $displayLabelSourcePreferences;

  /**
   * Constructs a AbstractTypedLabeledDisplayPreferenceValuePrefix object.
   */
  public function __construct($field, array $preferences, array $display_label_source_preferences = []) {
    parent::__construct($field, $preferences);
    $this->displayLabelSourcePreferences = $display_label_source_preferences;
  }

  /**
   * Creates a AbstractPreference object with the given field and form state.
   */
  public static function fromFormState($field, FormStateInterface $form_state, array $settings) {
    $preferences = static::getPreferences($field, $form_state, $settings);
    $display_label_source_preferences = TypedLabeledDisplayPreferenceLabelSource::getPreferences($field, $form_state, $settings);
    return new static($field, $preferences, $display_label_source_preferences);
  }

  /**
   * Creates a AbstractPreference object from display label source preference.
   */
  public static function fromDisplayPreferenceLabelSource($display_label_source_preference, $field, array $preferences) {
    switch ($display_label_source_preference) {
      case TypedLabeledDisplayPreferenceValuePrefixForLabel::getDisplayLabelSource():
        return TypedLabeledDisplayPreferenceValuePrefixForLabel::fromSettings($field, $preferences);

      case TypedLabeledDisplayPreferenceValuePrefixForType::getDisplayLabelSource():
        return TypedLabeledDisplayPreferenceValuePrefixForType::fromSettings($field, $preferences);

      case TypedLabeledDisplayPreferenceValuePrefixForField::getDisplayLabelSource():
        return TypedLabeledDisplayPreferenceValuePrefixForField::fromSettings($field, $preferences);

      default:
        throw new \RuntimeException("This state should not be reachable.");
    }
  }

  /**
   * The DisplayLabelSourcePreference for this value prefix source preference.
   *
   * @return string
   *   The source which must not be ignored.
   */
  abstract public static function getDisplayLabelSource();

  /**
   * Checks if the value prefix should be excluded from the set of options.
   *
   * Given a list of preceding display label preferences determine if the given
   * prefix source is applicable.
   */
  protected static function getExcluded() {
    return [
      self::SOURCE_LABEL => [
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_FIELD,
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_LABEL,
      ],
      self::SOURCE_LABEL_TYPE => [
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_FIELD,
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_LABEL,
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_TYPE,
      ],
      self::SOURCE_TYPE => [
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_FIELD,
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_TYPE,
      ],
      self::SOURCE_TYPE_LABEL => [
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_FIELD,
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_TYPE,
        TypedLabeledDisplayPreferenceLabelSource::SOURCE_LABEL,
      ],
      self::SOURCE_NONE => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions() {
    $options = array_intersect_key(
      [
        self::SOURCE_LABEL => $this->t('Prefer to prefix the field value with <strong>"Label value"</strong> when present'),
        self::SOURCE_LABEL_TYPE => $this->t('Prefer to prefix the field value with <strong>"Label value and Type value"</strong>  when both are present'),
        self::SOURCE_TYPE => $this->t('Prefer to prefix the field value with <strong>"Type value"</strong> when present'),
        self::SOURCE_TYPE_LABEL => $this->t('Prefer to prefix the field value with <strong>"Type value and Label value"</strong> when both are present'),
        self::SOURCE_NONE => $this->t('Do not apply a prefix to the field value'),
      ],
      array_combine(static::getDefaults(), static::getDefaults()),
    );

    // Get all DisplayLabelSourcePreference which precede this class's
    // display label source.
    $index = array_search(static::getDisplayLabelSource(), $this->displayLabelSourcePreferences);

    // If display label source has been excluded show the required option only.
    if ($index === FALSE) {
      return [
        static::getRequired() => $options[static::getRequired()],
      ];
    }

    // For any DisplayLabelSourcePreference that precedes our source check if
    // it excludes our options.
    $preceding = array_slice($this->displayLabelSourcePreferences, 0, $index);
    return array_filter($options, function ($key) use ($preceding) {
      $exclude = static::getExcluded()[$key];
      return count(array_intersect($exclude, $preceding)) == 0;
    }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * {@inheritdoc}
   */
  protected static function getRequired() {
    return self::SOURCE_NONE;
  }

  /**
   * Gets the preferred available display label for the given item.
   *
   * @return array
   *   A pair where the first item is the preference type, and the second is
   *   the display label to use.
   */
  public function getDisplayValue(FieldItemInterface $item) {
    $class = get_class($item);
    $value_property = $class::mainPropertyName();
    switch ($class) {
      case TypedLabeledEntityReference::class:
        /** @var \Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledEntityReference $item */
        $element = [
          '#type' => 'inline_template',
          '#template' => '{{ value|nl2br }}',
          '#context' => ['value' => $item->getEntityReference()->label()],
        ];
        break;

      case TypedLabeledTitle::class:
      case TypedLabeledOriginStatement::class:
        $element = [
          '#type' => 'inline_template',
          '#template' => '{{ value|nl2br }}',
          '#context' => ['value' => (string) $item],
        ];
        break;

      case TypedLabeledUrl::class:
        $url = Url::fromUri($item->{$value_property});
        $element = Link::fromTextAndUrl($item->{$value_property}, $url)->toRenderable();
        break;

      case TypedLabeledTextLong::class:
      case TypedLabeledTextShort::class:
      default:
        $element = [
          '#type' => 'inline_template',
          '#template' => '{{ value|nl2br }}',
          '#context' => ['value' => $item->{$value_property}],
        ];
        break;
    }

    foreach ($this->preferences as $preference) {
      switch ($preference) {
        case self::SOURCE_LABEL:
          if (!empty($item->label)) {
            $element['#prefix'] = "{$item->label}: ";
            break 2;
          }
          break;

        case self::SOURCE_LABEL_TYPE:
          if (!empty($item->label) && !empty($item->type)) {
            $element['#prefix'] = "{$item->label}: {$item->type->label()}: ";
            break 2;
          }
          break;

        case self::SOURCE_TYPE:
          if (!empty($item->type)) {
            $element['#prefix'] = "{$item->type->label()}: ";
            break 2;
          }
          break;

        case self::SOURCE_TYPE_LABEL:
          if (!empty($item->type) && !empty($item->label)) {
            $element['#prefix'] = "{$item->type->label()}: {$item->label}: ";
            break 2;
          }
          break;

        case self::SOURCE_NONE:
          break 2;

        default:
          throw new \RuntimeException("This state should not be reachable.");
      }
    }
    return $element;
  }

}

/**
 * Options for the display value prefix source for this field formatter.
 */
class TypedLabeledDisplayPreferenceValuePrefixForLabel extends AbstractTypedLabeledDisplayPreferenceValuePrefixSource {

  // The setting field used to store the user selected configuration.
  const SETTING = 'display_value_prefix_source_for_label';

  /**
   * {@inheritdoc}
   */
  public static function getSetting() {
    return self::SETTING;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults() {
    return [
      self::SOURCE_NONE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getDescription() {
    return t('Value Prefix Source (When "Label value" is the display label source)');
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayLabelSource() {
    return TypedLabeledDisplayPreferenceLabelSource::SOURCE_LABEL;
  }

}

/**
 * Options for the display value prefix source for this field formatter.
 */
class TypedLabeledDisplayPreferenceValuePrefixForType extends AbstractTypedLabeledDisplayPreferenceValuePrefixSource {

  // The setting field used to store the user selected configuration.
  const SETTING = 'display_value_prefix_source_for_type';

  /**
   * {@inheritdoc}
   */
  public static function getSetting() {
    return self::SETTING;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults() {
    return [
      self::SOURCE_LABEL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getDescription() {
    return t('Value Prefix Source (When "Type value" is the display label source)');
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayLabelSource() {
    return TypedLabeledDisplayPreferenceLabelSource::SOURCE_TYPE;
  }

}

/**
 * Options for the display value prefix source for this field formatter.
 */
class TypedLabeledDisplayPreferenceValuePrefixForField extends AbstractTypedLabeledDisplayPreferenceValuePrefixSource {

  // The setting field used to store the user selected configuration.
  const SETTING = 'display_value_prefix_source_for_field';

  /**
   * {@inheritdoc}
   */
  public static function getSetting() {
    return self::SETTING;
  }

  /**
   * {@inheritdoc}
   */
  public static function getDefaults() {
    return [
      self::SOURCE_TYPE_LABEL,
      self::SOURCE_LABEL,
      self::SOURCE_TYPE,
      self::SOURCE_LABEL_TYPE,
      self::SOURCE_NONE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getDescription() {
    return t('Value Prefix Source (When "Field name" is the display label source)');
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayLabelSource() {
    return TypedLabeledDisplayPreferenceLabelSource::SOURCE_FIELD;
  }

}
