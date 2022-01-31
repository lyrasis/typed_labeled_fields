<?php

namespace Drupal\typed_labeled_fields\Plugin\search_api\processor;

use Drupal\field\FieldConfigInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\node\NodeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\Core\Language\LanguageInterface;

/**
 * Adds "fake fields" to the index.
 *
 * @SearchApiProcessor(
 *   id = "fakefields_index_fake_fields",
 *   label = @Translation("Index fake fields"),
 *   description = @Translation("Index fields managed by the Fake Fields module."),
 *   stages = {
 *     "add_properties" = 0,
 *   }
 * )
 */
class IndexFakeFields extends ProcessorPluginBase implements PluginFormInterface {

  /**
   * The list of fake fields in the processor config form.
   *
   * @var array
   */

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];
    if (!$datasource) {
      $definition = [
        'label' => $this->t('Fake field'),
        'description' => $this->t('Fake field managed by the Fake Fields module'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];

      // Processes the fake fields into Solr Fields.
      $this->fake_fields_list = [];
      $this->fake_fields_list[] = preg_split("/\\r\\n|\\r|\\n/", $this->configuration['fake_fields']);
      if (count($this->fake_fields_list[0]) > 1 || !empty($this->fake_fields_list[0][0])) {
        foreach ($this->fake_fields_list[0] as $fake_field) {
          $transliterated = \Drupal::transliteration()->transliterate(t('@fakeField', ['@fakeField' => $fake_field]), LanguageInterface::LANGCODE_DEFAULT, '_');
          $transliterated = mb_strtolower($transliterated);
          $transliterated = preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);
          $fake_field_name = trim($transliterated);
          $properties[$fake_field_name] = new ProcessorProperty($definition);
        }
      }
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $node = $item->getOriginalObject()->getValue();
      if (!($node instanceof NodeInterface)) {
        return;
      }
      // List of Drupal fields with Typed Labeled Text (plain) field type.
      $fake_fields_source = $this->configuration['fake_fields_source'];

      // Review nodes with a declared Typed Labeled Text (plain) field type.
      if ($node->hasField($fake_fields_source)) {
        $fake_fields = [];
        $fake_fields_source_value = $node->get($fake_fields_source)->getValue();

        if (isset($fake_fields_source_value[0]['value'])) {
          foreach ($fake_fields_source_value as $fake_fields_source_value_each) {

            // Get the Taxonomy term machine name.
            $term_name = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($fake_fields_source_value_each['type_target_id'])->getName();
            // Get the optional user submitted "Label" for the
            // Islandora Object's identifier type.
            $temp_label = $term_name . '_' . $fake_fields_source_value_each['label'];
            // Concatenate this into a single String to Process
            // as Solr field machine Name.
            $new_fake_fields_label = $fake_fields_source_value_each['label'] ? $temp_label : $term_name;

            // Sanitized solr field name.
            $transliterated = \Drupal::transliteration()->transliterate(t('@newFakeFieldsLabel', ['@newFakeFieldsLabel' => $new_fake_fields_label]), LanguageInterface::LANGCODE_DEFAULT, '_');
            $transliterated = mb_strtolower($transliterated);
            $transliterated = preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);

            $fields = $item->getFields(FALSE);
            $fields = $this->getFieldsHelper()->filterForPropertyPath($fields, NULL, $transliterated);

            // Break existing fields into an array and add new value to array.
            $known_fields = preg_split("/\r\n|\n|\r/", $this->configuration['fake_fields']);
            $faker_field = $this->configuration['fake_fields'] . PHP_EOL . $transliterated;

            // If the field is not already in the list, add it.
            if (!empty($fields)) {
              $fake_fields[$transliterated] = $fake_fields_source_value_each['value'];

              // Avoid duplicate entries.
              if (!in_array($transliterated, $known_fields)) {
                // Save the value to the Solr config field list.
                $this->configuration['fake_fields'] = $faker_field;
                $config = \Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
                $config->set('processor_settings.fakefields_index_fake_fields.fake_fields', $this->configuration['fake_fields']);
                $config->save();
                \Drupal::logger('typed_labeled_fields')->info('Updated Solr Field list for the "add button"');
              }
            }
          }
        }
      }
    }
    catch (\Throwable $t) {
      \Drupal::logger('typed_labeled_fields')->warning($t->getMessage());
    }
    catch (\Exception | \Error $e) {
      \Drupal::logger('typed_labeled_fields')->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration += [
      'fake_fields_source' => '',
      'fake_fields' => '',
    ];
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function compileFakeFields() {
    /* To Do: Convert this into a service for scalability. */

    // Compile list of Drupal fields that are using the
    // Typed Labeled Text (plain) / typed_labeled_text_short.
    $this->compileFakeFieldsSources();

    // Set variables to use.
    $entity_type_id = 'node';
    $bundle = 'islandora_object';
    $field_type = 'typed_labeled_text_short';
    $fake_fields_source = $this->configuration['fake_fields_source'];

    // Setup node query.
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $storage->getQuery()
      ->condition('status', 1)
      ->condition('type', $bundle)
      ->sort('created', 'DESC')
      ->execute();
    // ->pager(15)
    // Create a list of fields for Islandora Object nodes.
    $definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node', $bundle);

    // Don't judge, this nest is inhabited by ..... ERMAHGERD.
    foreach ($definitions as $key) {
      if ($key->getType() == $field_type) {
        $fake_fields_sourcex = $key->get('field_name');
        if (!empty($query)) {
          $nodes = $storage->loadMultiple($query);
          foreach ($nodes as $node) {
            $fake_fields_source_value = $node->get($fake_fields_sourcex)->getValue();
            if ($fake_fields_source_value) {
              foreach ($fake_fields_source_value as $fake_fields_source_value_each) {
                // Get the Taxonomy term machine name.
                $term_name = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($fake_fields_source_value_each['type_target_id'])->getName();
                // Get the optional user submitted "Label" for the
                // Islandora Object's identifier type.
                $temp_label = $term_name . '_' . $fake_fields_source_value_each['label'];
                // Concatenate this into a single String to
                // Process as Solr field machine Name.
                $new_fake_fields_label = $fake_fields_source_value_each['label'] ? $temp_label : $term_name;
                // Sanitized solr field name.
                $transliterated = \Drupal::transliteration()->transliterate(t('@newFakeFieldsLabel', ['@newFakeFieldsLabel' => $new_fake_fields_label]), LanguageInterface::LANGCODE_DEFAULT, '_');
                $transliterated = mb_strtolower($transliterated);
                $transliterated = preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);
                // Break existing fields into an array and add new value
                // to array.
                $known_fields = preg_split("/\r\n|\n|\r/", $this->configuration['fake_fields']);
                $faker_field = $this->configuration['fake_fields'] . PHP_EOL . $transliterated;
                $this->configuration['fake_fields'] = $faker_field;
                if (!in_array($transliterated, $known_fields) && !empty($transliterated)) {
                  // List of Solr field names generated by
                  // Identifier Types + Label.
                  $config = \Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
                  $config->set('processor_settings.fakefields_index_fake_fields.fake_fields', $this->configuration['fake_fields']);
                  $config->save();
                  \Drupal::logger('typed_labeled_fields')->info('Updated List of Solr field names generated by Identifier Types + Label.');
                }
              }
            }
          }
        }
      }
    }
    // return;.
  }

  /**
   * Compile a list of all identifiers types from the Type + Label.
   */
  public function compileFakeFieldsSources() {
    // Check if the fake_fields_source list needs to be updated.
    $islandora_object_fields = array_filter(
      \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'islandora_object'),
      function ($fieldDefinition) {
        return $fieldDefinition instanceof FieldConfigInterface;
      }
    );
    $result = [];
    foreach ($islandora_object_fields as $key => $definition) {
      $result[$key] = [
        'type' => $definition->getType(),
      ];
      // If the field is a reference field get also the target entity type.
      if ($result[$key]['type'] == "typed_labeled_text_short") {
        $transliterated = \Drupal::transliteration()->transliterate(t('@def', ['@def' => $definition->get('field_name')]), LanguageInterface::LANGCODE_DEFAULT, '_');
        $transliterated = mb_strtolower($transliterated);
        $transliterated = preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);
        // Break existing fields into an array and add new value to array.
        $known_source_fields = preg_split("/\r\n|\n|\r/", $this->configuration['fake_fields_source']);

        // If not in list and is not an empty string add it.
        if (!in_array($transliterated, $known_source_fields) && !empty($transliterated)) {
          if (!in_array($transliterated, $known_source_fields)) {
            $this->configuration['fake_fields_source'] = t('@$transliterate', ['@$transliterate' => $transliterated]) . PHP_EOL;
            $config_source = \Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
            $config_source->set('processor_settings.fakefields_index_fake_fields.fake_fields_source', $this->configuration['fake_fields_source']);
            $config_source->save();
            \Drupal::logger('typed_labeled_fields')->info('Updated Machine names list of Drupal fields with the type typed_labeled_text_short');
          }
        }
      }
    }
    // return;.
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#description'] = t('If this area is empty after compiling check that the Content Type has at least one field of type typed_labeled_text_short.');

    $form['fake_fields_source'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Machine names of the Drupal fields of the type "typed_labeled_text_short".'),
      '#description' => $this->t('These fields should be of type "Typed Labeled Text (plain)" or "Typed Labeled Text (plain, long)".'),
      '#default_value' => $this->configuration['fake_fields_source'],
    ];

    // Validate form to remove blank lines.
    $form['fake_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of Solr field names generated by Identifier Types + Label'),
      '#description' => $this->t('isbn_furiously_happy'),
      '#default_value' => $this->configuration['fake_fields'],
    ];

    // Validate form to remove blank lines.
    $form['fake_fields_fetch'] = [
      '#type' => 'submit',
      '#value' => t('Compile Solr field names'),
      '#submit' => [[$this, 'compileFakeFields']],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState) {
    // Deduplicate the fake fields.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());

  }

}
