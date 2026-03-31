<?php

/**
 * Meilisearch custom sort order field.
 */
class Meilisearch_Search_Block_System_Config_Form_Field_CustomRankingProductAttributes extends Meilisearch_Search_Block_System_Config_Form_Field_AbstractField
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
                        $attributes = $product_helper->getAllAttributes();
                        foreach ($attributes as $key => $label) {
                            $options[$key] = $key ?: $label;
                        }

                        return $options;
                    },
                    'rowMethod' => 'getAttribute',
                    'width' => 200,
                ],
                'order' => [
                    'label'   => 'Asc / Desc',
                    'options' => [
                        'desc' => 'Descending',
                        'asc'  => 'Ascending',
                    ],
                    'rowMethod' => 'getOrder',
                ],
            ],
            'buttonLabel' => 'Add Ranking Criterion',
            'addAfter'    => false,
        ];

        parent::__construct();
    }

    /**
     * Add drag and drop functionality
     */
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
                var table = document.getElementById("' . $this->getHtmlId() . '");
                if (!table) return;
                
                var tbody = table.querySelector("tbody");
                if (!tbody) return;
                
                var draggedRow = null;
                
                // Add draggable attribute to all rows except the template
                var rows = tbody.querySelectorAll("tr");
                rows.forEach(function(row) {
                    if (row.id && row.id.indexOf("_add_template") === -1) {
                        row.draggable = true;
                        
                        // Add drag start handler
                        row.addEventListener("dragstart", function(e) {
                            draggedRow = this;
                            e.dataTransfer.effectAllowed = "move";
                            e.dataTransfer.setData("text/html", this.innerHTML);
                            this.style.opacity = "0.5";
                        });
                        
                        // Add drag end handler
                        row.addEventListener("dragend", function(e) {
                            this.style.opacity = "";
                            rows.forEach(function(row) {
                                row.classList.remove("drag-over");
                            });
                        });
                        
                        // Add drag over handler
                        row.addEventListener("dragover", function(e) {
                            if (e.preventDefault) {
                                e.preventDefault();
                            }
                            e.dataTransfer.dropEffect = "move";
                            
                            var thisRow = this;
                            if (thisRow !== draggedRow) {
                                thisRow.classList.add("drag-over");
                            }
                            return false;
                        });
                        
                        // Add drag leave handler
                        row.addEventListener("dragleave", function(e) {
                            this.classList.remove("drag-over");
                        });
                        
                        // Add drop handler
                        row.addEventListener("drop", function(e) {
                            if (e.stopPropagation) {
                                e.stopPropagation();
                            }
                            
                            if (draggedRow !== this) {
                                // Insert dragged row before this row
                                tbody.insertBefore(draggedRow, this);
                                
                                // Reindex all input names
                                reindexRows();
                            }
                            
                            return false;
                        });
                    }
                });
                
                // Function to reindex input names after reordering
                function reindexRows() {
                    var index = 0;
                    var rows = tbody.querySelectorAll("tr");
                    rows.forEach(function(row) {
                        if (row.id && row.id.indexOf("_add_template") === -1) {
                            var inputs = row.querySelectorAll("input, select");
                            inputs.forEach(function(input) {
                                if (input.name) {
                                    input.name = input.name.replace(/\[\d+\]/, "[" + index + "]");
                                }
                            });
                            index++;
                        }
                    });
                }
                
                // Add CSS
                var style = document.createElement("style");
                style.textContent = `
                    #' . $this->getHtmlId() . ' .drag-handle {
                        text-align: center;
                        color: #999;
                        font-size: 16px;
                        padding: 5px;
                        cursor: move;
                    }
                    #' . $this->getHtmlId() . ' tr[draggable="true"] {
                        cursor: move;
                    }
                    #' . $this->getHtmlId() . ' tr.drag-over {
                        border-top: 2px solid #3366cc;
                    }
                    #' . $this->getHtmlId() . ' tbody tr:hover .drag-handle {
                        color: #333;
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Initialize when DOM is ready
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
