<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Magento\Quote\Api\CartRepositoryInterface;

class Quote extends AbstractModel
{

    /**
     * @var CollectionFactory
     */
    protected $_quoteCollectionFactory;

    /**
     * @var CartRepositoryInterface
     */
    protected $_quoteRepository;

    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    protected $_coreTable = "quote";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        CollectionFactory $quoteCollectionFactory,
        CartRepositoryInterface $quoteRepository,
        ProductRepositoryInterface $productRepository
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
        $this->_quoteCollectionFactory = $quoteCollectionFactory;
        $this->_quoteRepository = $quoteRepository;
        $this->_productRepository = $productRepository;
    }

    /**
     * @param int $entityId
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws NoSuchEntityException
     */
    public function getEntity($entityId)
    {
        return $this->_quoteRepository->get($entityId);
    }

    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $searchCriteria
            ->addFilter("store_id", $this->_configHelper->getWebsiteById($websiteId)->getStoreIds(), 'in');

        return $this->_quoteRepository->getList($searchCriteria->create())->getItems();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $quotesData = [];
        try {
            foreach ($collection as $quote) {
                if ($quote->getId()) {
                    $data = $this->formatData($quote, $websiteId);
                    if (!array_key_exists("error", $data)) {
                        $quotesData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "Format data quote";
            if (isset($quote)) {
                $message .= " quote id " . $quote->getId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }
        return $quotesData;
    }

  
    /**
     * @param \Magento\Quote\Model\Quote|\Magento\Quote\Api\Data\CartInterface $quote
     * @param int $websiteId
     * @return array
     */
    public function formatData($quote, $websiteId)
    {
        try {
            $shippingAddressId = !empty($quote->getShippingAddress()) ? $quote->getShippingAddress()->getId() : "";

            $productData = [];
            foreach ($quote->getAllVisibleItems() as $item) {
                $itemData = $this->_formatProductData($item, $websiteId);
                if (!empty($itemData)) {
                    $productData[] = $itemData;
                }
            }

            $data = [
                "id" => (string)$quote->getId(),
                "id_shop_group" => (string)$websiteId,
                "id_shop" => (string)$quote->getStoreId(),
                "id_customer" => (string)$quote->getCustomerId(),
                "id_currency" => (string)$quote->getBaseCurrencyCode(),
                "id_address_delivery" => (string)$shippingAddressId,
                "id_address_invoice" => (string)$quote->getBillingAddress()->getId(),
                "date_add" => (string)$quote->getCreatedAt(),
                "date_update" => (string)$quote->getUpdatedAt(),
                "total_with_tax_with_shipping_with_discount" => (string)$quote->getBaseGrandTotal(),
                "total_with_tax_without_shipping_with_discount" => (string)$quote->getBaseSubtotalWithDiscount(),
                "total_discount_with_tax" => $this->_formatPrice(
                    $quote->getBaseSubtotalWithDiscount() - $quote->getBaseSubtotal()
                ),
                "total_products_with_tax" => (string)$quote->getBaseGrandTotal(), // no shipping price ?
                "products" => $productData,
            ];

            return $data;
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format quote data, id = " . $quote->getId(),
                $e->getMessage(),
                $websiteId
            );
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $quoteItem
     * @param int $websiteId
     * @return array
     */
    protected function _formatProductData($quoteItem, $websiteId)
    {
        try {
            $product = $quoteItem->getProduct();

            return [
                "id_product" => (string) $product->getId(),
                "id_product_attribute" => (string) $product->getAttributeSetId(),
                "cart_quantity" => (string) $quoteItem->getQty(),
                "id_shop" => (string) $quoteItem->getStoreId(),
                "reference" => (string) $quoteItem->getSku(),
                "reduction" => (string) $quoteItem->getDiscountAmount(),
                "reduction_type" => "",
                "total_wt" => $this->_formatPrice($quoteItem->getBaseRowTotalInclTax()),
                "total_unit_wt" => $this->_formatPrice($quoteItem->getBasePriceInclTax()),
                "id_category_default" => $this->_formatterHelper->getLowerCategory(
                    $product->getCategoryIds(),
                    $product->getStoreId()
                ),
            ];
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format quote product data, id = " . $quoteItem->getProductId(),
                $e->getMessage(),
                $websiteId
            );
            return null;
        }
    }

    /**
     * @return false|string
     */
    public function getSchema()
    {
        $schema = [
            "type" => "record",
            "name" => "Cart",
            "fields" => [
                [
                    "name" => "id",
                    "type" => "string"
                ],
                [
                    "name" => "id_shop_group",
                    "type" => "string"
                ],
                [
                    "name" => "id_shop",
                    "type" => "string"
                ],
                [
                    "name" => "id_customer",
                    "type" => "string"
                ],
                [
                    "name" => "id_currency",
                    "type" => "string"
                ],
                [
                    "name" => "id_address_delivery",
                    "type" => "string"
                ],
                [
                    "name" => "id_address_invoice",
                    "type" => "string"
                ],
                [
                    "name" => "date_add",
                    "type" => "string"
                ],
                [
                    "name" => "date_update",
                    "type" => "string"
                ],
                [
                    "name" => "total_with_tax_with_shipping_with_discount",
                    "type" => "string"
                ],
                [
                    "name" => "total_with_tax_without_shipping_with_discount",
                    "type" => "string"
                ],
                [
                    "name" => "total_discount_with_tax",
                    "type" => "string"
                ],
                [
                    "name" => "total_products_with_tax",
                    "type" => "string"
                ],
                [
                    "name" => "products",
                    "type" => [
                        "type" => "array",
                        "items" => [
                            "name" => "Product",
                            "type" => "record",
                            "fields" => [
                                [
                                    "name" => "id_product",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "id_product_attribute",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "cart_quantity",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "id_shop",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "reference",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "reduction",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "reduction_type",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "total_wt",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "total_unit_wt",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "id_category_default",
                                    "type" => "string"
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}
