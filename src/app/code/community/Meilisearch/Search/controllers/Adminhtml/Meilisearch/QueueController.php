<?php

class Meilisearch_Search_Adminhtml_Meilisearch_QueueController extends Mage_Adminhtml_Controller_Action
{
    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['truncate']);
        return parent::preDispatch();
    }

    #[\Override]
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/meilisearch_search/indexing_queue');
    }

    public function indexAction()
    {
        /** @var Meilisearch_Search_Helper_Config $config */
        $config = Mage::helper('meilisearch_search/config');

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $tableName = $resource->getTableName('meilisearch_search/queue');

        $readConnection = $resource->getConnection('core_read');

        $size = (int) $readConnection->query('SELECT COUNT(*) as total_count FROM ' . $tableName)->fetchColumn(0);
        $maxJobsPerSingleRun = $config->getNumberOfJobToRun();

        $etaMinutes = ceil($size / $maxJobsPerSingleRun) * 5; // 5 - assuming the queue runner runs every 5 minutes

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

        $this->sendResponse($queueInfo);
    }

    public function truncateAction()
    {
        try {
            /** @var Meilisearch_Search_Model_Queue $queue */
            $queue = Mage::getModel('meilisearch_search/queue');
            $queue->clearQueue(true);

            $status = ['status' => 'ok'];
        } catch (\Exception $e) {
            $status = ['status' => 'ko', 'message' => $e->getMessage()];
        }

        $this->sendResponse($status);
    }

    private function sendResponse($data)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(json_encode($data));
    }
}
