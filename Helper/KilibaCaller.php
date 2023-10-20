<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper;

use Kiliba\Connector\Model\Api\AbstractApiAction;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Theme\Block\Html\Header\Logo;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Store\Api\StoreRepositoryInterface as StoreRepository;
use Kiliba\Connector\Model\Import\FormatterResolver;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;

class KilibaCaller extends \Magento\Framework\App\Helper\AbstractHelper
{

    const APP_URL_PROD = 'https://backend-api.production-api.kiliba.eu';
    const APP_URL_PREPROD = 'https://backend-api.preprod-api.kiliba.eu';
    const ENDPOINT = '/external_api';

    const METHOD_CHECK_FROM = "/checkformmagento";

    const TIMEOUT = 3000;
    const CONNECT_TIMEOUT = 500;

    const ERROR_NO_ID = "error_1";

    const HTTP_SUCCESS_CODE = [
        200,
        100
    ];

    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * @var Logo
     */
    protected $_logo;

    /**
     * @var ProductMetadataInterface
     */
    protected $_productMetadata;

    /**
     * @var AssetRepository
     */
    protected $_assetRepository;

    /**
     * @var StoreRepository
     */
    protected $_storeRepository;

    /**
     * @var FormatterHelper
     */
    protected $_formatterHelper;

    /**
     * @var FormatterResolver
     */
    protected $formatterResolver;

    /**
     * @var SerializerInterface
     */
    protected $_serializer;

    /**
     * @var KilibaLogger
     */
    protected $_kilibaLogger;

    /**
     * @var CurlFactory
     */
    protected $_curlClientFactory;

    // store data
    /**
     * @var string
     */
    protected $_keySchema;

    /**
     * @var string[]
     */
    protected $_modelsSchema;

    /**
     * @var string
     */
    protected $_endpoint;


    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        Logo $logo,
        ProductMetadataInterface $productMetadata,
        AssetRepository $assetRepository,
        StoreRepository $storeRepository,
        FormatterHelper $formatterHelper,
        FormatterResolver $formatterResolver,
        SerializerInterface $serializer,
        KilibaLogger $kilibaLogger,
        CurlFactory $curlClientFactory
    ) {
        parent::__construct($context);
        $this->_configHelper = $configHelper;
        $this->_logo = $logo;
        $this->_productMetadata = $productMetadata;
        $this->_assetRepository = $assetRepository;
        $this->_storeRepository = $storeRepository;
        $this->_formatterHelper = $formatterHelper;
        $this->formatterResolver = $formatterResolver;
        $this->_serializer = $serializer;
        $this->_kilibaLogger = $kilibaLogger;
        $this->_curlClientFactory = $curlClientFactory;
        $this->_endpoint = self::APP_URL_PROD . self::ENDPOINT;
    }

    /**
     * @param int $websiteId
     * @return array|string
     */
    public function checkBeforeStartSync($websiteId)
    {
        try {
            $website = $this->_configHelper->getWebsiteById($websiteId);
            $store = $website->getDefaultStore();

            $accountId = $this->_configHelper->getClientId($websiteId);

            if (empty($accountId)) {
                return [
                    'success' => false,
                    'httpCode' => AbstractApiAction::STATUS_ERROR,
                    'response' => '',
                    'infos' => '',
                    'error' => self::ERROR_NO_ID,
                    'errorNumber' => '1',
                ];
            }

            $storeUrl = $store->getBaseUrl();
            $logoUrl = $this->_logo->getLogoSrc();

            $folderName = \Magento\Config\Model\Config\Backend\Image\Logo::UPLOAD_DIR;
            $storeLogoPath = $this->_configHelper->getConfigWithoutCache(
                'design/header/logo_src',
                $websiteId
            );

            $path = $folderName . DIRECTORY_SEPARATOR . $storeLogoPath;
            $logoUrl = $this->_urlBuilder
                    ->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]) . $path;

            if (empty($storeLogoPath)) {
                $logoUrl = $this->getViewFileUrl('images/logo.svg');
            }

            $magentoApiToken = hash('md5', rand());
            // only one token for all magento kiliba account
            if (empty($this->_configHelper->getConfigWithoutCache(ConfigHelper::XML_PATH_FLUX_TOKEN))) {
                $this->_configHelper->saveDataToCoreConfig(
                    ConfigHelper::XML_PATH_FLUX_TOKEN,
                    $magentoApiToken
                ); // will be used to call magento api
            } else {
                $magentoApiToken = $this->_configHelper->getConfigWithoutCache(ConfigHelper::XML_PATH_FLUX_TOKEN);
            }

            $storeLocale = $this->_configHelper->getStoreLocale($store->getId());

            $curl = $this->_buildCurlQuery();
            $curl->setOption(
                CURLOPT_POSTFIELDS,
                'id_account=' . $accountId
                . '&url_shop=' . $storeUrl
                . '&url_logo=' . $logoUrl
                . '&token=' . $magentoApiToken
                . '&locale=' . $storeLocale
            );
            $curl->setOption(CURLOPT_URL, $this->_endpoint . self::METHOD_CHECK_FROM);

            return $this->_formatResponse($curl, __FUNCTION__, $websiteId);
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    public function getViewFileUrl($fileId)
    {
        try {
            return $this->_assetRepository->getUrlWithParams($fileId, []);
        } catch (\Exception $e) {
            return "";
        }
    }

   


    /**
     * @return Curl
     */
    protected function _buildCurlQuery()
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Cache-Control: no-cache',
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ];

        /** @var Curl $curl */
        $curl = $this->_curlClientFactory->create();
        $curl->setOptions($options);

        return $curl;
    }


    /**
     * @param Curl $curl
     * @param string $processName
     * @param int $websiteId
     * @return array
     */
    protected function _formatResponse($curl, $processName, $websiteId)
    {
        $error = "";
        $errorNumber = 0;
        try {
            $curl->post("", "{}"); // url and params already set in

            $response = $curl->getBody();
            $infos = $curl;
            $responseCode = $curl->getStatus();

            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_INFO,
                "Call CURL " . $processName,
                $this->_serializer->serialize(["Query" => $infos]),
                $websiteId
            );

            if (is_string($response) && is_array(json_decode($response, true))) { // test if json
                $responseDecoded = $this->_serializer->unserialize($response);
            } else {
                $responseDecoded = $response;
            }

            $this->_kilibaLogger->addLog(
                (
                $response === false || !in_array($responseCode, self::HTTP_SUCCESS_CODE)
                    ? KilibaLogger::LOG_TYPE_ERROR
                    : KilibaLogger::LOG_TYPE_INFO
                ),
                "Result CURL " . $processName,
                $this->_serializer->serialize(
                    [
                        "Infos" => $infos,
                        "Error" => ($response === false ? $errorNumber . ' - ' . $error : 'None'),
                        "Response" => ($response === false ? '' : $responseDecoded),
                        "Code" => $responseCode
                    ]
                ),
                $websiteId
            );

            // fix because response http code is wrong if error happen !
            $isSuccess = true;
            if ($response === false
                || !in_array($responseCode, self::HTTP_SUCCESS_CODE)
                || !empty($responseDecoded["error_code"])
            ) {
                $isSuccess = false;
            }

            return [
                'success' => $isSuccess,
                'httpCode' => $responseCode,
                'response' => $responseDecoded,
                'infos' => $infos,
                'error' => $error,
                'errorNumber' => $errorNumber,
            ];
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Call CURL " . $processName,
                $this->_serializer->serialize(["Error" => $e->getMessage()]),
                $websiteId
            );
            $error = $e;
            $errorNumber++;
            return [
                'success' => false,
                'error' => $error,
                'httpCode' => "catched",
                'errorNumber' => $errorNumber,
            ];
        }
    }

    /**
     * @param string $model
     * @return false|string
     */
    protected function _getModelSchema($model)
    {
        if (!isset($this->_modelsSchema[$model])) {
            $formatterModel = $this->formatterResolver->get($model);
            $this->_modelsSchema[$model] = $formatterModel->getSchema();
        }

        return $this->_modelsSchema[$model];
    }
}
