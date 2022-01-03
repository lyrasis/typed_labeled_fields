//# sourceURL=modules/contrib/typed_labeled_fields/modules/metadata/js/typed_labeled_fields.field.admin.js
/**
 * @file
 * Largely based on core/modules/blocks/js/blocks.js
 * 
 * This file allows for moving rows between two regions in a table and have the
 * 'region' field update appropriately.
 */
(function ($, window, Drupal) {

  function checkEmptyRegions(table, rowObject) {
    table.find('tr.region-message').each(function () {
      var $this = $(this);

      if ($this.prev('tr').get(0) === rowObject.element) {
        if (rowObject.method !== 'keyboard' || rowObject.direction === 'down') {
          rowObject.swap('after', this);
        }
      }

      if ($this.next('tr').is(':not(.draggable)') || $this.next('tr').length === 0) {
        $this.removeClass('region-populated').addClass('region-empty');
      } else if ($this.is('.region-empty')) {
          $this.removeClass('region-empty').addClass('region-populated');
        }
    });
  }

  function updateLastPlaced(table, rowObject) {
    table.find('.color-success').removeClass('color-success');

    var $rowObject = $(rowObject);
    if (!$rowObject.is('.drag-previous')) {
      table.find('.drag-previous').removeClass('drag-previous');
      $rowObject.addClass('drag-previous');
    }
  }

  function updateFieldWeights(table, region) {
    var weight = -Math.round(table.find('.draggable').length / 2);

    table.find('.region-' + region + '-message').nextUntil('.region-title').find('select.field-weight').val(function () {
      return ++weight;
    });
  }

  Drupal.behaviors.typedLabeledFieldsFieldAdminTables = {
    attach: function attach(context, settings) {
      if (typeof Drupal.tableDrag === 'undefined') {
        return;
      }

      settings.typed_labeled_fields.tables.forEach(id => {
        if (typeof Drupal.tableDrag[id] === 'undefined') {
          return;
        }

        $('#' + id).once('typed-labeled-fields-field-admin-table').each(function () {
          var table = $(this);
          var tableDrag = Drupal.tableDrag[id];

          tableDrag.row.prototype.onSwap = function (swappedRow) {
            checkEmptyRegions($(this.table), this);
            updateLastPlaced($(this.table), this);
          };

          tableDrag.onDrop = function () {
            var dragObject = this;
            var $rowElement = $(dragObject.rowObject.element);

            var regionRow = $rowElement.prevAll('tr.region-message').get(0);
            var regionName = regionRow.className.replace(/([^ ]+[ ]+)*region-([^ ]+)-message([ ]+[^ ]+)*/, '$2');
            var regionField = $rowElement.find('select.field-display');

            if (regionField.find('option[value=' + regionName + ']').length === 0) {
              window.alert(Drupal.t('The field cannot be placed in this region.'));
              // Reset to known good value.
              regionField.find('option:eq(0)').prop('selected', true);
              regionField.trigger('change');
            }
            else if (!regionField.is('.field-display-' + regionName)) {
              var weightField = $rowElement.find('select.field-weight');
              var oldRegionName = weightField[0].className.replace(/([^ ]+[ ]+)*field-weight-([^ ]+)([ ]+[^ ]+)*/, '$2');
              regionField.removeClass('field-display-' + oldRegionName).addClass('field-display-' + regionName);
              weightField.removeClass('field-weight-' + oldRegionName).addClass('field-weight-' + regionName);
              regionField.val(regionName);
            }

            updateFieldWeights(this.$table, regionName);

            // Trigger Ajax refresh.
            $('input[data-drupal-selector="edit-refresh"]').trigger('mousedown');
          };

          table.find('select.field-display').once('field-display').on('change', function (event) {
            var row = $(this).closest('tr');
            var select = $(this);

            tableDrag.rowObject = new tableDrag.row(row[0]);
            var regionMessage = table.find('.region-' + select[0].value + '-message');
            var regionItems = regionMessage.nextUntil('.region-message, .region-title');
            if (regionItems.length) {
              regionItems.last().after(row);
            } else {
                regionMessage.after(row);
              }
            updateFieldWeights(table, select[0].value);

            checkEmptyRegions(table, tableDrag.rowObject);

            updateLastPlaced(table, row);

            if (!tableDrag.changed) {
              $(Drupal.theme('tableDragChangedWarning')).insertBefore(tableDrag.table).hide().fadeIn('slow');
              tableDrag.changed = true;
            }

            select.trigger('blur');
          });
        });
      })
    }
  };
})(jQuery, window, Drupal);