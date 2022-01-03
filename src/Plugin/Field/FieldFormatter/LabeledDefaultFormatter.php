<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Plugin implementation of the 'Labeled' formatter.
 *
 * @FieldFormatter(
 *   id = "labeled_default_formatter",
 *   label = @Translation("Labeled"),
 *   field_types = {
 *     "labeled_entity_reference",
 *   }
 * )
 */
class LabeledDefaultFormatter extends FormatterBase implements TrustedCallbackInterface {
  use DisplayPreferenceFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // The default precedence of the display label for each field item.
      // Field values will be grouped by their display label.
      LabeledDisplayPreferenceLabelSource::getSetting() => LabeledDisplayPreferenceLabelSource::getDefaults(),
      // Possible prefixes for values if the display label source is 'field'.
      LabeledDisplayPreferenceValuePrefixSourceForField::getSetting() => LabeledDisplayPreferenceValuePrefixSourceForField::getDefaults(),
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['description']['#markup'] = '<p>' . $this->t('Since some components of this field are optional (i.e. <strong>"Label value and Type value"</strong>), we cannot rely on them always being present for display. The tables below denote the preferred source for <em>display labels and value prefixes</em> from most preferred to least preferred.') . '</p>';

    $settings = $this->getSettings();
    $field = $this->fieldDefinition->getName();

    $preferences = [
      LabeledDisplayPreferenceLabelSource::fromFormState($field, $form_state, $settings),
      LabeledDisplayPreferenceValuePrefixSourceForField::fromFormState($field, $form_state, $settings),
    ];

    $elements = array_merge($elements, self::buildPreferenceTables($preferences));

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {
    $elements = parent::view($items, $langcode);
    if (!empty($elements)) {
      $elements['#theme'] = 'field__grouped_by_display_label';
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $settings = $this->getSettings();
    $field = $this->fieldDefinition->getName();
    $label_source_preference = LabeledDisplayPreferenceLabelSource::fromSettings($field, $settings);

    $field_group = [];
    foreach ($items as $item) {
      list($preference, $label) = $label_source_preference->getDisplayLabel($item);
      $value_prefix_preference = AbstractLabeledDisplayPreferenceValuePrefixSource::fromDisplayPreferenceLabelSource($preference, $field, $settings);
      $field_group[$label][] = $value_prefix_preference->getDisplayValue($item);
    }

    $delta = 0;
    foreach ($field_group as $label => $values) {
      $elements[$delta] = [
        'label' => $label,
        'items' => $values,
      ];
      $delta++;
    }

    return $elements;
  }

  /**
   * Renders a processed text element's #markup as a summary.
   *
   * @param array $element
   *   A structured array with the following key-value pairs:
   *   - #markup: the filtered text (as filtered by filter_pre_render_text())
   *   - #format: containing the machine name of the filter format to be used to
   *     filter the text. Defaults to the fallback format. See
   *     filter_fallback_format().
   *   - #text_summary_trim_length: the desired character length of the summary
   *     (used by text_summary())
   *
   * @return array
   *   The passed-in element with the filtered text in '#markup' trimmed.
   *
   * @see filter_pre_render_text()
   * @see text_summary()
   */
  public static function preRenderSummary(array $element) {
    $element['#markup'] = text_summary($element['#markup'], $element['#format'], $element['#text_summary_trim_length']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderSummary'];
  }

}
