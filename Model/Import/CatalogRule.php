<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;

class CatalogRule extends AbstractModel
{
    /**
     * @var CollectionFactory
     */
    protected $_catalogRuleCollectionFactory;

    protected $_coreTable = 'catalogrule';

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        CollectionFactory $catalogRuleCollectionFactory
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

        $this->_catalogRuleCollectionFactory = $catalogRuleCollectionFactory;
    }

    /**
     * Build collection filtered by website with pagination.
     *
     * @param SearchCriteriaBuilder $searchCriteria
     * @param int $websiteId
     * @return \Magento\CatalogRule\Model\ResourceModel\Rule\Collection
     */
    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $criteria = $searchCriteria->create();
        $limit = $criteria->getPageSize();
        $offset = ($criteria->getCurrentPage() - 1) * $limit;

        $collection = $this->_catalogRuleCollectionFactory->create();

        // website_ids column is stored as comma-separated list. Use finset condition.
        $collection->addFieldToFilter('website_ids', ['finset' => $websiteId]);

        $collection->getSelect()
            ->order(['main_table.rule_id ASC'])
            ->limit($limit, $offset);

        return $collection;
    }

    public function getTotalCount($websiteId)
    {
        return $this->_catalogRuleCollectionFactory->create()
            ->addFieldToFilter('website_ids', ['finset' => $websiteId])
            ->getSize();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $rulesData = [];
        try {
            foreach ($collection as $rule) {
                if ($rule->getId()) {
                    $data = $this->formatData($rule, $websiteId);
                    if (!array_key_exists('error', $data)) {
                        $rulesData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = 'Format data catalogRule';
            if (isset($rule)) {
                $message .= ' rule id ' . $rule->getId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }

        return $rulesData;
    }

    /**
     * Format a single rule for API.
     *
     * @param \Magento\CatalogRule\Model\Rule $rule
     * @param int $websiteId
     * @return array
     */
    public function formatData($rule, $websiteId)
    {
        try {
            $matchingProductIds = $rule->getMatchingProductIds();
            $customerGroupIds = $rule->getCustomerGroupIds();

            $from = $rule->getFromDate();
            $to = $rule->getToDate();

            $data = [
                'id'              => (string)$rule->getId(),
                'name'            => (string)$rule->getName(),
                'description'     => (string)$rule->getDescription(),
                'from_date'       => (string)($from ? strtotime($from) : null),
                'to_date'         => (string)($to ? strtotime($to) : null),
                'is_active'       => $this->_formatBoolean($rule->getIsActive()),
                'discount_amount' => (string)$rule->getDiscountAmount(),
                'simple_action'   => (string)$rule->getSimpleAction(),
                'priority'        => (string)$rule->getSortOrder(),
                'stop_processing'    => $this->_formatBoolean($rule->getStopRulesProcessing()),
                'product_ids'        => implode(',', $matchingProductIds ? array_keys($matchingProductIds) : []),
                'customer_group_ids' => implode(',', $customerGroupIds ? $customerGroupIds : []),
                'deleted'            => $this->_formatBoolean(false)
            ];

            return $data;
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                'Format catalogRule data, id = ' . $rule->getId(),
                $e->getMessage(),
                $websiteId
            );

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create the payload for a deleted catalog rule (fields mostly empty, deleted flag set to 1).
     *
     * @param int $catalogRuleId
     * @param int $websiteId
     * @return array
     */
    public function formatDeletedCatalogRule($catalogRuleId, $websiteId)
    {
        return [
            'id'              => (string)$catalogRuleId,
            'name'            => '',
            'description'     => '',
            'from_date'       => '',
            'to_date'         => '',
            'is_active'       => '',
            'discount_amount' => '',
            'simple_action'   => '',
            'priority'        => '',
            'stop_processing' => '',
            'product_ids'        => '',
            'customer_group_ids' => '',
            'deleted'         => $this->_formatBoolean(true),
        ];
    }

    /**
     * @return string
     */
    public function getSchema()
    {
        $schema = [
            'type'   => 'record',
            'name'   => 'CatalogRule',
            'fields' => [
                ['name' => 'id',   'type' => 'string', 'doc' => 'Identifiant unique de la règle'],
                ['name' => 'name', 'type' => 'string', 'doc' => 'Nom de la règle (back-office)'],
                ['name' => 'description', 'type' => 'string', 'doc' => 'Description libre'],
                ['name' => 'from_date', 'type' => 'string', 'doc' => 'Date de début de validité (YYYY-mm-dd HH:ii:ss)'],
                ['name' => 'to_date', 'type' => 'string', 'doc' => 'Date de fin (vide si illimitée)'],
                ['name' => 'is_active', 'type' => 'string', 'doc' => '1 = activée, 0 = désactivée'],
                ['name' => 'discount_amount', 'type' => 'string', 'doc' => 'Valeur de la remise (selon simple_action)'],
                ['name' => 'simple_action', 'type' => 'string', 'doc' => 'Type d’action : by_percent, to_fixed, etc.'],
                ['name' => 'priority', 'type' => 'string', 'doc' => 'Priorité (sort_order). Plus petit = plus prioritaire'],
                ['name' => 'stop_processing', 'type' => 'string', 'doc' => '1 = stop further rules processing, 0 sinon'],
                ['name' => 'product_ids', 'type' => 'string', 'doc' => 'IDs produits concernés, séparés par des virgules'],
                ['name' => 'customer_group_ids', 'type' => 'string', 'doc' => 'IDs groupes clients, 32000 = tous'],
                ['name' => 'deleted', 'type' => 'string', 'doc' => '1 = supprimée, 0 = active'],
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}
