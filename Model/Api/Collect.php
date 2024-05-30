<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Api;

use Kiliba\Connector\Api\Module\CollectInterface;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Model\Import\Visit;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Model\Import\FormatterResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;

class Collect extends AbstractApiAction implements CollectInterface
{
    const PARAM_MODEL = "model";
    const PARAM_LIMIT = "limit";
    const PARAM_OFFSET = "offset";
    const PARAM_CREATED_AT = "dateAdd";
    const PARAM_UPDATED_AT = "dateUpdate";

    /**
     * @var FormatterResolver
     */
    protected $_formatterResolver;

    /**
     * @var Emulation
     */
    protected $_emulation;

    /**
     * @var FormatterHelper
     */
    protected $_formatterHelper;

    public function __construct(
        RequestInterface $request,
        ResourceConnection $resourceConnection,
        ConfigHelper $configHelper,
        KilibaLogger $kilibaLogger,
        Visit $visitManager,
        DeletedItem $deletedItemManager,
        FormatterResolver $formatterResolver,
        Emulation $emulation,
        FormatterHelper $formatterHelper
    ) {
        parent::__construct($request, $resourceConnection, $configHelper, $kilibaLogger,$visitManager,$deletedItemManager);
        $this->_formatterResolver = $formatterResolver;
        $this->_emulation = $emulation;
        $this->_formatterHelper = $formatterHelper;
    }



    public function pull($withData)
    {
        $requestCheck = $this->_checkRequest();
        if(!$requestCheck["success"]) {
            return array($requestCheck);
        }

        $websiteId = $requestCheck["websiteId"];
        
        $website = $this->_configHelper->getWebsiteById($websiteId);
        $store = $website->getDefaultStore();
        $this->_emulation->startEnvironmentEmulation($store->getId(), 'frontend');

        $model = $this->_request->getParam(self::PARAM_MODEL);
        $limit = $this->_request->getParam(self::PARAM_LIMIT);
        $offset = $this->_request->getParam(self::PARAM_OFFSET);
        $createdAt = $this->_request->getParam(self::PARAM_CREATED_AT);
        $updatedAt = $this->_request->getParam(self::PARAM_UPDATED_AT);
        if(null !== $this->_request->getParam("debug")){
            error_reporting(-1);
        }

        
        $authorizedModels=['deleted_customer','deleted_product','product','visit','customer','country','order','quote','category','priceRule','coupon','deleted_priceRule'];
        if (!in_array($model, $authorizedModels)) {
            $result = ["success" => false, "error" => "Unauthorized model"];
            return [$result];
        }

        if (empty($model) || empty($limit) || $offset == "") {
            $this->logOnMissingParam("'".self::PARAM_MODEL."' or/and '"
                .self::PARAM_LIMIT."' or/and '".self::PARAM_OFFSET."'");
            $result = ["success" => false, "error" => "Error on missing model or limit or offset"];
            return [$result];
        }

       

        /*if ($model == "customer") {
            // customer is actually filtered by website
            $websiteId = $this->_formatterHelper->getWebsiteIdFromStore($websiteId);
        }*/

        if ($model == "visit") {
            $updatedAt = null; // no updated_at on visit, empty it to prevent error
        }

        try {
            switch ($model) {
                case "deleted_customer":
                    $formatterModel = $this->_formatterResolver->get("deleted");
                    $modelDatas = $formatterModel->getDeletedCollection(
                        "customer",
                        $websiteId,
                        $limit,
                        $offset,
                        $this->timestampToDate($createdAt),
                        $withData
                    );
                    $totalCount = $formatterModel->getTotalCount($websiteId);
                    break;
                case "deleted_product":
                    $formatterModel = $this->_formatterResolver->get("deleted");
                    $modelDatas = $formatterModel->getDeletedCollection(
                        "product",
                        $websiteId,
                        $limit,
                        $offset,
                        $this->timestampToDate($createdAt),
                        $withData
                    );
                    $totalCount = $formatterModel->getTotalCount($websiteId);
                    break;
                case "deleted_priceRule":
                    $formatterModel = $this->_formatterResolver->get("deleted");
                    $modelDatas = $formatterModel->getDeletedCollection(
                        "priceRule",
                        $websiteId,
                        $limit,
                        $offset,
                        $this->timestampToDate($createdAt),
                        $withData
                    );
                    $totalCount = $formatterModel->getTotalCount($websiteId);
                    break;
                default:
                    $formatterModel = $this->_formatterResolver->get($model);
                    $modelDatas = $formatterModel->getSyncCollection(
                        $websiteId,
                        $limit,
                        $offset,
                        $this->timestampToDate($createdAt),
                        $this->timestampToDate($updatedAt),
                        $withData
                    );
                    $totalCount = $formatterModel->getTotalCount($websiteId);
                    break;
            }
        } catch (NoSuchEntityException $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "pullData",
                $e->getMessage(),
                $websiteId
            );
            $result = ["success" => false, "error" => "Error on pull"];
            return [$result];
        }

        $this->_emulation->stopEnvironmentEmulation();

        return [[
            "results" => $modelDatas,
            "memory_usage" => memory_get_peak_usage(),
            "memory_get_usage_alloc" => memory_get_usage(true),
            "total_size" => $totalCount,
            "model" => $model,
            "limit" => $limit,
            "offset" => $offset
        ]];
    }

    public function pullDatas()
    {
        return $this->pull(true);
    }

    public function pullIds()
    {
        return $this->pull(false);
    }

    protected function timestampToDate($timestamp) {
        try {
            if (!empty($timestamp)) {
                return date("Y-m-d H:i:s", $timestamp);
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "PullData/ids",
                "incorrect timestamp format : " . $timestamp,
                0
            );
        }
        return null;
    }
}
