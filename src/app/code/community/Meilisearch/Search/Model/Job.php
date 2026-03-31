<?php

class Meilisearch_Search_Model_Job extends Mage_Core_Model_Abstract
{
    public const CACHE_TAG = 'meilisearch_queue_job';

    protected $_cacheTag = 'meilisearch_queue_job';
    protected $_eventPrefix = 'meilisearch_queue_job';
    protected $_eventObject = 'queue_job';

    /**
     * Initialize resources
     */
    protected function _construct()
    {
        $this->_init('meilisearch_search/job');
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        $status = Meilisearch_Search_Model_Source_JobStatuses::STATUS_PROCESSING;

        if (is_null($this->getPid())) {
            $status = Meilisearch_Search_Model_Source_JobStatuses::STATUS_NEW;
        }

        if ((int) $this->getRetries() >= $this->getMaxRetries()) {
            $status = Meilisearch_Search_Model_Source_JobStatuses::STATUS_ERROR;
        }

        return $status;
    }

    /**
     * @return string
     */
    public function getStatusLabel()
    {
        $status = $this->getStatus();
        $labels = Mage::getModel('meilisearch_search/source_jobStatuses')->getStatuses();

        return $labels[$status] ?? $status;
    }

    /**
     * @return Meilisearch_Search_Model_Job
     */
    public function saveError(Exception $e)
    {
        $this->setErrorLog($e->getMessage());
        $this->save();

        return $this;
    }
}
