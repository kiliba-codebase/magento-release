<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Model\ResourceModel\PopupCustomer\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;

class PopupCustomer extends AbstractModel
{
    /**
     * @var CollectionFactory
     */
    protected $_popupCustomerCollectionFactory;

    protected $_coreTable = "kiliba_connector_popup_customers";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        CollectionFactory $popupCustomerCollectionFactory
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
        $this->_popupCustomerCollectionFactory = $popupCustomerCollectionFactory;
    }

    /**
     * @param int $entityId
     * @param int $websiteId
     * @return \Kiliba\Connector\Model\PopupCustomer
     * @throws NoSuchEntityException
     */
    public function getEntity($entityId, $websiteId = null)
    {
        $popupCustomer = $this->_popupCustomerCollectionFactory->create()
            ->addFieldToFilter('popup_customer_id', $entityId)
            ->getFirstItem();

        if (!$popupCustomer->getId()) {
            throw new NoSuchEntityException(__('Popup customer with id "%1" does not exist.', $entityId));
        }

        return $popupCustomer;
    }

    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $collection = $this->_popupCustomerCollectionFactory->create();
        $collection->addFieldToFilter('website_id', $websiteId);

        // Apply filters from search criteria
        $filters = $searchCriteria->create()->getFilterGroups();
        foreach ($filters as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
                $field = $filter->getField();
                
                // Remap updated_at to created_at since updated_at column doesn't exist
                if ($field === 'updated_at') {
                    $field = 'created_at';
                }
                
                $collection->addFieldToFilter($field, [$condition => $filter->getValue()]);
            }
        }

        // Apply pagination
        $collection->setPageSize($searchCriteria->create()->getPageSize());
        $collection->setCurPage($searchCriteria->create()->getCurrentPage());

        return $collection->getItems();
    }

    public function getTotalCount($websiteId)
    {
        return $this->_popupCustomerCollectionFactory->create()
            ->addFieldToFilter('website_id', $websiteId)
            ->getSize();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $popupCustomersData = [];
        try {
            foreach ($collection as $popupCustomer) {
                if ($popupCustomer->getId()) {
                    $data = $this->formatData($popupCustomer, $websiteId);
                    if (!array_key_exists("error", $data)) {
                        $popupCustomersData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "prepareDataForApi popupCustomer",
                $e->getMessage(),
                $websiteId
            );
        }

        return $popupCustomersData;
    }

    public function formatData($popupCustomer, $websiteId)
    {
        $data = [];
        try {
            $data = [
                'id_popup_customer' => $popupCustomer->getPopupCustomerId(),
                'popup_type' => $popupCustomer->getPopupType(),
                'email' => $popupCustomer->getEmail(),
                'subscribe' => (int)$popupCustomer->getSubscribe(),
                'subscribe_ip' => $popupCustomer->getSubscribeIp(),
                'website_id' => (int)$popupCustomer->getWebsiteId(),
                'created_at' => (string)$popupCustomer->getCreatedAt(),
            ];
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "formatData popupCustomer",
                $e->getMessage(),
                $websiteId
            );
            $data['error'] = $e->getMessage();
        }

        return $data;
    }

    public function prepareIdsForApi($collection, $websiteId)
    {
        $popupCustomerIds = [];
        try {
            foreach ($collection as $popupCustomer) {
                if ($popupCustomer->getId()) {
                    $popupCustomerIds[] = [
                        'id_popup_customer' => $popupCustomer->getPopupCustomerId()
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "prepareIdsForApi popupCustomer",
                $e->getMessage(),
                $websiteId
            );
        }

        return $popupCustomerIds;
    }
}
