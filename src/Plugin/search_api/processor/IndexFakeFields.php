<?php

namespace Drupal\typed_labeled_fields\Plugin\search_api\processor;

use Drupal;
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
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL): array
  {
    $properties = [];

    if ($this->configuration['fake_fields'] === NULL) {
      ksm('OK');
      return $properties;
    }

    if (!$datasource) {
      // Processes the fake fields into Solr Fields.
      $this->fake_fields_list = [];
      foreach (array_filter(preg_split("/\\r\\n|\\r|\\n/", $this->configuration['fake_fields'])) as $value) {
          $this->fake_fields_list[]=$value;
        }
        $config = Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
        $existing_fields = $config->get('field_settings');

        if (count($this->fake_fields_list) > 1 || !empty($this->fake_fields_list[0])) {
        foreach ($this->fake_fields_list as $fake_field) {
          $transliterated = Drupal::transliteration()->transliterate(t('@fakeField', ['@fakeField' => $fake_field]), LanguageInterface::LANGCODE_DEFAULT, '_');
          $transliterated = mb_strtolower($transliterated);
          $transliterated = preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);
          $fake_field_name = trim($transliterated);

          if (!$fake_field_name == '') {
            $definition = [
              'label' => t('Fake Field: @fakeField', ['@fakeField' => $fake_field]),
              'description' => $this->t('Fake field managed by the Fake Fields module'),
              'type' => 'string',
              'processor_id' => $this->getPluginId(),
              'hidden' => array_key_exists($fake_field_name, $existing_fields),
            ];
            $properties[$fake_field_name] = new ProcessorProperty($definition);
          }
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
      $this->compileFakeFields();
      $fake_fields_source = $this->configuration['fake_fields_source'];

      // Review nodes with a declared Typed Labeled Text (plain) field type.
      if ($node->hasField($fake_fields_source)) {
        $fake_fields = [];
        $fake_fields_source_value = $node->get($fake_fields_source)->getValue();

        if (isset($fake_fields_source_value[0]['value'])) {
          foreach ($fake_fields_source_value as $fake_fields_source_value_each) {

            // Get the Taxonomy term machine name.
            $new_fake_fields_label = $this->createDynamicLabel($fake_fields_source_value_each);
            // Break existing fields into an array and add new value to array.
            $known_fields = [];
            foreach (array_filter(preg_split("/\r\n|\n|\r/", $this->configuration['fake_fields'])) as $fvalue) {
              $known_fields[]=$fvalue;
            }
            // $faker_field = $this->configuration['fake_fields'] . PHP_EOL . rtrim($new_fake_fields_label, '_');
            
            // This is the field value IE. 978-1250077028
            $this->fake_fields[$new_fake_fields_label] = $fake_fields_source_value_each['value'];

            // If the field is not already in the list, add it.
            if (count($known_fields) > 0 ) {
              $config = Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
              $existing_fields = $config->get('field_settings');
              // OUTPUTS: [label, type_target_id, value]
              // $fake_fields_source_value_each

              // OUTPUTS: STRING field_discogs_happy_dog
              // $new_fake_fields_label

              // OUTPUTS: STRING 978-1250077028
              // $this->fake_fields[$new_fake_fields_label]
              
              $fields = $item->getFields(FALSE);
              $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, $new_fake_fields_label);

              if (!empty($fields) && !in_array($new_fake_fields_label, $existing_fields)) {
                foreach ($fields as $field) {
                    $field->addValue($this->fake_fields[$new_fake_fields_label]);
                  // \Drupal::logger('typed_labeled_fields')->info('New field added: @newFakeFieldsLabel', ['@newFakeFieldsLabel' => $new_fake_fields_label]);
                }
              }
            }
          }
        }
      }
    }
    catch (\Throwable $t) {
      Drupal::logger('typed_labeled_fields')->warning($t->getMessage());
    }
    catch (\Exception | \Error $e) {
      Drupal::logger('typed_labeled_fields')->error($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array
  {
    $configuration = parent::defaultConfiguration();
    $configuration += [
      'fake_fields_prefix' => 'field_',
      'fake_fields_source' => '',
      'fake_fields' => '',
    ];
    return $configuration;
  }

  /**
   * {}
   */
  public function compileFakeFields() {
    /* To Do: Convert this into a service for scalability. */

    // Compile list of Drupal fields that are using the
    // Typed Labeled Text (plain) / typed_labeled_text_short.
    $this->compileFakeFieldsSources();
    // Set variables to use.
      $bundle = 'islandora_object';
    $field_type = 'typed_labeled_text_short';

      // Setup node query.
      try {
          $storage = Drupal::entityTypeManager()->getStorage('node');
          $query = $storage->getQuery()
              ->condition('status', 1)
              ->condition('type', $bundle)
              ->sort('created')
              ->execute();
      } catch (Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException|Drupal\Component\Plugin\Exception\PluginNotFoundException $e) {
        Drupal::logger('typed_labeled_fields')->error($e->getMessage());
      }
    // Create a list of fields for Islandora Object nodes.
    $definitions = Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node', $bundle);

    // Don't judge, this nest is inhabited by ..... ERMAHGERD.
    $first_ran = FALSE;
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
                $new_fake_fields_label = $this->createDynamicLabel($fake_fields_source_value_each);
                $new_fake_fields_label = str_replace("__", "_", $new_fake_fields_label);

                // Break existing fields into an array and add new value to array.
                $known_fields = preg_split("/\r\n|\n|\r/", $this->configuration['fake_fields']);

                // Avoid adding a new line if there is only one field.
                $fake_fields_source_text = t('@$transliterate', ['@$transliterate' => $new_fake_fields_label]);
                if ($first_ran) {
                  $fake_fields_source_text = PHP_EOL . $fake_fields_source_text;
                  $first_ran = FALSE;
                }

                $faker_field = $this->configuration['fake_fields'] . PHP_EOL . rtrim($new_fake_fields_label, '_');
                $this->configuration['fake_fields'] = $faker_field;
                if (!in_array($new_fake_fields_label, $known_fields) && !empty($new_fake_fields_label)) {
                  // List of Solr field names generated by Identifier Types + Label.
                  $config = Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
                  $config->set('processor_settings.fakefields_index_fake_fields.fake_fields', $faker_field);
                  $config->save();
                  Drupal::logger('typed_labeled_fields')->info('Updated List of Solr field names generated by Identifier Types + Label.');
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * Compile a list of all identifiers types from the Type + Label.
   */
  public function compileFakeFieldsSources() {
    // Check if the fake_fields_source list needs to be updated.
    $islandora_object_fields = array_filter(
      Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'islandora_object'),
      function ($fieldDefinition) {
        return $fieldDefinition instanceof FieldConfigInterface;
      }
    );
    $result = [];
    $first_ran = FALSE;
    foreach ($islandora_object_fields as $key => $definition) {
      $result[$key] = [
        'type' => $definition->getType(),
      ];
      // If the field is a reference field get also the target entity type.
      if ($result[$key]['type'] == "typed_labeled_text_short") {
        $transliterated = Drupal::transliteration()->transliterate(t('@def', ['@def' => $definition->get('field_name')]), LanguageInterface::LANGCODE_DEFAULT, '_');
        $transliterated = mb_strtolower($transliterated);
        $transliterated = preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);

        // Break existing fields into an array and add new value to array.
        $known_source_fields = preg_split("/\r\n|\n|\r/", $this->configuration['fake_fields_source']);
        
        // If not in list and is not an empty string add it.
        if (!in_array($transliterated, $known_source_fields) && !empty($transliterated)) {
          // Avoid adding a new line if there is only one field.
          $fake_fields_source_text = t('@$transliterate', ['@$transliterate' => $transliterated]);
          if ($first_ran) {
            $fake_fields_source_text = PHP_EOL . $fake_fields_source_text;
            $first_ran = TRUE;
          }

          if (!in_array($transliterated, $known_source_fields)) {
            $this->configuration['fake_fields_source'] = $fake_fields_source_text;
            $config_source = Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
            $config_source->set('processor_settings.fakefields_index_fake_fields.fake_fields_source', $this->configuration['fake_fields_source']);
            $config_source->save();
            Drupal::logger('typed_labeled_fields')->info('Updated Machine names list of Drupal fields with the type typed_labeled_text_short');
          }
        }
      }
    }
    // return;.
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
  {
    $form['#cache']['max-age'] = 0;
    $form['#description'] = t('If this area is empty fails to compile fields, check that the "Content Type" has at least one field of the type "typed_labeled_text_short".');

    $form['fake_fields_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prefix used for fake fields. SAVE is required before compiling.'),
      '#description' => $this->t('Feel free to use the default value, but if you change it you will need to save the configuration before compiling. This field can be left blank.'),
      '#default_value' => $this->configuration['fake_fields_prefix'],
    ];

    $form['fake_fields_source'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Machine names of the Drupal fields of the type "typed_labeled_text_short". These fields should be of type "Typed Labeled Text (plain)" or "Typed Labeled Text (plain, long)". '),
      '#description' => $this->t('These should automatically pull in once when compiling Solr field names. Click button to compile.'),
      '#default_value' => $this->configuration['fake_fields_source'],
    ];

    // Validate form to remove blank lines.
    $form['fake_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('List of Solr field names generated by Identifier Types + Label. Prefix_Label . For example; isbn_furiously_happy . '),
      '#description' => $this->t('These should automatically pull in once when compiling Solr field names. Click button to compile.'),
      '#default_value' => $this->configuration['fake_fields'],
    ];

    // Validate form to remove blank lines.
    $form['fake_fields_fetch'] = [
      '#type' => 'submit',
      '#value' => t('Compile Solr field names'),
      '#submit' => [[$this, 'validateForm'], [$this, 'compileFakeFields']],
    ];

    $form['resetForm'] = [
      '#type' => 'submit',
      '#value' => t('Reset'),
      '#submit' => [[$this, 'reset']],
      ];

      $form['#validate'][] = 'validateForm';

      return $form;
  }

  function reset($form) {
    try {
      $config_source = Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
      foreach ($this->defaultConfiguration() as $key => $value) {
        $config_source->set('processor_settings.fakefields_index_fake_fields.' . $key, $value);
        $form[$key] = $value;
      }
      $config_source->save();
    } catch (\Throwable $th) {
      Drupal::logger('typed_labeled_fields')->error('Error resetting fake fields processor settings.' . $th->getMessage());
    }
  }
  function validateForm($form, FormStateInterface $form_state) {
    $config_source = Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
    $fake_fields_source = $form_state->getValue('fake_fields_source');
    $fake_fields_source = preg_split("/\r\n|\n|\r/", $fake_fields_source);
    $fake_fields_source = array_filter($fake_fields_source);
    $form_state->setValue('fake_fields_source', implode("\n", $fake_fields_source));
    $this->configuration['fake_fields_source'] = $form_state->getValue('fake_fields_source');
    $config_source->set('processor_settings.fakefields_index_fake_fields.fake_fields_source', $this->configuration['fake_fields_source']);

    $fake_fields = $form_state->getValue('fake_fields');
    $fake_fields = preg_split("/\r\n|\n|\r/", $fake_fields);
    $fake_fields = array_filter($fake_fields);
    $form_state->setValue('fake_fields', implode("\n", $fake_fields));
    $this->configuration['fake_fields'] = $form_state->getValue('fake_fields');
    $config_source->set('processor_settings.fakefields_index_fake_fields.fake_fields', $this->configuration['fake_fields']);

    $config_source->save();
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

  /**
   * @param $fake_fields_source_value_each
   * @return array|string|string[]
   * @throws Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createDynamicLabel($fake_fields_source_value_each)
  {
    $term_name = Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($fake_fields_source_value_each['type_target_id'])->getName();
    $prefix_field = $this->configuration['fake_fields_prefix'];
    // Get the optional user submitted "Label" for the
    // Islandora Object's identifier type.
    $temp_label = $term_name . '_' . $fake_fields_source_value_each['label'];
    // Concatenate this into a single String to Process
    // as Solr field machine Name.
    $new_fake_fields_label = $fake_fields_source_value_each['label'] ? $temp_label : $term_name;

    // Sanitized solr field name.
    $new_fake_fields_label = Drupal::transliteration()->transliterate(t('@prefix_@newFakeFieldsLabel', ['@prefix' => rtrim($prefix_field, '_'), '@newFakeFieldsLabel' => $new_fake_fields_label]), LanguageInterface::LANGCODE_DEFAULT, '_');
    $new_fake_fields_label = mb_strtolower($new_fake_fields_label);
    $new_fake_fields_label = preg_replace('@[^a-z0-9_]+@', '_', $new_fake_fields_label);
    return str_replace(".", "_", $new_fake_fields_label);
  }

}
