<?php

/**
 * Meilisearch custom sort order field.
 */
class Meilisearch_Search_Block_System_Config_Form_Field_ProductAdditionalAttributes extends Meilisearch_Search_Block_System_Config_Form_Field_AbstractField
{
    public function __construct()
    {
        $this->settings = [
            'columns' => [
                'attribute' => [
                    'label'   => 'Attribute',
                    'options' => function () {
                        $options = [];

                        /** @var Meilisearch_Search_Helper_Entity_Producthelper $product_helper */
                        $product_helper = Mage::helper('meilisearch_search/entity_producthelper');

                        $searchableAttributes = $product_helper->getAllAttributes();
                        foreach ($searchableAttributes as $key => $label) {
                            $options[$key] = $key ?: $label;
                        }

                        return $options;
                    },
                    'rowMethod' => 'getAttribute',
                    'width'     => 160,
                ],
                'searchable' => [
                    'label'   => 'Searchable',
                    'options' => [
                        '1' => 'Yes',
                        '0' => 'No',
                    ],
                    'rowMethod' => 'getSearchable',
                ],
                'retrievable' => [
                    'label'   => 'Retrievable',
                    'options' => [
                        '1' => 'Yes',
                        '0' => 'No',
                    ],
                    'rowMethod' => 'getRetrievable',
                ],
                'order' => [
                    'label'   => 'Ordered',
                    'options' => [
                        'unordered' => 'Unordered',
                        'ordered'   => 'Ordered',
                    ],
                    'rowMethod' => 'getOrder',
                ],
                'index_no_value' => [
                    'label'   => 'Index empty value',
                    'options' => [
                        '1'     => 'Yes',
                        '0'     => 'No',
                    ],
                    'rowMethod' => 'getIndexNoValue',
                ],
            ],
            'buttonLabel' => 'Add Attribute',
            'addAfter'    => false,
        ];

        parent::__construct();
    }

    #[\Override]
    protected function _prepareToRender()
    {
        parent::_prepareToRender();

        // Add drag handle as the first column
        $columns = $this->_columns;
        $this->_columns = ['drag_handle' => [
            'label' => '',
            'style' => 'width:30px;',
            'class' => 'drag-handle',
            'size'  => false,
            'renderer' => false,
        ]] + $columns;
    }

    #[\Override]
    protected function _renderCellTemplate($columnName)
    {
        if ($columnName === 'drag_handle') {
            return '<span style="cursor:move;color:#999;font-size:20px;user-select:none;display:inline-block;line-height:1;">&equiv;</span>';
        }
        return parent::_renderCellTemplate($columnName);
    }

    /**
     * Add JavaScript for drag and drop
     */
    #[\Override]
    protected function _toHtml()
    {
        $html = parent::_toHtml();

        $html .= '<script type="text/javascript">
        (function() {
            function initDragAndDrop() {
                // Find the table: try ID first, then search by class within the config field
                var table = document.getElementById("' . $this->getHtmlId() . '");
                if (!table) {
                    // Fallback: find by the field wrapper
                    var field = document.getElementById("row_' . $this->getHtmlId() . '");
                    if (field) table = field.querySelector("table");
                }
                if (!table) {
                    // Last resort: find table inside the product_additional_attributes field
                    var tables = document.querySelectorAll("table");
                    for (var i = 0; i < tables.length; i++) {
                        var t = tables[i];
                        if (t.querySelector("select[name*=product_additional_attributes]")) {
                            table = t;
                            break;
                        }
                    }
                }
                if (!table) return;

                var thead = table.querySelector("thead tr");
                var tbody = table.querySelector("tbody");
                if (!thead || !tbody) return;

                // Set up drag events on rows
                var draggedRow = null;
                tbody.querySelectorAll("tr").forEach(function(row) {
                    if (row.id && row.id.indexOf("_add_template") === -1) {
                        row.draggable = true;
                        row.addEventListener("dragstart", function(e) {
                            draggedRow = this;
                            e.dataTransfer.effectAllowed = "move";
                            e.dataTransfer.setData("text/plain", "");
                            this.style.opacity = "0.4";
                        });
                        row.addEventListener("dragend", function() {
                            this.style.opacity = "";
                            tbody.querySelectorAll("tr").forEach(function(r) { r.classList.remove("drag-over"); });
                        });
                        row.addEventListener("dragover", function(e) {
                            e.preventDefault();
                            e.dataTransfer.dropEffect = "move";
                            if (this !== draggedRow) this.classList.add("drag-over");
                        });
                        row.addEventListener("dragleave", function() { this.classList.remove("drag-over"); });
                        row.addEventListener("drop", function(e) {
                            e.stopPropagation();
                            if (draggedRow && draggedRow !== this) {
                                tbody.insertBefore(draggedRow, this);
                                reindexRows();
                            }
                            return false;
                        });
                    }
                });

                function reindexRows() {
                    var index = 0;
                    tbody.querySelectorAll("tr").forEach(function(row) {
                        if (row.id && row.id.indexOf("_add_template") === -1) {
                            row.querySelectorAll("input, select").forEach(function(input) {
                                if (input.name) input.name = input.name.replace(/\[\d+\]/, "[" + index + "]");
                            });
                            index++;
                        }
                    });
                }

                // CSS
                var tableId = table.id || "meilisearch-drag-table";
                if (!table.id) table.id = tableId;
                var s = document.createElement("style");
                s.textContent = "#" + tableId + " tr.drag-over { border-top: 2px solid #3366cc; } #" + tableId + " tbody tr:hover .drag-handle { color: #333; }";
                document.head.appendChild(s);
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", initDragAndDrop);
            } else {
                initDragAndDrop();
            }
        })();
        </script>';

        return $html;
    }
}
