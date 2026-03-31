<?php

class Meilisearch_Search_Model_Source_JobStatuses
{
    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ERROR = 'error';
    public const STATUS_COMPLETE = 'complete';

    protected $_statuses = [
        self::STATUS_NEW => 'New',
        self::STATUS_ERROR => 'Error',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_COMPLETE => 'Complete',
    ];

    /**
     * @return array
     */
    public function getStatuses()
    {
        return $this->_statuses;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->_methods as $method => $label) {
            $option[] = [
                'value' => $method,
                'label' => $label,
            ];
        }
        return $options;
    }
}
