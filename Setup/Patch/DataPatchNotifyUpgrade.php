<?php

namespace Kiliba\Connector\Setup\Patch;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Kiliba\Connector\Helper\ConfigHelper;

abstract class DataPatchNotifyUpgrade implements DataPatchInterface
{
    const APP_URL_PROD = 'https://backend-api.production-api.kiliba.eu';
    const ENDPOINT = '/external_api';
    const METHOD_CHECK_FROM = "/checkformmagento";

    const TIMEOUT = 3000;
    const CONNECT_TIMEOUT = 1000;

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CurlFactory
     */
    private $curlFactory;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CurlFactory $curlFactory,
        ConfigHelper $configHelper,
        WebsiteRepositoryInterface $websiteRepository
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->curlFactory = $curlFactory;
        $this->configHelper = $configHelper;
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * Get the installed version that triggers this patch
     */
    abstract protected function getInstalledVersion();

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // Get all websites
        $websites = $this->websiteRepository->getList();

        foreach ($websites as $website) {
            $websiteId = $website->getId();
            
            // Check if website has a flux token configured
            $fluxToken = $this->configHelper->getConfigWithoutCache(
                ConfigHelper::XML_PATH_FLUX_TOKEN,
                $websiteId
            );
            
            if (empty($fluxToken)) {
                continue;
            }

            $website = $this->configHelper->getWebsiteById($websiteId);
            $store = $website->getDefaultStore();

            $accountId = $this->configHelper->getClientId($websiteId);

            if (empty($accountId)) {
                continue;
            }

            $storeUrl = $store->getBaseUrl();

            $curl = $this->curlFactory->create();
            $curl->setOptions(array(
                CURLOPT_URL => self::APP_URL_PROD . self::ENDPOINT . self::METHOD_CHECK_FROM,
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
                ],
                CURLOPT_POSTFIELDS => 
                    'id_account=' . $accountId
                    . '&url_shop=' . $storeUrl
                    . '&token=' . $fluxToken
                    . '&version=' . $this->getInstalledVersion()
            ));

            $curl->post("", "{}"); // url and params already set in
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
