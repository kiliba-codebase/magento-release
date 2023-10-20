<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Api;

use Kiliba\Connector\Api\Module\ConfigurationInterface;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Model\Import\Visit;
use Magento\Theme\Block\Html\Header\Logo;
use Magento\Store\Api\StoreRepositoryInterface as StoreRepository;

class Configuration extends AbstractApiAction implements ConfigurationInterface
{
    /**
     * @var ComponentRegistrarInterface
     */
    protected $_componentRegistrar;

    /**
     * @var ReadFactory
     */
    protected $_readFactory;

    /**
     * @var DeploymentConfig
     */
    protected $_deploymentConfig;

    /**
     * @var SerializerInterface
     */
    protected $_serializer;

    /**
     * @var ProductMetadataInterface
     */
    protected $_productMetadata;

    /**
     * @var StoreRepository
     */
    protected $_storeRepository;

    /**
     * @var Logo
     */
    protected $_logo;

    public function __construct(
        RequestInterface $request,
        ResourceConnection $resourceConnection,
        KilibaLogger $kilibaLogger,
        ConfigHelper $configHelper,
        Visit $visitManager,
        DeletedItem $deletedItemManager,
        DeploymentConfig $deploymentConfig,
        ComponentRegistrarInterface $componentRegistrar,
        ReadFactory $readFactory,
        SerializerInterface $serializer,
        ProductMetadataInterface $productMetadata,
        Logo $logo,
        StoreRepository $storeRepository
    ) {
        parent::__construct($request, $resourceConnection, $configHelper, $kilibaLogger, $visitManager, $deletedItemManager);
        $this->_deploymentConfig = $deploymentConfig;
        $this->_componentRegistrar = $componentRegistrar;
        $this->_readFactory = $readFactory;
        $this->_serializer = $serializer;
        $this->_productMetadata = $productMetadata;
        $this->_storeRepository = $storeRepository;
        $this->_logo = $logo;
    }


    /**
     * {@inheritdoc}
     */
    public function setConfigValue()
    {
        $result = $this->_checkToken();
        if (!$result["success"]) {
            return [$result];
        }

        $configuration = $this->_request->getParam("configuration");
        $newValue = $this->_request->getParam("value");

        if (
            !$configuration
            || (!$newValue && $newValue != "0")
        ) {
            $this->logOnMissingParam("'configuration' or/and 'value'");
            $result = ["success" => false, "code" => self::ERROR_CODE_MISSING_PARAM];
            return [$result];
        }

        if (!in_array($configuration, ConfigHelper::CONFIG_CHANGE_ALLOWED)) {
            $result = ["success" => false, "code" => self::ERROR_CODE_WRONG_CONFIG_NAME];
            return [$result];
        }


        if (ConfigHelper::CONFIG_SCOPE[$configuration] == ScopeInterface::SCOPE_WEBSITES) {
            $accountId = $this->_request->getParam("accountId");
            $return = $this->_getTargetedStore($accountId);
            if (is_array($return)) {
                return $return;
            }
            $websiteId = $return;
        } else {
            $websiteId = 0;
        }

        $this->_configHelper->saveDataToCoreConfig(
            ConfigHelper::CONFIG_MAPPING[$configuration],
            $newValue,
            $websiteId
        );

        $result = ["success" => true];
        return [$result];
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigValue()
    {
        $result = $this->_checkToken();
        if (!$result["success"]) {
            return [$result];
        }

        $configValue = [];

        $configValue["version"] = $this->_getModuleVersion();
        $configValue["magento"] = $this->_productMetadata->getVersion();
        $configValue["edition"] = $this->_productMetadata->getEdition();
        $configValue["phpversion"] = phpversion();
        $configValue["isSingleStore"] = $this->_configHelper->isSingleStore();
        $configValue["linkedWebsite"] = $this->_configHelper->getLinkedWebsite($this->_request->getParam("accountId"));
        $configValue["accountId"] = $this->_request->getParam("accountId");

        return [$configValue];
    }

    protected function _getModuleVersion()
    {
        $path = $this->_componentRegistrar->getPath(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            "Kiliba_Connector"
        );
        $directoryRead = $this->_readFactory->create($path);
        $composerJsonData = $directoryRead->readFile('composer.json');
        $data = $this->_serializer->unserialize($composerJsonData);

        return !empty($data["version"]) ? $data["version"] : "N/A";
    }
}
