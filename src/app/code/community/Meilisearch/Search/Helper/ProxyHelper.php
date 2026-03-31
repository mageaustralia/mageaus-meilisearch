<?php

class Meilisearch_Search_Helper_ProxyHelper extends Mage_Core_Helper_Abstract
{
    public const PROXY_URL = 'https://magento-proxy.meilisearch.com/';
    public const PROXY_URL_PARAM_GET_INFO = 'get-info/';
    public const PROXY_URL_PARAM_POST_DATA = 'hs-push/';

    public const INFO_TYPE_EXTENSION_SUPPORT = 'extension_support';
    public const INFO_TYPE_QUERY_RULES = 'query_rules';
    public const INFO_TYPE_ANALYTICS = 'analytics';
    public const INFO_TYPE_ALL = 'all';

    /** @var Meilisearch_Search_Helper_Config */
    private $configHelper;

    private $allClientData;

    public function __construct()
    {
        $this->configHelper = Mage::helper('meilisearch_search/config');
    }

    /**
     * @param string $type
     *
     * @return string|array
     */
    public function getInfo($type)
    {
        $appId = $this->configHelper->getServerUrl();
        $apiKey = $this->configHelper->getAPIKey();

        $token = $appId . ':' . $apiKey;
        $token = base64_encode($token);
        $token = str_replace(["\n", '='], '', $token);

        $params = [
            'appId' => $appId,
            'token' => $token,
        ];

        if ($type !== self::INFO_TYPE_EXTENSION_SUPPORT) {
            $params['type'] = $type;
        }

        $info = $this->postRequest($params, self::PROXY_URL_PARAM_GET_INFO);

        if ($info) {
            $info = json_decode($info, true);
        }

        return $info;
    }

    public function getClientConfigurationData()
    {
        if (!$this->allClientData) {
            $this->allClientData = $this->getInfo(self::INFO_TYPE_ALL);
        }

        return $this->allClientData;
    }

    /**
     * @param $data
     * @param $proxyMethod
     *
     * @return bool|string
     */
    private function postRequest($data, $proxyMethod)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::PROXY_URL . $proxyMethod);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }
}
