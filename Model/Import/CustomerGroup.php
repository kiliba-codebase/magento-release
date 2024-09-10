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
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupModel;

class CustomerGroup extends AbstractModel
{
    /**
     * @var CustomerGroupModel
     */
    protected $_customerGroup;

    protected $_coreTable = "customer_group";
    protected $_filterScope = false;

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        CustomerGroupModel $customerGroup
    ) {
        parent::__construct(
            $configHelper,
            $formatterHelper,
            $kilibaCaller,
            $kilibaLogger,
            $serializer,
            $searchCriteriaBuilder,
            $resourceConnection
        );
        $this->_customerGroup = $customerGroup;
    }

    /**
     * @param int $entityId
     * @param int $websiteId
     * @return \Magento\Customer\Model\ResourceModel\Group\Collection
     */
    public function getEntity($entityId)
    {
        // TODO ?
    }


    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $customerGroups = $this->_customerGroup->toOptionArray();
        return $customerGroups;
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $customerGroupsData = [];
        foreach ($collection as $customerGroup) {
            $customerGroupsData[] = $this->formatData($customerGroup);
        }

        return $customerGroupsData;
    }

    public function getSyncCollection($website, $limit, $offset, $createdAt = null, $updatedAt = null, $withData = true) {
        $customerGroups = $this->_customerGroup->toOptionArray();

        if ($withData) {
            return $this->prepareDataForApi($customerGroups, $website->getId());
        }
        
        $ids = [];
        foreach ($customerGroups as $item) {
            $ids[] = $customerGroup["value"];
        }

        return $ids;
    }

    /**
     * @param \Magento\Customer\Model\ResourceModel\Group\Collection $customerGroup
     * @return array
     */
    public function formatData($customerGroup)
    {
        $data = [
            "id" => (string) $customerGroup["value"],
            "name" => (string) $customerGroup["label"]
        ];

        return $data;
    }

    /**
     * @return false|string
     */
    public function getSchema()
    {
        $schema = [
            "type" => "record",
            "name" => "CustomerGroup",
            "fields" => [
                [
                    "name" => "id",
                    "type" => "string"
                ],
                [
                    "name" => "name",
                    "type" => "string"
                ]
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}
