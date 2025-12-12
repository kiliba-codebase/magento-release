<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\StoreRepositoryInterface as StoreRepository;
use Magento\Store\Api\WebsiteRepositoryInterface as WebsiteRepository;

class ConfigHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    const XML_PATH_DEBUG_MODE = "kiliba/connector/debug_mode";
    const XML_PATH_CLIENT_ID = "kiliba/connector/client_id";
    const XML_PATH_FLUX_TOKEN = "kiliba/connector/flux_token";
    const XML_PATH_LOG_LEVEL = "kiliba/connector/log_level";

    const XML_PATH_WEBHOOK_CART_ENABLED = "kiliba/connector/webhook_cart_enabled";
    const XML_PATH_WEBHOOK_ORDER_ENABLED = "kiliba/connector/webhook_order_enabled";

    const XML_PATH_POPUP_PROMOCODEFIRSTPURCHASE_CONFIGURATION = "kiliba/connector/popup_promocodefirstpurchase_configuration";
    const XML_PATH_POPUP_PROMOCODEFIRSTPURCHASE_ACTIVATION = "kiliba/connector/popup_promocodefirstpurchase_activation";

    const CONFIG_NAME_DEBUG_MODE = "TESTING_ENVIRONMENT";
    const CONFIG_NAME_CLIENT_ID = "ID_ACCOUNT_THATSOWL";
    const CONFIG_NAME_LOG_LEVEL = "LOG_LEVEL";
    const CONFIG_NAME_WEBHOOK_CART_ENABLED = "WEBHOOK_CART_ENABLED";
    const CONFIG_NAME_WEBHOOK_ORDER_ENABLED = "WEBHOOK_ORDER_ENABLED";

    const CONFIG_MAPPING = [
        self::CONFIG_NAME_CLIENT_ID => self::XML_PATH_CLIENT_ID,
        self::CONFIG_NAME_LOG_LEVEL => self::XML_PATH_LOG_LEVEL,
        self::CONFIG_NAME_WEBHOOK_CART_ENABLED => self::XML_PATH_WEBHOOK_CART_ENABLED,
        self::CONFIG_NAME_WEBHOOK_ORDER_ENABLED => self::XML_PATH_WEBHOOK_ORDER_ENABLED
    ];

    const CONFIG_SCOPE = [
        self::CONFIG_NAME_CLIENT_ID => ScopeInterface::SCOPE_WEBSITES,
        self::CONFIG_NAME_LOG_LEVEL => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        self::CONFIG_NAME_WEBHOOK_CART_ENABLED => ScopeInterface::SCOPE_WEBSITES,
        self::CONFIG_NAME_WEBHOOK_ORDER_ENABLED => ScopeInterface::SCOPE_WEBSITES,
    ];

    const CONFIG_CHANGE_ALLOWED = [
        self::CONFIG_NAME_CLIENT_ID,
        self::CONFIG_NAME_LOG_LEVEL,
        self::CONFIG_NAME_WEBHOOK_CART_ENABLED,
        self::CONFIG_NAME_WEBHOOK_ORDER_ENABLED,
    ];


    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var ConfigInterface
     */
    protected $_configInterface;

    /**
     * @var CollectionFactory
     */
    protected $_storeCollectionFactory;

    /**
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    // store data
    /**
     * @var string[]|null
     */
    protected $_clientId;

    /**
     * @var array
     */
    protected $_kilibaStores;

    /**
     * @var array
     */
    protected $_storeLocales;

    /**
     * @var string
     */
    protected $_isSingleStore;

    /**
     * @var int
     */
    protected $_singleStoreId;

    /**
     * @var int
     */
    protected $_logLevel;

    /**
     * @var StoreRepository
     */
    protected $_storeRepository;

    /**
     * @var WebsiteRepository
     */
    protected $_websiteRepository;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        ConfigInterface $configInterface,
        CollectionFactory $storeCollectionFactory,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        StoreRepository $storeRepository,
        WebsiteRepository $websiteRepository
    ) {
        parent::__construct($context);
        $this->_scopeConfig = $scopeConfig;
        $this->_configInterface = $configInterface;
        $this->_storeCollectionFactory = $storeCollectionFactory;
        $this->_resourceConnection = $resourceConnection;
        $this->_storeManager = $storeManager;
        $this->_storeRepository = $storeRepository;
        $this->_websiteRepository = $websiteRepository;
        $this->_storeLocales = array();
    }

    /**
     * @param string $path
     * @param int|null $websiteId
     * @return string
     */
    public function getValueFromCoreConfig($path, $websiteId=null)
    {
        return $this->_scopeConfig->getValue($path,  $websiteId == null ? ScopeConfigInterface::SCOPE_TYPE_DEFAULT : ScopeInterface::SCOPE_STORES,  $websiteId == null ? '0' : $websiteId);
    }

    /**
     * Get store config value from ScopeConfigInterface
     * @param string $path
     * @param int $storeId
     * @return string
     */
    public function getStoreConfig($path, $storeId) {
        return $this->_scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int $storeId
     * @return string
     */
    public function getStoreLocale($storeId)
    {
        if(!isset($this->_storeLocales[$storeId])) {
            $locale = $this->_scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
            $this->_storeLocales[$storeId] = $locale;
        }

        return $this->_storeLocales[$storeId];
    }

    public function getConfigWithoutCache($path, $websiteId=null)
    {
        $connection = $this->_resourceConnection->getConnection();
        $configTable = $this->_resourceConnection->getTableName("core_config_data");

        $bind['path'] = $path;
        $bind['scope'] = $websiteId == null ? ScopeConfigInterface::SCOPE_TYPE_DEFAULT : ScopeInterface::SCOPE_WEBSITES;
        $bind['scope_id'] = $websiteId == null ? '0' : $websiteId;

        return $connection->fetchOne(
            $connection->select()
                ->from(
                    $configTable,
                    ["value"]
                )->where(
                    "path = :path AND scope = :scope AND scope_id = :scope_id"
                ),
            $bind
        );
    }

    /**
     * @param string $path
     * @param string $value
     * @param int|null $websiteId
     * @return void
     */
    public function saveDataToCoreConfig($path, $value, $websiteId = null)
    {
        $scope = empty($websiteId) ? ScopeConfigInterface::SCOPE_TYPE_DEFAULT : ScopeInterface::SCOPE_WEBSITES;
        $scopeId = empty($websiteId) ? 0 : $websiteId;
        $this->_configInterface->saveConfig($path, $value, $scope, $scopeId);
    }

    /**
     * @param int $websiteId
     * @return string
     */
    public function getClientId($websiteId)
    {
        if (!isset($this->_clientId[$websiteId])) {
            $this->_clientId[$websiteId] = $this->getConfigWithoutCache(self::XML_PATH_CLIENT_ID, $websiteId);
        }
        return $this->_clientId[$websiteId];
    }


    /**
     * @param int $websiteId
     * @return bool
     */
    public function isStoreLinkToKiliba($websiteId)
    {
        if (!empty($this->getClientId($websiteId))) {
            return true;
        }

        return false;
    }

    public function isCurlInstalled()
    {
        return function_exists('curl_version');
    }

    /**
     * @param string $accountId
     * @return false|string
     */
    public function checkIfAccountIdLikedToMagento($accountId)
    {

        $connection = $this->_resourceConnection->getConnection();
        $configTable = $this->_resourceConnection->getTableName("core_config_data");

        $bind = [
            'path' => ConfigHelper::XML_PATH_CLIENT_ID,
            'scope' => ScopeInterface::SCOPE_WEBSITES,
            'value' => $accountId
        ];

       
        return $connection->fetchOne(
            $connection->select()
                ->from(
                    $configTable,
                    ["website_id" => "scope_id"]
                )->where(
                    "path = :path AND scope = :scope AND value = :value"
                ),
            $bind
        );
    }

    public function isSingleStore()
    {
        if (!isset($this->_isSingleStore)) {
            $this->_isSingleStore =
                $this->getValueFromCoreConfig(StoreManager::XML_PATH_SINGLE_STORE_MODE_ENABLED);
        }
        return $this->_isSingleStore;
    }

    public function getLogLevelToApply()
    {
        if (!isset($this->_logLevel)) {
            $this->_logLevel = (int) $this->getConfigWithoutCache(self::XML_PATH_LOG_LEVEL);
        }
        return $this->_logLevel;
    }

    public function getWebsiteStores($website) {
        $stores = array();
        foreach ($website->getStores() as $store) {
            $storeId = $store->getId();
            $currency = $store->getCurrentCurrency();
            $stores[] = array(
                "id" => $storeId,
                "code" => $store->getCode(),
                "name" => $store->getName(),
                "baseUrl" => $store->getBaseUrl(),
                "active" => $store->isActive(),
                "lang" => $this->getStoreLocale($storeId),
                "currency" => array(
                    "code" => $currency->getCurrencyCode(),
                    "symbol" => $currency->getCurrencySymbol()
                )
            );
        }
        return $stores;
    }

    public function getWebsiteById($websiteId) {
        return $this->_websiteRepository->getById($websiteId);
    }

    public function getStoreById($storeId) {
        return $this->_storeRepository->getById($storeId);
    }

    /**
     * Generate authentication token for Kiliba API
     * Use stronger method by default
     * Allow PHP back compatibility
     */
    public function generateToken() {
        if(function_exists("random_bytes")) {
            return bin2hex(random_bytes(32));
        } else if(function_exists("openssl_random_pseudo_bytes")) {
            return bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            return hash("md5", rand()).hash("md5", rand());
        }
    }

    /**
     * Extract lang short code from locale.
     * fr_FR -> fr / fr_LU -> fr / de_DE -> de
     */
    public function extractLangFromLocale($idLang) {
        return strpos($idLang, "_") !== false ? explode("_", $idLang)[0] : $idLang;
    }

    /**
     * Check if cart webhook is enabled (enabled by default)
     * @param int $websiteId
     * @return bool
     */
    public function isCartWebhookEnabled($websiteId = null) {
        $value = $this->getConfigWithoutCache(self::XML_PATH_WEBHOOK_CART_ENABLED, $websiteId);
        // Default to enabled (true) if not set
        return $value === null || $value === false || $value === '' || $value === '1' || $value === 1 || $value === true;
    }

    /**
     * Check if order webhook is enabled (enabled by default)
     * @param int $websiteId
     * @return bool
     */
    public function isOrderWebhookEnabled($websiteId = null) {
        $value = $this->getConfigWithoutCache(self::XML_PATH_WEBHOOK_ORDER_ENABLED, $websiteId);
        // Default to enabled (true) if not set
        return $value === null || $value === false || $value === '' || $value === '1' || $value === 1 || $value === true;
    }
}
