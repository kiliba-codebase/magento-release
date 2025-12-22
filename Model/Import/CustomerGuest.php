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
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;

class CustomerGuest extends AbstractModel
{
    /**
     * @var SubscriberCollectionFactory
     */
    protected $subscriberCollectionFactory;

    /**
     * newsletter_subscriber table does not use the default store scope column
     * so we disable the automatic store filtering.
     *
     * @var bool
     */
    protected $_filterScope = false;

    /**
     * @var string
     */
    protected $_coreTable = "newsletter_subscriber";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        SubscriberCollectionFactory $subscriberCollectionFactory
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
        $this->subscriberCollectionFactory = $subscriberCollectionFactory;
    }

    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $collection = $this->prepareBaseCollection($websiteId);

        $filters = $searchCriteria->create()->getFilterGroups();
        foreach ($filters as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $condition = $filter->getConditionType() ?: 'eq';
                $field = $this->remapFilterField($filter->getField());
                $collection->addFieldToFilter($field, [$condition => $filter->getValue()]);
            }
        }

        $criteria = $searchCriteria->create();
        $collection->setPageSize($criteria->getPageSize());
        $collection->setCurPage($criteria->getCurrentPage());

        return $collection->getItems();
    }

    public function getTotalCount($websiteId)
    {
        return $this->prepareBaseCollection($websiteId)->getSize();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $guests = [];
        try {
            foreach ($collection as $subscriber) {
                if (!$subscriber->getId()) {
                    continue;
                }

                $data = $this->formatData($subscriber, $websiteId);
                if (!array_key_exists("error", $data)) {
                    $guests[] = $data;
                }
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "prepareDataForApi customerGuest",
                $e->getMessage(),
                $websiteId
            );
        }

        return $guests;
    }

    public function formatData(Subscriber $subscriber, $websiteId)
    {
        try {
            return [
                "subscriber_id" => (string)$subscriber->getId(),
                "email" => (string)$subscriber->getSubscriberEmail(),
                "subscriber_status" => (string)$subscriber->getSubscriberStatus(),
                "id_shop_group" => (string)$websiteId,
                "id_shop" => (string)$subscriber->getStoreId(),
                "change_status_at" => (string)$subscriber->getChangeStatusAt(),
                "confirm_code" => (string)$subscriber->getSubscriberConfirmCode(),
                "source" => (string)$subscriber->getSubscriberSource(),
                "ip" => (string)$subscriber->getSubscriberIp(),
            ];
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "formatData customerGuest",
                $e->getMessage(),
                $websiteId
            );
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * Base collection shared between list and count queries.
     *
     * @param int $websiteId
     * @return \Magento\Newsletter\Model\ResourceModel\Subscriber\Collection
     */
    protected function prepareBaseCollection($websiteId)
    {
        $storeIds = $this->_configHelper->getWebsiteById($websiteId)->getStoreIds();

        $collection = $this->subscriberCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', 0);
        $collection->addFieldToFilter(
            'subscriber_status',
            ['in' => $this->getAllowedStatuses()]
        );

        if (!empty($storeIds)) {
            $collection->addFieldToFilter('store_id', ['in' => $storeIds]);
        }

        return $collection;
    }

    /**
     * Guests only expose change_status_at, so map incoming date filters to that column.
     *
     * @param string $field
     * @return string
     */
    protected function remapFilterField($field)
    {
        if (in_array($field, ["created_at", "updated_at"])) {
            return "change_status_at";
        }

        return $field;
    }

    /**
     * @return int[]
     */
    protected function getAllowedStatuses()
    {
        return [
            Subscriber::STATUS_SUBSCRIBED,
            Subscriber::STATUS_NOT_ACTIVE,
            Subscriber::STATUS_UNSUBSCRIBED,
            Subscriber::STATUS_UNCONFIRMED,
        ];
    }
}
