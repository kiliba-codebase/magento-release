<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\SalesRule\Model\ResourceModel\Coupon\CollectionFactory;

class Coupon extends AbstractModel
{
    /**
     * @var CouponRepositoryInterface
     */
    protected $_couponRepository;

    /**
     * @var CollectionFactory
     */
    protected $_couponCollectionFactory;

    protected $_coreTable = "salesrule_coupon";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        CouponRepositoryInterface $couponRepository,
        CollectionFactory $couponCollectionFactory
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
        $this->_couponRepository = $couponRepository;
        $this->_couponCollectionFactory = $couponCollectionFactory;
    }

    /**
     * @param int $entityId
     * @return \Magento\SalesRule\Api\Data\CouponInterface
     * @throws NoSuchEntityException
     */
    public function getEntity($entityId)
    {
        return $this->_couponRepository->getById($entityId);
    }

    protected function addWebsiteFilter($collection, $websiteId)
    {
        $websiteSelect = $collection->getConnection()->select();
        $websiteSelect->from(
            "salesrule_website",
            ["rule_id"]
        )->distinct(
            true
        )->where(
            $collection->getConnection()->quoteInto("website_id" . ' IN (?)', [$websiteId])
        );

        $collection->getSelect()->join(
            ['website' => $websiteSelect],
            'main_table.rule_id = website.rule_id',
            []
        );

        return $collection;
    }
    
    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $criteria = $searchCriteria->create();
        $limit = $criteria->getPageSize();
        $offset = ($criteria->getCurrentPage() - 1) * $limit;

        $collection = $this->addWebsiteFilter($this->_couponCollectionFactory->create(), $websiteId);

        $collection
            ->getSelect()
            ->order(array('main_table.coupon_id ASC'))
            ->limit($limit, $offset);

        return $collection;
    }

    public function getTotalCount($websiteId) {
        return $this->addWebsiteFilter($this->_couponCollectionFactory->create(), $websiteId)
            ->getSize();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $couponsData = [];
        try {
            foreach ($collection as $coupon) {
                if ($coupon->getId()) {
                    $data = $this->formatData($coupon, $websiteId);
                    if (!array_key_exists("error", $data)) {
                        $couponsData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "Format data coupon";
            if (isset($coupon)) {
                $message .= " coupon id " . $coupon->getId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }
        return $couponsData;
    }


    /**
     * @param \Magento\SalesRule\Api\Data\CouponInterface $coupon
     * @param int $websiteId
     * @return array
     */
    public function formatData($coupon, $websiteId)
    {
        try {
            $data = [
                "id" => (string)$coupon->getId(),
                "rule_id" => (string)$coupon->getRuleId(),
                "code" => (string)$coupon->getCode(),
                "usage_limit" => (string)$coupon->getUsageLimit(),
                "usage_per_customer" => (string)$coupon->getUsagePerCustomer(),
                "times_used" => (string)$coupon->getTimesUsed(),
                "expiration_date" => (string)$coupon->getExpirationDate(),
                "is_primary" => (string)$coupon->getIsPrimary(),
                "created_at" => (string)$coupon->getCreatedAt(),
                "type" => (string)$coupon->getType(),
            ];
            return $data;
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format coupon data, id = " . $coupon->getId(),
                $e->getMessage(),
                $websiteId
            );
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * @return false|string
     */
    public function getSchema()
    {
        $schema = [
            "type" => "record",
            "name" => "Coupon",
            "fields" => [
                [
                    "name" => "id",
                    "type" => "string"
                ],
                [
                    "name" => "rule_id",
                    "type" => "string"
                ],
                [
                    "name" => "code",
                    "type" => "string"
                ],
                [
                    "name" => "usage_limit",
                    "type" => "string"
                ],
                [
                    "name" => "usage_per_customer",
                    "type" => "string"
                ],
                [
                    "name" => "times_used",
                    "type" => "string"
                ],
                [
                    "name" => "expiration_date",
                    "type" => "string"
                ],
                [
                    "name" => "is_primary",
                    "type" => "string"
                ],
                [
                    "name" => "created_at",
                    "type" => "string"
                ],
                [
                    "name" => "type",
                    "type" => "string"
                ],
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}
