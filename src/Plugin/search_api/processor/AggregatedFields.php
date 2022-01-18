<?php

namespace Drupal\typed_labeled_fields\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\Property\AggregatedFieldProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Utility\Utility;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\typed_labeled_fields\Plugin\Field\FieldType\TypedLabeledTextShort;

/**
 * Adds customized aggregations of existing fields to the index.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\Property\AggregatedFieldProperty
 *
 * @SearchApiProcessor(
 *   id = "enhanced_identifier_aggregated_field",
 *   label = @Translation("Enhanced Identifier Aggregated field"),
 *   description = @Translation("Add customized aggregations of existing fields to the index. SBN, ISBN, ISBN13, etc.. NON FUNCTIONAL AT THE MOMENT."),
 *   stages = {
 *     "add_properties" = 0,
 *     "pre_index_save" = -10,
 *     "preprocess_index" = -11,
 *     "preprocess_query" = -30,
 *   },
 * )
 */

class AggregatedFields extends ProcessorPluginBase {
  protected $processor_id = 'enhanced_identifier_aggregated_field';
  
    /**
     * {@inheritdoc}
     */
    public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
      $properties = [];
      if (!$datasource) {
        $definition = [
          'label' => $this->t('Enhanced Identifier Aggregated field'),
          'description' => $this->t('Add customized aggregations of existing fields to the index. SBN, ISBN, ISBN13, etc.. NON FUNCTIONAL AT THE MOMENT.'),
          'type' => 'textfield',
          'processor_id' => $this->getPluginId(),
        ];
        $properties['enhanced_typed_labeled_fields'] = new ProcessorProperty($definition);
      }
  
      return $properties;
    }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $node = $item->getOriginalObject()->getValue();
    $field_lease = $node->get('field_identifier_types')->getValue();
    if ($field_lease[0]['value']) {
      $fields = $this->getFieldsHelper()
        ->filterForPropertyPath($item->getFields(), NULL, 'enhanced_typed_labeled_fields');
      foreach ($fields as $field) {
        $field->addValue('for lease');
      }
      \Drupal::logger('typed_labeled_fields')->notice('<pre><code>' . print_r($field_lease, TRUE) . '</code></pre>');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    foreach ($value as $key) {
      $message = dsm($key);
      \Drupal::logger('typed_labeled_fields')->notice($message);
    }
  }
}