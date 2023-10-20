<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;

class AbstractModel
{


    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * @var FormatterHelper
     */
    protected $_formatterHelper;

    /**
     * @var KilibaCaller
     */
    protected $_kilibaCaller;

    /**
     * @var KilibaLogger
     */
    protected $_kilibaLogger;

    /**
     * @var SerializerInterface
     */
    protected $_serializer;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriterieBuilder;

    /**
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    // overrided in child
    protected $_coreTable = "store";
    protected $_filterScope = true;

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection
    ) {
        $this->_configHelper = $configHelper;
        $this->_formatterHelper = $formatterHelper;
        $this->_kilibaCaller = $kilibaCaller;
        $this->_kilibaLogger = $kilibaLogger;
        $this->_serializer = $serializer;
        $this->_searchCriterieBuilder = $searchCriteriaBuilder;
        $this->_resourceConnection = $resourceConnection;
    }


    protected function getModelCollection($searchCriteria, $storeId) {
        // implemented in child
        return "";
    }

    protected function prepareDataForApi($collection, $websiteId) {
        // implemented in child
        return [];
    }

    public function getTotalCount($websiteId) {
        $storeIds = $this->_configHelper->getWebsiteById($websiteId)->getStoreIds();

        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName($this->_coreTable);

        $select = $connection->select()
            ->from(
                $table,
                [new \Zend_Db_Expr('COUNT(*)')]
            );

        if ($this->_filterScope) {
            $select->where(
                "store_id IN (?)",
                $storeIds
            );
        }

        return $connection->fetchOne($select);
    }

    public function getSyncCollection($websiteId, $limit, $offset, $createdAt = null, $updatedAt = null, $withData = true) {
        $currentPage = intdiv($offset, $limit) + 1;

        $searchCriteria = $this->_searchCriterieBuilder
            ->setPageSize($limit)
            ->setCurrentPage($currentPage);

        if (!empty($createdAt)) {
            $searchCriteria->addFilter("created_at", $createdAt, "gteq");
        }

        if (!empty($updatedAt)) {
            $searchCriteria->addFilter("updated_at", $updatedAt, "gteq");
        }

        $collection = $this->getModelCollection($searchCriteria, $websiteId);

        if ($withData) {
            return $this->prepareDataForApi($collection, $websiteId);
        }

        $ids = [];
        foreach ($collection as $item) {
            $ids[] = $item->getId();
        }

        return $ids;
    }
    /**
     * @return false|string
     */
    public function getSchema()
    {
        // implemented in child
        $schema = [

        ];

        return $this->_serializer->serialize($schema);
    }

    /**
     * @param string|int|float $price
     * @return string
     */
    protected function _formatPrice($price)
    {
        return (string) number_format($price, 4, ".", "");
    }

    /**
     * @param bool $boolean
     * @return string
     */
    protected function _formatBoolean($boolean)
    {
        $boolean ? $return = "1" : $return = "0";
        return $return;
    }
}
