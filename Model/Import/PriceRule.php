<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;

class PriceRule extends AbstractModel
{
    /**
     * @var RuleRepositoryInterface
     */
    protected $_ruleRepository;

    /**
     * @var CollectionFactory
     */
    protected $_ruleCollectionFactory;

    protected $_coreTable = "salesrule";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        RuleRepositoryInterface $ruleRepository,
        CollectionFactory $ruleCollectionFactory
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
        $this->_ruleRepository = $ruleRepository;
        $this->_ruleCollectionFactory = $ruleCollectionFactory;
    }

    /**
     * @param int $entityId
     * @return \Magento\SalesRule\Api\Data\RuleInterface
     * @throws NoSuchEntityException
     */
    public function getEntity($entityId)
    {
        return $this->_ruleRepository->getById($entityId);
    }
    
    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $criteria = $searchCriteria->create();
        $limit = $criteria->getPageSize();
        $offset = ($criteria->getCurrentPage() - 1) * $limit;

        $collection = $this->_ruleCollectionFactory->create();
        $collection
            ->addWebsiteFilter($websiteId)
            ->getSelect()
            ->order(array('main_table.rule_id ASC'))
            ->limit($limit, $offset);

        return $collection;
    }

    public function getTotalCount($websiteId) {
        return $this->_ruleCollectionFactory->create()
            ->addWebsiteFilter($websiteId)
            ->getSize();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $priceRulesData = [];
        try {
            foreach ($collection as $priceRule) {
                if ($priceRule->getId()) {
                    $data = $this->formatData($priceRule, $websiteId);
                    if (!array_key_exists("error", $data)) {
                        $priceRulesData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "Format data priceRule";
            if (isset($priceRule)) {
                $message .= " priceRule id " . $priceRule->getId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }
        return $priceRulesData;
    }


    /**
     * @param \Magento\SalesRule\Api\Data\RuleInterface $priceRule
     * @param int $websiteId
     * @return array
     */
    public function formatData($priceRule, $websiteId)
    {
        try {
            $data = [
                "id" => (string)$priceRule->getId(),
                "name" => (string)$priceRule->getName(),
                "from_date" => (string)$priceRule->getFromDate(),
                "to_date" => (string)$priceRule->getToDate(),
                "uses_per_customer" => (string)$priceRule->getUsesPerCustomer(),
                "is_active" => (string)$priceRule->getIsActive(),
                "discount_amount" => (string)$priceRule->getDiscountAmount(),
                "discount_qty" => (string)$priceRule->getDiscountQty(),
                "discount_step" => (string)$priceRule->getDiscountStep(),
                "apply_to_shipping" => (string)$priceRule->getApplyToShipping(),
                "times_used" => (string)$priceRule->getTimesUsed(),
                "coupon_type" => (string)$priceRule->getCouponType(),
                "is_rss" => (string)$priceRule->getIsRss(),
                "use_auto_generation" => (string)$priceRule->getUseAutoGeneration(),
                "uses_per_coupon" => (string)$priceRule->getUsesPerCoupon(),
                "simple_action" => (string)$priceRule->getSimpleAction(),
                "deleted" => $this->_formatBoolean(false), // always false when use this function
            ];
            return $data;
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format priceRule data, id = " . $priceRule->getId(),
                $e->getMessage(),
                $websiteId
            );
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * @param int $priceRuleId
     * @param int $websiteId
     * @return array
     */
    public function formatDeletedPriceRule($priceRuleId, $websiteId)
    {
        $data = [
            "id" => (string) $priceRuleId,
            "name" => "",
            "from_date" => "",
            "to_date" => "",
            "uses_per_customer" => "",
            "is_active" => "",
            "discount_amount" => "",
            "discount_qty" => "",
            "discount_step" => "",
            "apply_to_shipping" => "",
            "times_used" => "",
            "coupon_type" => "",
            "is_rss" => "",
            "use_auto_generation" => "",
            "uses_per_coupon" => "",
            "simple_action" => "",
            "deleted" => $this->_formatBoolean(true), // always true when use this function
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
            "name" => "PriceRule",
            "fields" => [
                [
                    "name" => "id",
                    "type" => "string"
                ],
                [
                    "name" => "name", 
                    "type" => "string"
                ],
                [
                    "name" => "from_date", 
                    "type" => "string"
                ],
                [
                    "name" => "to_date", 
                    "type" => "string"
                ],
                [
                    "name" => "uses_per_customer", 
                    "type" => "string"
                ],
                [
                    "name" => "is_active", 
                    "type" => "string"
                ],
                [
                    "name" => "discount_amount", 
                    "type" => "string"
                ],
                [
                    "name" => "discount_qty", 
                    "type" => "string"
                ],
                [
                    "name" => "discount_step", 
                    "type" => "string"
                ],
                [
                    "name" => "apply_to_shipping", 
                    "type" => "string"
                ],
                [
                    "name" => "times_used", 
                    "type" => "string"
                ],
                [
                    "name" => "coupon_type", 
                    "type" => "string"
                ],
                [
                    "name" => "is_rss", 
                    "type" => "string"
                ],
                [
                    "name" => "use_auto_generation", 
                    "type" => "string"
                ],
                [
                    "name" => "uses_per_coupon", 
                    "type" => "string"
                ],
                [
                    "name" => "simple_action", 
                    "type" => "string"
                ],
                [
                    "name" => "deleted",
                    "type" => "string"
                ],
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}
