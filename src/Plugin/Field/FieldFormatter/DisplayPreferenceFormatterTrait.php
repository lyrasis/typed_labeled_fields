<?php

namespace Drupal\typed_labeled_fields\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Common functionality for formatters which rely on display preferences.
 */
trait DisplayPreferenceFormatterTrait {

  /**
   * Gets the default value for the given setting.
   */
  protected function getPreferences($setting, $field, FormStateInterface $form_state) {
    $parents = [
      'fields',
      $field,
      'settings_edit_form',
      'settings',
      $setting,
    ];
    if ($form_state->hasValue($parents)) {
      return $form_state->getValue($parents);
    }
    else {
      return $this->getSetting($setting);
    }
  }

  /**
   * Builds a form with tables for each preference.
   */
  protected static function buildPreferenceTables(array $preferences) {
    $tables = [];
    foreach ($preferences as $preference) {
      $id = $preference::getSetting();
      $elements[$id] = $preference->buildForm();
      $tables[] = $elements[$id]['#attributes']['id'];
    }

    $elements['#attributes']['class'][] = 'clearfix';
    $elements['#attached']['library'][] = 'typed_labeled_fields/field.admin';
    $elements['#attached']['drupalSettings']['typed_labeled_fields'] = [
      'tables' => $tables,
    ];

    return $elements;
  }

}
