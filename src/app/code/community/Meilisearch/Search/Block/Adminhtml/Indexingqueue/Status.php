<?php

class Meilisearch_Search_Block_Adminhtml_Indexingqueue_Status extends Mage_Adminhtml_Block_Template
{
    public const CRON_QUEUE_FREQUENCY = 330;

    public const QUEUE_NOT_PROCESSED_LIMIT = 3600;

    public const QUEUE_FAST_LIMIT = 220;

    /** @var Meilisearch_Search_Helper_Config */
    protected $config;

    /** @var Mage_Core_Model_Date */
    protected $dateTime;

    /** @var Meilisearch_Search_Model_Queue */
    protected $queue;

    /** @var Mage_Index_Model_Process */
    protected $queueRunnerIndexer;

    /**
     * Meilisearch_Search_Model_Indexer_Meilisearchqueuerunner
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->config = Mage::helper('meilisearch_search/config');
        $this->dateTime = Mage::getModel('core/date');
        $this->queue = Mage::getModel('meilisearch_search/queue');

        $this->queueRunnerIndexer = Mage::getModel('index/indexer')
            ->getProcessByCode(Meilisearch_Search_Model_Indexer_Meilisearchqueuerunner::INDEXER_ID);

        $this->setTemplate('meilisearch_search/queue/status.phtml');
    }

    /**
     * @return mixed
     */
    public function isQueueActive()
    {
        return $this->config->isQueueActive();
    }

    /**
     * @return string
     */
    public function getQueueRunnerStatus()
    {
        $status = 'Unknown';

        /** @var Mage_Index_Model_Process $process */
        $process = Mage::getModel('index/process');
        $statuses = $process->getStatusesOptions();

        if ($this->queueRunnerIndexer->getStatus()
            && isset($statuses[$this->queueRunnerIndexer->getStatus()])) {
            $status = $statuses[$this->queueRunnerIndexer->getStatus()];
        }

        return $status;
    }

    public function getLastQueueUpdate()
    {
        return $this->queueRunnerIndexer->getEndedAt();
    }

    /**
     * @return mixed
     */
    public function getResetQueueUrl()
    {
        return $this->getUrl('*/*/reset');
    }

    /**
     * @return array
     */
    public function getNotices()
    {
        $notices = [];

        if ($this->isQueueStuck()) {
            $notices[] = '<a href="' . $this->getResetQueueUrl() . '"> ' . $this->__('Reset Queue') . '</a>';
        }

        if ($this->isQueueNotProcessed()) {
            $notices[] =  $this->__(
                'Queue has not been processed for one hour and indexing might be stuck or your cron is not set up properly.',
            );
            $notices[] =  $this->__(
                'To help you, please read our <a href="%s" target="_blank">documentation</a>.',
                'https://docs.meilisearch.com/',
            );
        }

        if ($this->isQueueFast()) {
            $notices[] = $this->__('The average processing time of the queue has been performed under 3 minutes.');
            $notices[] = $this->__(
                'Adding more jobs in the <a href="%s">Indexing Queue configuration</a> would increase the indexing speed.',
                $this->getUrl('adminhtml/system_config/edit/section/meilisearch_search/'),
            );
        }

        return $notices;
    }

    /**
     * If the queue status is not "ready" and it is running for more than 5 minutes, we consider that the queue is stuck
     *
     * @return bool
     */
    private function isQueueStuck()
    {
        if ($this->queueRunnerIndexer->getStatus() == Mage_Index_Model_Process::STATUS_PENDING) {
            return false;
        }

        if ($this->getTimeSinceLastIndexerUpdate() > self::CRON_QUEUE_FREQUENCY) {
            return true;
        }

        return false;
    }

    /**
     * Check if the queue indexer has not been processed for more than 1 hour
     *
     * @return bool
     */
    private function isQueueNotProcessed()
    {
        return $this->getTimeSinceLastIndexerUpdate() > self::QUEUE_NOT_PROCESSED_LIMIT;
    }

    /**
     * Check if the average processing time  of the queue is fast
     *
     * @return bool
     */
    private function isQueueFast()
    {
        $averageProcessingTime = $this->queue->getAverageProcessingTime();

        return !is_null($averageProcessingTime) && $averageProcessingTime < self::QUEUE_FAST_LIMIT;
    }

    /** @return int */
    private function getIndexerLastUpdateTimestamp()
    {
        return $this->dateTime->gmtTimestamp($this->queueRunnerIndexer->getLatestUpdated());
    }

    /** @return int */
    private function getTimeSinceLastIndexerUpdate()
    {
        return $this->dateTime->gmtTimestamp('now') - $this->getIndexerLastUpdateTimestamp();
    }

    /**
     * Prepare html output
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if ($this->isQueueActive()) {
            return parent::_toHtml();
        }

        return '';
    }
}
