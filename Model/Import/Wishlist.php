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

class Wishlist extends AbstractModel
{
    /**
     * Native Magento wishlist storage entrypoint.
     *
     * @var string
     */
    protected $_coreTable = "wishlist";

    /**
     * Scope filtering is handled manually through the customer website.
     *
     * @var bool
     */
    protected $_filterScope = false;

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection
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
    }

    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $criteria = $searchCriteria->create();
        $filters = [];
        foreach ($criteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $filters[] = [
                    "field" => $this->remapFilterField($filter->getField()),
                    "condition" => $filter->getConditionType() ?: "eq",
                    "value" => $filter->getValue(),
                ];
            }
        }

        $connection = $this->_resourceConnection->getConnection();
        $select = $this->buildBaseSelect($websiteId, $filters, false)
            ->order("wishlist_item.wishlist_item_id ASC")
            ->limitPage($criteria->getCurrentPage(), $criteria->getPageSize());

        $rows = $connection->fetchAll($select);
        if (empty($rows)) {
            return [];
        }

        $optionRows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->_resourceConnection->getTableName("wishlist_item_option"),
                    ["wishlist_item_id", "code", "value"]
                )
                ->where(
                    "wishlist_item_id IN (?)",
                    array_map(static function ($row) {
                        return $row["wishlist_item_id"];
                    }, $rows)
                )
                ->order(["wishlist_item_id ASC", "option_id ASC"])
        );

        $optionsByWishlistItemId = [];
        foreach ($optionRows as $optionRow) {
            $wishlistItemId = (string)$optionRow["wishlist_item_id"];
            if (!isset($optionsByWishlistItemId[$wishlistItemId])) {
                $optionsByWishlistItemId[$wishlistItemId] = [];
            }

            $optionsByWishlistItemId[$wishlistItemId][] = $optionRow;
        }

        foreach ($rows as &$row) {
            $wishlistItemId = (string)$row["wishlist_item_id"];
            $row["options_rows"] = $optionsByWishlistItemId[$wishlistItemId] ?? [];
        }
        unset($row);

        return $rows;
    }

    /**
     * AbstractModel assumes collections yield Magento entities exposing getId().
     * Wishlist uses raw SQL rows instead, so we handle the ids-only pull
     * explicitly and return wishlist_item identifiers.
     *
     * @param \Magento\Store\Api\Data\WebsiteInterface $website
     * @param int $limit
     * @param int $offset
     * @param string|null $createdAt
     * @param string|null $updatedAt
     * @param bool $withData
     * @return array
     */
    public function getSyncCollection($website, $limit, $offset, $createdAt = null, $updatedAt = null, $withData = true)
    {
        $websiteId = $website->getId();
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

        if ($withData) {
            return $this->prepareDataForApi($this->getModelCollection($searchCriteria, $websiteId), $websiteId);
        }

        $criteria = $searchCriteria->create();
        $filters = [];
        foreach ($criteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $filters[] = [
                    "field" => $this->remapFilterField($filter->getField()),
                    "condition" => $filter->getConditionType() ?: "eq",
                    "value" => $filter->getValue(),
                ];
            }
        }

        $connection = $this->_resourceConnection->getConnection();
        $select = $this->buildBaseSelect($websiteId, $filters, false)
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(["wishlist_item_id" => "wishlist_item.wishlist_item_id"])
            ->order("wishlist_item.wishlist_item_id ASC")
            ->limitPage($criteria->getCurrentPage(), $criteria->getPageSize());

        return array_map("strval", $connection->fetchCol($select));
    }

    public function getTotalCount($websiteId)
    {
        $connection = $this->_resourceConnection->getConnection();
        return (int)$connection->fetchOne($this->buildBaseSelect($websiteId, [], true));
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $wishlistItems = [];
        try {
            foreach ($collection as $wishlistRow) {
                $data = $this->formatData($wishlistRow, $websiteId);
                if (!array_key_exists("error", $data)) {
                    $wishlistItems[] = $data;
                }
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "prepareDataForApi wishlist",
                $e->getMessage(),
                $websiteId
            );
        }

        return $wishlistItems;
    }

    public function formatData($wishlistRow, $websiteId)
    {
        try {
            $defaultStoreId = (string)$this->_configHelper->getWebsiteById($websiteId)->getDefaultStore()->getId();
            $variantId = $this->resolveVariantId($wishlistRow["options_rows"] ?? []);

            return [
                "id_wishlist" => (string)$wishlistRow["wishlist_id"],
                "id_customer" => (string)$wishlistRow["customer_id"],
                "id_product" => (string)$wishlistRow["product_id"],
                "id_product_attribute" => $variantId,
                "qty" => (string)$wishlistRow["qty"],
                "added_at" => (string)$wishlistRow["added_at"],
                "id_shop" => (string)($wishlistRow["id_shop"] ?: $defaultStoreId),
                "id_shop_group" => (string)$websiteId,
            ];
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "formatData wishlist",
                $e->getMessage(),
                $websiteId
            );
            return ["error" => $e->getMessage()];
        }
    }

    public function getSchema()
    {
        $schema = [
            "type" => "record",
            "name" => "wishlist",
            "fields" => [
                ["name" => "id_wishlist", "type" => "string"],
                ["name" => "id_customer", "type" => "string"],
                ["name" => "id_product", "type" => "string"],
                ["name" => "id_product_attribute", "type" => "string"],
                ["name" => "qty", "type" => "string"],
                ["name" => "added_at", "type" => "string"],
                ["name" => "id_shop", "type" => "string"],
                ["name" => "id_shop_group", "type" => "string"],
            ],
        ];

        return $this->_serializer->serialize($schema);
    }

    /**
     * Query the active native wishlist rows for one Magento website.
     *
     * @param int $websiteId
     * @param array $filters
     * @param bool $countOnly
     * @return \Magento\Framework\DB\Select
     */
    protected function buildBaseSelect($websiteId, array $filters, $countOnly)
    {
        $connection = $this->_resourceConnection->getConnection();
        $wishlistTable = $this->_resourceConnection->getTableName("wishlist");
        $wishlistItemTable = $this->_resourceConnection->getTableName("wishlist_item");
        $customerTable = $this->_resourceConnection->getTableName("customer_entity");

        $columns = $countOnly
            ? [new \Zend_Db_Expr("COUNT(*)")]
            : [
                "wishlist_id" => "wishlist.wishlist_id",
                "wishlist_item_id" => "wishlist_item.wishlist_item_id",
                "customer_id" => "wishlist.customer_id",
                "product_id" => "wishlist_item.product_id",
                "qty" => "wishlist_item.qty",
                "added_at" => "wishlist_item.added_at",
                "id_shop" => "customer.store_id",
            ];

        $select = $connection->select()
            ->from(["wishlist" => $wishlistTable], $columns)
            ->joinInner(
                ["wishlist_item" => $wishlistItemTable],
                "wishlist_item.wishlist_id = wishlist.wishlist_id",
                []
            )
            ->joinInner(
                ["customer" => $customerTable],
                "customer.entity_id = wishlist.customer_id",
                []
            )
            ->where("customer.website_id = ?", (int)$websiteId);

        foreach ($filters as $filter) {
            $field = $filter["field"];
            $condition = $filter["condition"];
            $value = $filter["value"];

            switch ($condition) {
                case "gteq":
                    $select->where("{$field} >= ?", $value);
                    break;
                case "lteq":
                    $select->where("{$field} <= ?", $value);
                    break;
                case "in":
                    $select->where("{$field} IN (?)", is_array($value) ? $value : explode(",", (string)$value));
                    break;
                default:
                    $select->where("{$field} = ?", $value);
                    break;
            }
        }

        return $select;
    }

    /**
     * Wishlist rows only expose the add date, so both created/updated filters
     * are mapped to the same native column.
     *
     * @param string $field
     * @return string
     */
    protected function remapFilterField($field)
    {
        if (in_array($field, ["created_at", "updated_at"], true)) {
            return "wishlist_item.added_at";
        }

        $allowedFieldMap = [
            "id_wishlist" => "wishlist.wishlist_id",
            "id_customer" => "wishlist.customer_id",
            "id_product" => "wishlist_item.product_id",
            "added_at" => "wishlist_item.added_at",
        ];

        return $allowedFieldMap[$field] ?? "wishlist_item.added_at";
    }

    /**
     * Resolve the Magento variant from native wishlist item options when the
     * item comes from a configurable product. If no explicit child can be
     * recovered, downstream Databricks logic will safely keep "0".
     *
     * @param array $optionRows
     * @return string
     */
    protected function resolveVariantId(array $optionRows)
    {
        foreach ($optionRows as $optionRow) {
            $code = (string)($optionRow["code"] ?? "");
            $value = trim((string)($optionRow["value"] ?? ""));

            if (in_array($code, ["simple_product", "simple_product_id", "selected_configurable_option"], true)) {
                $numericValue = preg_replace("/[^0-9]/", "", $value);
                if ($numericValue !== "") {
                    return $numericValue;
                }
            }

            if ($code !== "info_buyRequest") {
                continue;
            }

            $resolvedFromBuyRequest = $this->extractVariantIdFromBuyRequest($value);
            if ($resolvedFromBuyRequest !== null) {
                return $resolvedFromBuyRequest;
            }
        }

        return "0";
    }

    /**
     * Magento stores buy request payloads in multiple serialization formats
     * depending on the platform version and extensions. We accept JSON when it
     * is available, and fall back to a conservative regex for PHP-serialized
     * payloads so the export stays robust without extra dependencies.
     *
     * @param string $buyRequestValue
     * @return string|null
     */
    protected function extractVariantIdFromBuyRequest($buyRequestValue)
    {
        if ($buyRequestValue === "") {
            return null;
        }

        try {
            $decoded = $this->_serializer->unserialize($buyRequestValue);
            if (is_array($decoded)) {
                foreach (["selected_configurable_option", "simple_product", "simple_product_id"] as $candidateKey) {
                    if (!empty($decoded[$candidateKey])) {
                        return (string)$decoded[$candidateKey];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $patterns = [
            '/selected_configurable_option["\']?\s*[:;]\s*(?:i|s:\d+:"|")?(\d+)/',
            '/simple_product_id["\']?\s*[:;]\s*(?:i|s:\d+:"|")?(\d+)/',
            '/simple_product["\']?\s*[:;]\s*(?:i|s:\d+:"|")?(\d+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $buyRequestValue, $matches) === 1 && !empty($matches[1])) {
                return (string)$matches[1];
            }
        }

        return null;
    }
}
