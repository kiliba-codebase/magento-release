<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper;

use Kiliba\Connector\Api\Data\LogInterface;
use Kiliba\Connector\Api\Data\LogInterfaceFactory;
use Kiliba\Connector\Api\LogRepositoryInterface;
use Kiliba\Connector\Model\Config\Source\LogLevel;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class KilibaLogger extends \Magento\Framework\App\Helper\AbstractHelper
{
    const LOG_TABLE = "kiliba_connector_log";

    const LOG_TYPE_INFO = "info";
    const LOG_TYPE_WARNING = "warning";
    const LOG_TYPE_ERROR = "error";

    const AVAILABLE_LOG_TYPES = [
        self::LOG_TYPE_INFO,
        self::LOG_TYPE_WARNING,
        self::LOG_TYPE_ERROR
    ];

    const PROCESS_REMOVE_LOG = "clearLog";
  
    const PROCESS_CHECK_FORM = "checkForm";
    const PROCESS_FIRST_SYNC = "firstSync";
    const SCOPE_GLOBAL= 0;

    /**
     * @var LogInterfaceFactory
     */
    protected $_dataLogFactory;

    /**
     * @var LogRepositoryInterface
     */
    protected $_logRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    /**
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    public function __construct(
        Context $context,
        LogInterfaceFactory $dataLogFactory,
        LogRepositoryInterface $logRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context);
        $this->_dataLogFactory = $dataLogFactory;
        $this->_logRepository = $logRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_resourceConnection = $resourceConnection;
        $this->_configHelper = $configHelper;
    }

    /**
     * @param string $type
     * @param string $process
     * @param string $message
     * @param int|null $websiteId
     * @return bool true on success
     */
    public function addLog($type, $process, $message, $websiteId = null)
    {
        switch ($this->_configHelper->getLogLevelToApply()) {
            case LogLevel::NO_LOG:
                return true;
                break;
            case LogLevel::ONLY_ERROR:
                if ($type != self::LOG_TYPE_ERROR) {
                    return true;
                }
                break;
        }

        /** @var \Kiliba\Connector\Api\Data\LogInterface $log */
        $log = $this->_dataLogFactory->create();
        $log
            ->setType($type)
            ->setProcess($process)
            ->setMessage($message)
            ->setStoreId(self::SCOPE_GLOBAL);

        try {
            $this->_logRepository->save($log);
        } catch (LocalizedException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param int $websiteId
     * @return array|LogInterface[]
     */
    public function getStoreLog($websiteId = null, $pageSize = null, $page = 1, $type = null)
    {
        if (!empty($type) && in_array($type, self::AVAILABLE_LOG_TYPES)) {
            $this->_searchCriteriaBuilder->addFilter(LogInterface::TYPE, $type);
        }

        $this->_searchCriteriaBuilder->setPageSize($pageSize);
        if (!is_numeric($page)) {
            $page = 1;
        }
        $this->_searchCriteriaBuilder->setCurrentPage($page);

        try {
            return $this->_logRepository->getList($this->_searchCriteriaBuilder->create())->getItems();
        } catch (LocalizedException $e) {
            return [];
        }
    }

    /**
     * @param int|null $storeId
     * @param string|null $beforeDate
     * @return int
     */
    public function removeOldLog($storeId = null, $beforeDate = null)
    {
        if (empty($beforeDate)) {
            $beforeDate = date('Y-m-d H:i:s');
        }

        $connection = $this->_resourceConnection->getConnection();
        $logTable = $this->_resourceConnection->getTableName(self::LOG_TABLE);

        $bind["date <= ?"] = $beforeDate;

        $nbRemoved = $connection->delete(
            $logTable,
            $bind
        );

        if ($nbRemoved > 0) {
            $this->addLog(
                self::LOG_TYPE_INFO,
                self::PROCESS_REMOVE_LOG,
                "Delete logs before " . $beforeDate,
                $storeId
            );
        }

        return $nbRemoved;
    }
}
