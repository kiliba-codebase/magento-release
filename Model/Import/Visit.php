<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Api\Data\VisitInterface;
use Kiliba\Connector\Api\Data\VisitInterfaceFactory;
use Kiliba\Connector\Api\VisitRepositoryInterface;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;

class Visit extends AbstractModel
{
    /**
     * @var VisitInterfaceFactory
     */
    protected $_dataVisitFactory;

    /**
     * @var VisitRepositoryInterface
     */
    protected $_visitRepository;

    protected $_coreTable = "kiliba_connector_visit";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        VisitInterfaceFactory $dataVisitFactory,
        VisitRepositoryInterface $visitRepository
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
        $this->_dataVisitFactory = $dataVisitFactory;
        $this->_visitRepository = $visitRepository;
    }


    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $searchCriteria
            ->addFilter("store_id", $this->_configHelper->getWebsiteById($websiteId)->getStoreIds(), 'in');

        return $this->_visitRepository->getList($searchCriteria->create())->getItems();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $visitsData = [];
        foreach ($collection as $visit) {
            if (!empty($visit->getContent())) {
                $visitsData[] = $this->formatData($visit->getContent(), $websiteId);
            }
        }

        return $visitsData;
    }


    /**
     * @param string $content
     * @param int $storeId
     */
    public function recordVisit($content, $storeId)
    {
        // check if store is link to kiliba
        $store = $this->_configHelper->getStoreById($storeId);
        if (empty($store)) return;

        $website = $store->getWebsite();
        $websiteId = $website->getId();
        if (!empty($this->_configHelper->getClientId($websiteId))) {
            /** @var VisitInterface $visit */
            $visit = $this->_dataVisitFactory->create();
            $visit->setContent($content)->setStoreId($storeId);
            try {
                $this->_visitRepository->save($visit);
            } catch (LocalizedException $e) {
            }
        } else {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                __FUNCTION__,
                "Couldn't find website linked to store : " . $storeId,
                $websiteId
            );
        }
    }

    public function cleanOldVisit()
    {
        $cleanBefore = date_create("4 days ago")->format("Y-m-d");
        $this->_searchCriterieBuilder->addFilter(VisitInterface::CREATED_AT, $cleanBefore, "lteq");

        $visitToClean = $this->_visitRepository->getList($this->_searchCriterieBuilder->create());

        $numberCleaned = 0;
        foreach ($visitToClean->getItems() as $visit) {
            try {
                $this->_visitRepository->delete($visit);
                $numberCleaned++;
            } catch (LocalizedException $e) {
                $this->_kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_ERROR,
                    "Clean old visit before date = " . $cleanBefore,
                    $e->getMessage(),
                    0
                );
            }
        }

        $this->_kilibaLogger->addLog(
            KilibaLogger::LOG_TYPE_INFO,
            "Clean old visit before date = " . $cleanBefore,
            $numberCleaned . " visit removed / " . $visitToClean->getTotalCount() . " to delete",
            0
        );
        return $numberCleaned;
    }

    /**
     * @param string $cookieContent
     * @param int $storeId
     */
    public function addPreviousVisits($cookieContent, $customerId, $storeId)
    {
        try {
            // check if store is link to kiliba
            $store = $this->_configHelper->getStoreById($storeId);
            if (empty($store)) return;
    
            $website = $store->getWebsite();
            $websiteId = $website->getId();
            if (!empty($this->_configHelper->getClientId($websiteId))) {
                $recentlyViewedProduct = $this->_serializer->unserialize($cookieContent);
                if (!empty($recentlyViewedProduct)) {
                    foreach ($recentlyViewedProduct as $view) {
                        try {
                            $data = [
                                "url" => "",
                                "id_customer" => (string) $customerId,
                                "date" => date("Y-m-d H:i:s", intval($view["added_at"])),
                                "id_product" => $view["product_id"],
                                "id_category" => "0"
                            ];
                            $this->recordVisit($this->_serializer->serialize($data), $storeId);
                        } catch (\Exception $e) {
                            $this->_kilibaLogger->addLog(
                                KilibaLogger::LOG_TYPE_ERROR,
                                __FUNCTION__,
                                "Unable to register visit for product " . (isset($view) && isset($view["product_id"]) ? $view["product_id"] : 'N/A') . ": " . $e->getMessage(),
                                $websiteId
                            );
                        }
                    }
                }
            } else {
                $this->_kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_ERROR,
                    __FUNCTION__,
                    "Couldn't find website linked to store : " . $websiteId,
                    $websiteId
                );
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                __FUNCTION__,
                "Unable to register previous visits : " . $e->getMessage(),
                0
            );
        }
    }

    /**
     * @param string $data
     * @param $websiteId
     * @return array[]
     */
    public function formatData($data, $websiteId)
    {
        $data = $this->_serializer->unserialize($data);

        return $data;
    }

    /**
     * @return false|string
     */
    public function getSchema()
    {
        $schema = [
            "type" => "record",
            "name" => "Visit",
            "fields" => [
                [
                    "name" => "url",
                    "type" => "string"
                ],
                [
                    "name" => "id_customer",
                    "type" => "string"
                ],
                [
                    "name" => "date",
                    "type" => "string"
                ],
                [
                    "name" => "id_product",
                    "type" => "string"
                ],
                [
                    "name" => "id_category",
                    "type" => "string"
                ],
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}
