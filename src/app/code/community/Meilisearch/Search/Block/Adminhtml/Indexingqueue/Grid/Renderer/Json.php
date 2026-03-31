<?php

class Meilisearch_Search_Block_Adminhtml_Indexingqueue_Grid_Renderer_Json extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @return string
     */
    #[\Override]
    public function render(Varien_Object $row)
    {
        $html = '';
        if ($json = $row->getData('data')) {
            $json = json_decode((string) $json, true);

            foreach ($json as $var => $value) {
                $html .= $var . ': ' . (is_array($value) ? implode(',', $value) : $value) . '<br/>';
            }
        }
        return $html;
    }
}
