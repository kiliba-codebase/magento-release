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
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class Order extends AbstractModel
{

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    protected $_coreTable = "sales_order";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        OrderRepositoryInterface $orderRepository,
        CollectionFactory $orderCollectionFactory,
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
        $this->_orderRepository = $orderRepository;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_productRepository = $productRepository;
    }

    /**
     * @param int $entityId
     * @param int $websiteId
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws NoSuchEntityException
     */
    public function getEntity($entityId)
    {
        return $this->_orderRepository->get($entityId);
    }

    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $searchCriteria
            ->addFilter("store_id", $this->_configHelper->getWebsiteById($websiteId)->getStoreIds(), 'in');
            
        return $this->_orderRepository->getList($searchCriteria->create())->getItems();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $ordersData = [];
        try {
            foreach ($collection as $order) {
                if ($order->getId()) {
                    $data = $this->formatData($order, $websiteId);
                    if (!array_key_exists("error", $data)) {
                        $ordersData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "Format data order";
            if (isset($order)) {
                $message .= " order id " . $order->getId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }
        return $ordersData;
    }

    /**
     * @param \Magento\Sales\Model\Order|\Magento\Sales\Api\Data\OrderInterface $order
     * @param int $websiteId
     * @return array
     */
    public function formatData($order, $websiteId)
    {
        try {
            $shippingAddressId = !empty($order->getShippingAddress()) ? $order->getShippingAddress()->getId() : "";
            $totalWithoutTax = $this->_formatPrice($order->getBaseGrandTotal() - $order->getBaseTaxAmount());

            $productData = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $itemData = $this->_formatProductData($item, $order->getStoreId());
                if (!empty($itemData)) {
                    $productData[] = $itemData;
                }
            }

            $guest_data = null;
            try {
                if($order->getCustomerIsGuest()) {
                    $guest_data = [
                        "email" => (string) $order->getCustomerEmail(),
                        "first_name" => (string) $order->getCustomerFirstName(),
                        "last_name" => (string) $order->getCustomerLastName(),
                        "middle_name" => (string) $order->getCustomerMiddlename(),
                        "gender" => (string) $order->getCustomerGender()
                    ];
                }
            } catch (\Exception $e) {
                $this->_kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_ERROR,
                    "Unable to get guest checkout, id = " . $order->getId(),
                    $e->getMessage(),
                    $websiteId
                );
            }

            $data = [
                "id" => (string) $order->getId(),
                "id_shop_group" => (string) $websiteId,
                "id_shop" => (string) $order->getStoreId(),
                "id_customer" => (string) $order->getCustomerId(),
                "customer_guest" => $guest_data,
                "id_currency" => (string) $order->getBaseCurrencyCode(),
                "id_cart" => (string) $order->getQuoteId(),
                "current_state" => (string) $order->getStatus(),
                "reference" => (string) $order->getIncrementId(),
                "id_address_invoice" => (string) $order->getBillingAddressId(),
                "id_address_delivery" => (string) $shippingAddressId,
                "date_add" => (string) $order->getCreatedAt(),
                "date_update" => (string) $order->getUpdatedAt(),
                "total_paid" => $this->_formatPrice($order->getBaseGrandTotal()),
                "total_with_tax" => $this->_formatPrice($order->getBaseGrandTotal()),
                "total_without_tax" => $totalWithoutTax,
                "total_products_with_tax" => $this->_formatPrice($order->getBaseSubtotalInclTax()),
                "total_products_without_tax" => $this->_formatPrice($order->getBaseSubtotal()),
                "total_shipping_with_tax" => $this->_formatPrice($order->getBaseShippingInclTax()),
                "total_shipping_without_tax" => $this->_formatPrice($order->getBaseShippingAmount()),
                "total_discount_with_tax" => $this->_formatPrice($order->getBaseDiscountAmount()),
                "total_discount_without_tax" => $this->_formatPrice($order->getBaseDiscountAmount()),
                "products" => $productData,
            ];

            return $data;
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format order data, id = " . $order->getId(),
                $e->getMessage(),
                $websiteId
            );
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $orderItem
     * @param int $websiteId
     * @return array
     */
    protected function _formatProductData($orderItem, $storeId)
    {
        try {
            $product = $this->_productRepository->getById(
                $orderItem->getProductId(),
                false,
                $storeId
            );

            return [
                "id_product" => (string) $orderItem->getProductId(),
                "id_product_attribute" => (string) $product->getAttributeSetId(),
                "cart_quantity" => (string) $orderItem->getQtyOrdered(),
                "reference" => (string) $orderItem->getSku(),
                "reduction_amount" => (string) $orderItem->getDiscountAmount(),
                "reduction_percent" => (string) $orderItem->getDiscountPercent(),
                "price" => $this->_formatPrice($orderItem->getBasePrice()),
                "price_wt" => $this->_formatPrice($orderItem->getBasePriceInclTax()),
                "total_wt" => $this->_formatPrice($orderItem->getBaseRowTotalInclTax()),
                "id_category_default" => $this->_formatterHelper->getLowerCategory(
                    $product->getCategoryIds(),
                    $orderItem->getStoreId()
                ),
            ];
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format order product data, id = " . $orderItem->getProductId(),
                $e->getMessage(),
                $storeId
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
            "name" => "Order",
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
                    "name" => "id_cart",
                    "type" => "string"
                ],
                [
                    "name" => "current_state",
                    "type" => "string"
                ],
                [
                    "name" => "reference",
                    "type" => "string"
                ],
                [
                    "name" => "id_address_invoice",
                    "type" => "string"
                ],
                [
                    "name" => "id_address_delivery",
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
                    "name" => "total_paid",
                    "type" => "string"
                ],
                [
                    "name" => "total_with_tax",
                    "type" => "string"
                ],
                [
                    "name" => "total_without_tax",
                    "type" => "string"
                ],
                [
                    "name" => "total_products_with_tax",
                    "type" => "string"
                ],
                [
                    "name" => "total_products_without_tax",
                    "type" => "string"
                ],
                [
                    "name" => "total_shipping_with_tax",
                    "type" => "string"
                ],
                [
                    "name" => "total_shipping_without_tax",
                    "type" => "string"
                ],
                [
                    "name" => "total_discount_with_tax",
                    "type" => "string"
                ],
                [
                    "name" => "total_discount_without_tax",
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
                                    "name" => "reference",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "reduction_amount",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "reduction_percent",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "price",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "price_wt",
                                    "type" => "string"
                                ],
                                [
                                    "name" => "total_wt",
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
