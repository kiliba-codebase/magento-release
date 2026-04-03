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
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;

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

    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var UploaderFactory
     */
    protected $_uploaderFactory;

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
        StoreRepository $storeRepository,
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        UploaderFactory $uploaderFactory
    ) {
        parent::__construct($request, $resourceConnection, $configHelper, $kilibaLogger, $visitManager, $deletedItemManager);
        $this->_deploymentConfig = $deploymentConfig;
        $this->_componentRegistrar = $componentRegistrar;
        $this->_readFactory = $readFactory;
        $this->_serializer = $serializer;
        $this->_productMetadata = $productMetadata;
        $this->_storeRepository = $storeRepository;
        $this->_logo = $logo;
        $this->_filesystem = $filesystem;
        $this->_storeManager = $storeManager;
        $this->_uploaderFactory = $uploaderFactory;
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

    /**
     * {@inheritdoc}
     */
    public function uploadImage()
    {
        try {
            $requestCheck = $this->_checkRequest();
            if(!$requestCheck["success"]) {
                return array($requestCheck);
            }

            // Get the uploaded file
            $files = $this->_request->getFiles()->toArray();
            if (!isset($files['image'])) {
                return array([
                    'success' => false,
                    'message' => 'No image file provided. Please send the file with parameter name "image".'
                ]);
            }

            $fileData = $files['image'];

            // Validate file upload
            if (!isset($fileData['tmp_name']) || empty($fileData['tmp_name'])) {
                return array([
                    'success' => false,
                    'message' => 'File upload failed or no file selected.'
                ]);
            }

            // Create uploader instance
            $uploader = $this->_uploaderFactory->create(['fileId' => 'image']);
            
            // Set allowed file extensions and validate
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png', 'svg', 'webp']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);
            
            // Add unique prefix to filename to prevent conflicts
            $originalName = $fileData['name'];
            $fileInfo = pathinfo($originalName);
            $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
            $baseName = isset($fileInfo['filename']) ? $fileInfo['filename'] : 'image';
            $uniqueFileName = uniqid() . '_' . $baseName . $extension;
            
            // Rename the file with unique prefix
            $uploader->setAllowRenameFiles(false);

            // Get media directory path
            $mediaDirectory = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $targetPath = $mediaDirectory->getAbsolutePath('kiliba/images/');

            // Create directory if it doesn't exist
            $mediaWriter = $this->_filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            if (!$mediaWriter->isExist('kiliba/images/')) {
                $mediaWriter->create('kiliba/images/');
            }

            // Upload file with unique filename
            $result = $uploader->save($targetPath, $uniqueFileName);

            if (!$result) {
                return array([
                    'success' => false,
                    'message' => 'File upload failed.'
                ]);
            }

            // Get URLs
            $store = $this->_storeManager->getStore();
            $mediaUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            
            $fileName = $result['file'];
            $relativePath = '/media/kiliba/images/' . $fileName;
            $absoluteUrl = $mediaUrl . 'kiliba/images/' . $fileName;

            return array([
                'success' => true,
                'message' => 'Image uploaded successfully.',
                'file_name' => $fileName,
                'relative_path' => $relativePath,
                'absolute_url' => $absoluteUrl
            ]);

        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                "Image upload error: " . $e->getMessage(),
                \Monolog\Logger::ERROR
            );
            
            return array([
                'success' => false,
                'message' => 'Error uploading image: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setConfig()
    {
        $requestCheck = $this->_checkRequest();
        if(!$requestCheck["success"]) {
            return array($requestCheck);
        }

        $websiteId = $requestCheck["websiteId"];
        $params = $this->_request->getParams();

        $results = array();
        $errors = array();

        // Process each configuration parameter
        foreach ($params as $key => $value) {
            // Skip authentication parameters
            if (in_array($key, array('accountId', 'token'))) {
                continue;
            }

            // Check if this config is in the allowed list
            if (!in_array($key, ConfigHelper::CONFIG_CHANGE_ALLOWED)) {
                $errors[] = array(
                    'config' => $key,
                    'message' => 'Configuration not allowed to be changed via API'
                );
                continue;
            }

            // Get the XML path for this config
            if (!isset(ConfigHelper::CONFIG_MAPPING[$key])) {
                $errors[] = array(
                    'config' => $key,
                    'message' => 'Configuration mapping not found'
                );
                continue;
            }

            $xmlPath = ConfigHelper::CONFIG_MAPPING[$key];
            // PHP 7.1 compatible null coalescing
            $scope = isset(ConfigHelper::CONFIG_SCOPE[$key]) ? ConfigHelper::CONFIG_SCOPE[$key] : ScopeInterface::SCOPE_WEBSITES;

            try {
                // For boolean values, convert to 0/1
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif (is_string($value) && strtolower($value) === 'true') {
                    $value = '1';
                } elseif (is_string($value) && strtolower($value) === 'false') {
                    $value = '0';
                }

                // Save the configuration
                $scopeId = ($scope === ScopeInterface::SCOPE_WEBSITES) ? $websiteId : 0;
                $this->_configHelper->saveDataToCoreConfig($xmlPath, $value, $scopeId);

                $results[] = array(
                    'config' => $key,
                    'success' => true,
                    'value' => $value
                );
            } catch (\Exception $e) {
                $errors[] = array(
                    'config' => $key,
                    'message' => 'Error saving configuration: ' . $e->getMessage()
                );
            }
        }

        return array(array(
            'success' => count($errors) === 0,
            'results' => $results,
            'errors' => $errors
        ));
    }
}
