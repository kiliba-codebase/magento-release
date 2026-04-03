<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Api;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Model\Import\Visit;

class AbstractApiAction
{
    const ERROR_CODE_MISSING_PARAM              = 1;
    const ERROR_CODE_WRONG_TOKEN                = 2;
    const ERROR_CODE_WRONG_CONFIG_NAME          = 3;
    const ERROR_CODE_NOT_KILIBA_WEBSITE         = 4;
    const ERROR_CODE_INVALID_CRON_FORMAT        = 5;
    const ERROR_CODE_WRONG_ENTITY_TYPE          = 6;
    const ERROR_CODE_WRONG_ENTITY_ID            = 7;
    const ERROR_CODE_FIRST_SYNC_NOT_DONE        = 8;
    const ERROR_CODE_WRONG_MODEL                = 9;
    const ERROR_CODE_RESYNC_ALREADY_IN_PROGRESS = 10;
    const ERROR_CODE_DISCOUNT_DOESNT_EXIST      = 11;
    const ERROR_CODE_CANNOT_DELETE_DISCOUNT     = 12;
    const ERROR_CODE_CANNOT_CREATE_DISCOUNT     = 13;
    const ERROR_CODE_CRON_NOT_FOUND             = 14;
    const ERROR_CODE_PULL_DATA                  = 15;

    const STATUS_SUCCESS                = 200;
    const STATUS_ERROR                  = 500;
    const STATUS_DISCOUNT_ALREADY_EXIST = 409;
    const STATUS_DISCOUNT_CREATED       = 201;

    const MAX_BATCH_DATA = 40;

    /**
     * @var Visit
     */
    protected $_visitManager;

    /**
     * @var DeletedItem
     */
    protected $_deletedItemManager;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    // store data
    /**
     * @var false|string
     */
    protected $_websiteId;

    /**
     * @var KilibaLogger
     */
    protected $_kilibaLogger;

    public function __construct(
        RequestInterface $request,
        ResourceConnection $resourceConnection,
        ConfigHelper $configHelper,
        KilibaLogger $kilibaLogger,
        Visit $visitManager,
        DeletedItem $deletedItemManager
    ) {
        $this->_request = $request;
        $this->_configHelper = $configHelper;
        $this->_kilibaLogger = $kilibaLogger;
        $this->_visitManager = $visitManager;
        $this->_deletedItemManager = $deletedItemManager;
    }

    /**
     * Check current request for correct account ID and token
     * @return array
     */
    protected function _checkRequest()
    {
        ini_set('memory_limit', '2G'); // see if add

        $accountId = $this->_request->getParam("accountId");
        $token = $this->_request->getParam("token");

        $websiteCheck = $this->_checkWebsite($accountId);
        if(!$websiteCheck["success"]) {
            return $websiteCheck;
        }

        $websiteId = $websiteCheck["websiteId"];
        $tokenCheck = $this->_checkToken($websiteId, $token);
        if(!$tokenCheck["success"]) {
            return $tokenCheck;
        }

        return array(
            "success" => true,
            "accountId" => $accountId,
            "token" => $token,
            "websiteId" => $websiteId
        );
    }

    /**
     * Return website ID for given accountId
     * @param string $accountId
     * @return array
     */
    protected function _checkWebsite($accountId)
    {
        if (empty($accountId)) {
            $this->logOnMissingParam("'accountId'");
            return array(
                "success" => false,
                "code" => self::ERROR_CODE_MISSING_PARAM
            );
        }

        $websiteId = $this->_checkIfAccountIdLikedToMagento($accountId);
        if ($websiteId === false) {
            return array(
                "success" => false,
                "code" => self::ERROR_CODE_NOT_KILIBA_WEBSITE
            );
        }

        return array(
            "success" => true,
            "websiteId" => $websiteId
        );
    }

    /**
     * Check auth token for current Website
     * @return array
     */
    protected function _checkToken($websiteId, $token)
    {
        ini_set('memory_limit', '2G'); // see if add

        if(empty($token)) {
            $this->logOnMissingParam("'token'");
            return array(
                "success" => false,
                "code" => self::ERROR_CODE_MISSING_PARAM
            );
        }

        $websiteToken = $this->_configHelper->getConfigWithoutCache(ConfigHelper::XML_PATH_FLUX_TOKEN, $websiteId);
        $legacyToken = $this->_configHelper->getConfigWithoutCache(ConfigHelper::XML_PATH_FLUX_TOKEN);

        if(
            empty($websiteToken)
                // Legacy (check global scope token)
                ? $token !== $legacyToken
                // 2.2.6 (check website scoped token)
                : $token !== $websiteToken
        ) {
            return array(
                "success" => false,
                "code" => self::ERROR_CODE_WRONG_TOKEN
            );
        }

        return array(
            "success" => true
        );
    }

    /**
     * @param string $accountId
     * @return false|string
     */
    protected function _checkIfAccountIdLikedToMagento($accountId)
    {
        if (!isset($this->_websiteId)) {
            $this->_websiteId = $this->_configHelper->checkIfAccountIdLikedToMagento($accountId);
        }
        return $this->_websiteId;
    }

    protected function logOnMissingParam($param, $context = null)
    {
        $this->_kilibaLogger->addLog(
            KilibaLogger::LOG_TYPE_ERROR,
            "Missing required param in API call",
            "required param $param is/are missing in api call $context"
        );
    }
}
