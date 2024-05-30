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
    public function getConfigValue()
    {
        $requestCheck = $this->_checkRequest();
        if(!$requestCheck["success"]) {
            return array($requestCheck);
        }

        $accountId = $requestCheck["accountId"];
        $websiteId = $requestCheck["websiteId"];

        $website = $this->_configHelper->getWebsiteById($websiteId);
        $defaultStore = $website->getDefaultStore();
        $linkedWebsite = array(
            "id" => $website->getId(),
            "name" => $website->getName(),
            "defaultStore" => $defaultStore->getId(),
            "stores" => $this->_configHelper->getWebsiteStores($website)
        );

        $configValue = array();
        $configValue["version"] = $this->_getModuleVersion();
        $configValue["magento"] = $this->_productMetadata->getVersion();
        $configValue["edition"] = $this->_productMetadata->getEdition();
        $configValue["phpversion"] = phpversion();
        $configValue["isSingleStore"] = $this->_configHelper->isSingleStore();
        $configValue["linkedWebsite"] = $linkedWebsite;
        $configValue["accountId"] = $accountId;

        return array($configValue);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshToken() {
        $requestCheck = $this->_checkRequest();
        if(!$requestCheck["success"]) {
            return array($requestCheck);
        }

        $websiteId = $requestCheck["websiteId"];
        $newToken = $this->_configHelper->generateToken();

        $this->_configHelper->saveDataToCoreConfig(
            ConfigHelper::XML_PATH_FLUX_TOKEN,
            $newToken,
            $websiteId
        );

        return array($newToken);
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
