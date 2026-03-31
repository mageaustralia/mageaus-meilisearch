<?php

/**
 * MeiliSearch Server URL Validation
 *
 * @category    Meilisearch
 * @package     Meilisearch_Search
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 */
class Meilisearch_Search_Model_System_Config_Backend_ServerUrl extends Mage_Core_Model_Config_Data
{
    /**
     * Validate MeiliSearch server URL before save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        $value = $this->getValue();

        $helper = Mage::helper('meilisearch_search');

        if (empty($value)) {
            Mage::throwException($helper->__('MeiliSearch Server URL is required.'));
        }

        // Validate URL format
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            Mage::throwException($helper->__('Please enter a valid URL (e.g., http://localhost:7700 or https://your-meilisearch-instance.com)'));
        }

        // Check if URL uses HTTPS in production
        if (!$this->_isLocalEnvironment($value) && !str_starts_with($value, 'https://')) {
            $this->_getSession()->addWarning(
                $helper->__('⚠️ Security Warning: You should use HTTPS for production MeiliSearch instances to protect your API keys.'),
            );
        }

        // Test connection to MeiliSearch
        try {
            $this->_testConnection($value);
        } catch (Exception $e) {
            Mage::throwException($helper->__(
                'Unable to connect to MeiliSearch server at %s. Please check the URL and ensure MeiliSearch is running. Error: %s',
                $value,
                $e->getMessage(),
            ));
        }

        return parent::_beforeSave();
    }

    /**
     * Check if URL is for local development
     *
     * @param string $url
     * @return bool
     */
    protected function _isLocalEnvironment($url)
    {
        $localHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($host, $localHosts) || str_contains($host, '.local');
    }

    /**
     * Test connection to MeiliSearch server
     *
     * @param string $url
     * @throws Exception
     */
    protected function _testConnection($url)
    {
        $client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 5]);

        try {
            $response = $client->request('GET', $url . '/health');

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new Exception('Server returned status code: ' . $statusCode);
            }

            $data = json_decode($response->getContent(), true);
            if (isset($data['status']) && $data['status'] === 'available') {
                $helper = Mage::helper('meilisearch_search');
                $this->_getSession()->addSuccess(
                    $helper->__('✅ Successfully connected to MeiliSearch server!'),
                );
            }
        } catch (Exception $e) {
            throw new Exception('Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get admin session
     *
     * @return Mage_Adminhtml_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }
}
