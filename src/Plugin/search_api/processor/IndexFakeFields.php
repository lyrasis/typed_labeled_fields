<?php

namespace Drupal\typed_labeled_fields\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\Yaml\Parser;
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
  protected $fake_fields_list;

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
      $this->fake_fields_list = preg_split("/\\r\\n|\\r|\\n/", $this->configuration['fake_fields']);
      foreach ($this->fake_fields_list as $fake_field) {
        $fake_field_name = trim($fake_field);
	      $properties[$fake_field_name] = new ProcessorProperty($definition);
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

        // $fields = $item->getFields(FALSE);
        $fake_fields_source = $this->configuration['fake_fields_source'];
        if ($node->hasField($fake_fields_source)) {
            $parser = new Parser();
            $fake_fields = array();
            $fake_fields_source_value = $node->get($fake_fields_source)->getValue();
            /** $fake_fields_source_value outputs a nameless array [field_identifier_types][0]
             *  Within each is this structure.
             *  [field_identifier_types][0][label] => Happy
             *  [field_identifier_types][0][type_target_id] => 71
             *  [field_identifier_types][0][value] => 978-1250077028
             * 
             * Look up label for [field_identifier_types][0][type_target_id] 
             * [term:name] => International Standard Book Number
             * parent_type = [term:name]
             */

            if (isset($fake_fields_source_value[0]['value'])) {
                foreach ($fake_fields_source_value as $fake_fields_source_value_each) {
                  $term_name = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($fake_fields_source_value_each['type_target_id'])->getName();
                  $temp_label = $term_name . '_' . $fake_fields_source_value_each['label'];
                  $fake_fields_source_value_each_label = $fake_fields_source_value_each['label'] ? $temp_label : $term_name;
                  // Sanitized solr field name.
                  $transliterated = \Drupal::transliteration()->transliterate($fake_fields_source_value_each_label, LanguageInterface::LANGCODE_DEFAULT, '_');
                  $transliterated = mb_strtolower($transliterated);
                  $transliterated = preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);
                  $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, $transliterated);
                  // (array $fields, $datasource_id, $property_path)

                  $known_fields = preg_split("/\r\n|\n|\r/", $this->configuration['fake_fields']);
                  $faker_field = $this->configuration['fake_fields'] . PHP_EOL . $transliterated;
                  if (empty($fields)) {
                    $fake_fields[$transliterated] = $fake_fields_source_value_each['value'];
                    if (!in_array($faker_field, $known_fields)) {
                      $this->configuration['fake_fields'] = $faker_field;
                    }
                  }
                  // if (!$fields) {
                  //   ksm('Not field Found: ' . $this->configuration['fake_fields']);
                  //   // Don't add if found.
                  // }
                }
            }
        }
    // ksm('Found: ' . $this->configuration['fake_fields']);
    $config = \Drupal::configFactory()->getEditable('search_api.index.default_solr_index');
    $config->set('processor_settings.fakefields_index_fake_fields.fake_fields', t($this->configuration['fake_fields']));
    $config->save();
    ksm($config->get('processor_settings.fakefields_index_fake_fields.fake_fields'));
  } catch (\Throwable $th) {
        throw $th;
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** Remove this form entirely after moving the functionality somewhere else */
    $form['fake_fields_source'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Machine name of the Drupal fields to be processed for indexing. One per line.'),
        '#description' => $this->t('These fields should be of type "Typed Labeled Text (plain)" or "Typed Labeled Text (plain, long)".'),
        '#default_value' => $this->configuration['fake_fields_source'],
    ];

    /**
     * Replace $this->configuration['fake_fields_source'], with a dynamic value from the field.
    * Change the default value to the sanitized(field_identifier_types:label) + the 
    * sanitized(field identifier label).
    */
    $form['fake_fields'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Fake fields prefix'),
        '#description' => $this->t('Solr field prefix. "local_identifier" will result in "local_identifier_isbn"'),
        '#default_value' => $this->configuration['fake_fields'],
    ];

    /**
     * Replace this with a dynamic value from the field_identifier_types[0]:value.
     */
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
