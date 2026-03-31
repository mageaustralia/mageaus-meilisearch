<?php

/**
 * MeiliSearch Configuration Status Display
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Block_System_Config_Form_Field_Status extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $html = '<div class="meilisearch-status" style="padding: 10px; border-radius: 5px; margin: 10px 0;">';

        try {
            $helper = Mage::helper('meilisearch_search/config');

            if (!$helper->isEnabledBackend()) {
                $html .= $this->_renderStatus('warning', 'Indexing is disabled', 'Enable indexing to sync your catalog with MeiliSearch');
            } elseif (!$helper->isEnabledFrontEnd()) {
                $html .= $this->_renderStatus('warning', 'Search is disabled', 'Enable search to use MeiliSearch on your storefront');
            } else {
                $serverUrl = $helper->getServerUrl();
                $apiKey = $helper->getApiKey();

                if (empty($serverUrl) || empty($apiKey)) {
                    $html .= $this->_renderStatus('error', 'Configuration incomplete', 'Please provide server URL and API key');
                } else {
                    try {
                        $meilisearchHelper = Mage::helper('meilisearch_search/meilisearchhelper');
                        $client = $meilisearchHelper->getClient();

                        if ($client) {
                            $stats = $client->stats();
                            $html .= $this->_renderStatus('success', 'Connected to MeiliSearch', '');
                            $html .= '<div style="margin-top: 10px; font-size: 13px;">';
                            $html .= '<strong>Statistics:</strong><br/>';
                            $html .= 'Database size: ' . $this->_formatBytes($stats->getDatabaseSize()) . '<br/>';
                            $html .= 'Index prefix: <code>' . $this->escapeHtml($helper->getIndexPrefix()) . '</code><br/>';
                            $html .= '</div>';
                        } else {
                            $html .= $this->_renderStatus('error', 'MeiliSearch client not initialized', 'Check server URL and API key');
                        }
                    } catch (Throwable $e) {
                        $html .= $this->_renderStatus('error', 'Connection error', $e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            $html .= $this->_renderStatus('error', 'Error', $e->getMessage());
        }

        $html .= '</div>';
        return $html;
    }

    protected function _renderStatus($type, $title, $message)
    {
        $colors = [
            'success' => '#d4edda',
            'warning' => '#fff3cd',
            'error' => '#f8d7da',
        ];

        $color = $colors[$type] ?? '#f8f9fa';

        $html = '<div style="background-color: ' . $color . '; padding: 10px; border-radius: 3px; margin-bottom: 5px;">';
        $html .= '<strong>' . $this->escapeHtml($title) . '</strong>';
        if ($message) {
            $html .= '<br/><span style="font-size: 12px;">' . $this->escapeHtml($message) . '</span>';
        }
        $html .= '</div>';

        return $html;
    }

    protected function _formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
