<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Config\Backend;

use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Model\Import\FormatterResolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Message\ManagerInterface;

class LinkAccountToKiliba extends \Magento\Framework\App\Config\Value
{
    /**
     * @var KilibaCaller
     */
    protected $_kilibaCaller;

    /**
     * @var ManagerInterface 
     */
    protected $_messageManager;

    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * @var KilibaLogger
     */
    private $_kilibaLogger;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        KilibaCaller $kilibaCaller,
        ManagerInterface $messageManager,
        ConfigHelper $configHelper,
        KilibaLogger $kilibaLogger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_kilibaCaller = $kilibaCaller;
        $this->_messageManager = $messageManager;
        $this->_configHelper = $configHelper;
        $this->_kilibaLogger = $kilibaLogger;
    }

    /**
     * @return $this
     */
    public function afterSave()
    {
        $endProcess = parent::afterSave();
        $websiteId = $this->getScopeId();

        if (
            !empty($this->getValue())
            && $this->_configHelper->checkIfAccountIdLikedToMagento($this->getValue()) != $websiteId
        ) {
            $message = __("Cannot have same accountId linked to several magento store");
            $this->_messageManager->addErrorMessage($message);

            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                KilibaLogger::PROCESS_CHECK_FORM,
                $message,
                $websiteId
            );
            $this->_configHelper->saveDataToCoreConfig(
                ConfigHelper::XML_PATH_CLIENT_ID,
                $this->getOldValue(),
                $websiteId
            );
            return $endProcess;
        }

        // When single store, also save to default
        // Because Magento reads from default scope and save to websites scope
        if($this->_configHelper->isSingleStore()) {
            $this->_configHelper->saveDataToCoreConfig(
                ConfigHelper::XML_PATH_CLIENT_ID,
                $this->getValue()
            );
        }    

        if (!$this->_configHelper->isCurlInstalled()) {
            $message = "Please install curl and try later";
            $this->_messageManager->addErrorMessage($message);
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                KilibaLogger::PROCESS_FIRST_SYNC,
                $message,
                $websiteId
            );
            return $endProcess;
        }

        if ($this->getScope() === ScopeInterface::SCOPE_WEBSITES) {
            $response = $this->_kilibaCaller->checkBeforeStartSync($websiteId);

            if (!$response["success"]) {
                $this->_messageManager->addErrorMessage(
                    __("Server unavailable when launching the synchronization: ")
                        . $response['httpCode'] . ' '
                        . $response["error"]
                );
                return $endProcess;
            }

            $message = __("Success onboarding for website : %1", $this->_configHelper->getWebsiteById($websiteId)->getName());
            $this->_messageManager->addSuccessMessage($message);
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_INFO,
                KilibaLogger::PROCESS_FIRST_SYNC,
                $message,
                $websiteId
            );
        }
        return $endProcess;
    }
}
