<?php

/**
 * MeiliSearch Queue Grid Container
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Block_Adminhtml_Queue extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'meilisearch_search';
        $this->_controller = 'adminhtml_queue';
        $this->_headerText = Mage::helper('meilisearch_search')->__('MeiliSearch Indexing Queue');

        parent::__construct();

        $this->_removeButton('add');

        // Add custom buttons
        $this->_addButton('run_queue', [
            'label'     => Mage::helper('meilisearch_search')->__('Process Queue Now'),
            'onclick'   => "setLocation('{$this->getUrl('*/*/run')}')",
            'class'     => 'add',
        ]);

        $this->_addButton('clear_queue', [
            'label'     => Mage::helper('meilisearch_search')->__('Clear Queue'),
            'onclick'   => "confirmSetLocation('" . Mage::helper('meilisearch_search')->__('Are you sure you want to clear the entire queue?') . "', '{$this->getUrl('*/*/clear')}')",
            'class'     => 'delete',
        ]);

        // Add queue statistics
        $this->_addQueueStats();
    }

    /**
     * Add queue statistics to the header
     */
    protected function _addQueueStats()
    {
        $queueStats = $this->_getQueueStats();

        if ($queueStats) {
            $statsHtml = '<div class="queue-stats" style="margin: 10px 0; padding: 10px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">';
            $statsHtml .= '<strong>' . $this->__('Queue Statistics:') . '</strong> ';
            $statsHtml .= $this->__('Total: %s', '<span style="font-weight: bold;">' . $queueStats['total'] . '</span>') . ' | ';
            $statsHtml .= $this->__('Pending: %s', '<span style="color: #1976d2;">' . $queueStats['pending'] . '</span>') . ' | ';
            $statsHtml .= $this->__('Failed: %s', '<span style="color: #d32f2f;">' . $queueStats['failed'] . '</span>') . ' | ';
            $statsHtml .= $this->__('Processing: %s', '<span style="color: #ff9800;">' . $queueStats['processing'] . '</span>');

            if ($queueStats['oldest']) {
                $statsHtml .= ' | ' . $this->__('Oldest: %s', '<span style="color: #666;">' . $this->_formatAge($queueStats['oldest']) . '</span>');
            }

            $statsHtml .= '</div>';

            $this->_headerText .= $statsHtml;
        }
    }

    /**
     * Get queue statistics
     */
    protected function _getQueueStats()
    {
        try {
            /** @var Mage_Core_Model_Resource $resource */
            $resource = Mage::getSingleton('core/resource');
            $readConnection = $resource->getConnection('core_read');
            $tableName = $resource->getTableName('meilisearch_search/queue');

            // Get total count
            $totalCount = (int) $readConnection->fetchOne("SELECT COUNT(*) FROM {$tableName}");

            $stats = [
                'total' => $totalCount,
                'pending' => 0,
                'failed' => 0,
                'processing' => 0,
                'oldest' => null,
            ];

            if ($totalCount > 0) {
                // Get counts by retry status
                $sql = "SELECT 
                    CASE 
                        WHEN retries >= 3 THEN 'failed' 
                        WHEN retries > 0 THEN 'processing' 
                        ELSE 'pending' 
                    END as status,
                    COUNT(*) as count
                FROM {$tableName}
                GROUP BY status";

                $results = $readConnection->fetchAll($sql);
                foreach ($results as $row) {
                    $stats[$row['status']] = (int) $row['count'];
                }

                // Get oldest item
                $oldestSql = "SELECT created_at FROM {$tableName} ORDER BY created_at ASC LIMIT 1";
                $oldest = $readConnection->fetchOne($oldestSql);
                if ($oldest) {
                    $stats['oldest'] = $oldest;
                }
            }

            return $stats;
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }

    /**
     * Format age of queue item
     */
    protected function _formatAge($datetime)
    {
        $timestamp = strtotime((string) $datetime);
        $age = time() - $timestamp;

        if ($age < 60) {
            return $this->__('%d seconds ago', $age);
        } elseif ($age < 3600) {
            return $this->__('%d minutes ago', round($age / 60));
        } elseif ($age < 86400) {
            return $this->__('%d hours ago', round($age / 3600));
        } else {
            return $this->__('%d days ago', round($age / 86400));
        }
    }
}
