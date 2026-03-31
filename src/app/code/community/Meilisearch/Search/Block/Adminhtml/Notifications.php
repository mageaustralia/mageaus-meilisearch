<?php

class Meilisearch_Search_Block_Adminhtml_Notifications extends Mage_Adminhtml_Block_Template
{
    protected $_queueInfo;

    public function getConfigurationUrl()
    {
        return $this->getUrl('adminhtml/system_config/edit/section/meilisearch');
    }

    public function showNotification()
    {
        /** @var Meilisearch_Search_Helper_Config $config */
        $config = Mage::helper('meilisearch_search/config');

        return $config->showQueueNotificiation();
    }

    public function getQueueInfo()
    {
        if (isset($this->_queueInfo)) {
            return $this->_queueInfo;
        }

        /** @var Meilisearch_Search_Helper_Config $config */
        $config = Mage::helper('meilisearch_search/config');

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $tableName = $resource->getTableName('meilisearch_search/queue');

        $readConnection = $resource->getConnection('core_read');

        $size = (int) $readConnection->query('SELECT COUNT(*) as total_count FROM ' . $tableName)->fetchColumn(0);
        $maxJobsPerSingleRun = $config->getNumberOfJobToRun();

        $etaMinutes = $maxJobsPerSingleRun > 0 ? ceil($size / $maxJobsPerSingleRun) * 5 : 0; // 5 - assuming the queue runner runs every 5 minutes

        $eta = $etaMinutes . ' minutes';
        if ($etaMinutes > 60) {
            $hours = floor($etaMinutes / 60);
            $restMinutes = $etaMinutes % 60;

            $eta = $hours . ' hours ' . $restMinutes . ' minutes';
        }

        $queueInfo = [
            'isEnabled' => $config->isQueueActive(),
            'currentSize' => $size,
            'eta' => $eta,
        ];

        $this->_queueInfo = $queueInfo;

        return $this->_queueInfo;
    }

    /**
     * Show notification based on condition
     *
     * @return bool
     */
    #[\Override]
    protected function _toHtml()
    {
        // Temporarily disabled - return empty string to avoid notifications
        return '';

        $queueInfo = $this->getQueueInfo();
        if ($this->showNotification()
            && $queueInfo['isEnabled'] === true
            && $queueInfo['currentSize'] > 0) {
            return parent::_toHtml();
        }

        return '';
    }
}
