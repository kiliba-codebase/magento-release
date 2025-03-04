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
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Kiliba\Connector\Helper\CookieHelper;

class Quote extends AbstractModel
{

    const PARAM_ENHANCED = "enhanced";

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

    /**
     * @var CustomerRepositoryInterface
     */
    protected $_customerRepository;

    /**
     * @var CookieHelper
     */
    protected $_cookieHelper;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var TimezoneInterface
     */
    protected $_timezone;

    protected $_coreTable = "quote";

    // store data
    /**
     * @var string[]
     */
    protected $_mediaUrl;
    /**
     * @var string
     */
    protected $_optionUseImageFromConfigurableChild;
    
    /**
     * @var bool
     */
    protected $enhancedMode;

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
        ProductRepositoryInterface $productRepository,
        CustomerRepositoryInterface $customerRepository,
        CookieHelper $cookieHelper,
        RequestInterface $request,
        TimezoneInterface $timezone
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
        $this->_customerRepository = $customerRepository;
        $this->_cookieHelper = $cookieHelper;
        $this->_request = $request;
        $this->_timezone = $timezone;

        $this->_mediaUrl = array();

        $this->enhancedMode = $this->_request->getParam(self::PARAM_ENHANCED) === "true";
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
            ->addFilter("main_table.store_id", $this->_configHelper->getWebsiteById($websiteId)->getStoreIds(), 'in');

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

            $customer_email = $quote->getCustomerEmail();
            $customer_email_type = "logged_customer_email";
            try {
                // If quote has no logged customer found, check if from guest with specified email
                if(empty($customer_email)) {
                    $customer_email = $quote->getBillingAddress()->getEmail();
                    $customer_email_type = "guest_customer_email";
                }
                // If no guest customer found, try to read kiliba customer from tracker
                if(empty($customer_email)) {
                    $customer_email = $this->_cookieHelper->getCustomerEmailViaKilibaCustomerKey($quote->getKilibaCustomerKey());
                    $customer_email_type = "tracker_customer_email";
                }
                // No information found for this quote
                if(empty($customer_email)) {
                    $customer_email_type = "";
                }
            } catch (\Exception $e) {}

            // Get customer details if enhanced mode
            $customer = null;
            if($this->enhancedMode) {
                try {
                    // Logged
                    if($quote->getCustomerId()) {
                        $customer = $this->_customerRepository->getById($quote->getCustomerId());
                    }
                    // Guest
                    else if($customer_email) {
                        $customer = $this->_customerRepository->get($customer_email);
                    }
                    // No one
                    else {
                        $customer = null;
                    }

                    if($customer) {
                        $customer = array(
                            "id" => (string) $customer->getId(),
                            "email" => (string) $customer->getEmail(),
                            "firstname" => (string) $customer->getFirstName(),
                            "lastname" => (string) $customer->getLastName()          
                        );
                    }
                } catch (\Exception $e) {}
            }

            $quoteLocale = $this->_configHelper->getStoreLocale($quote->getStoreId());

            $data = [
                "id" => (string)$quote->getId(),
                "customer_email" => (string)$customer_email,
                "customer_email_type" => (string)$customer_email_type,
                "customer" => $customer,
                "id_shop_group" => (string)$websiteId,
                "id_shop" => (string)$quote->getStoreId(),
                "id_customer" => (string)$quote->getCustomerId(),
                "id_currency" => (string)$quote->getBaseCurrencyCode(),
                "id_address_delivery" => (string)$shippingAddressId,
                "id_address_invoice" => (string)$quote->getBillingAddress()->getId(),
                "id_lang" => (string) $this->_configHelper->extractLangFromLocale($quoteLocale),
                "locale" => (string) $quoteLocale,
                "date_add" => (string) $quote->getCreatedAt(),
                "timestamp_add" => (string) $this->_timezone->date(new \DateTime($quote->getCreatedAt()))->getTimestamp(),
                "date_update" => (string) $quote->getUpdatedAt(),
                "timestamp_update" => (string) $this->_timezone->date(new \DateTime($quote->getUpdatedAt()))->getTimestamp(),
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
            $quoteProduct = $quoteItem->getProduct();
            $storeId = $quoteItem->getStoreId();

            $product = $quoteProduct;
            $imageUrl = "";
            $absoluteUrl = "";

            if($this->enhancedMode) {
                $configurableChildProduct = $quoteItem->getOptionByCode('simple_product');

                $product = $this->_productRepository->getById(
                    $configurableChildProduct ? $configurableChildProduct->getProduct()->getId() : $quoteProduct->getId(),
                    false,
                    $storeId
                );

                if (!isset($this->_mediaUrl[$storeId])) {
                    $store = $this->_configHelper->getStoreById($websiteId);
                    $this->_mediaUrl[$storeId] = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                }

                // Read option from cache
                // Or from config (defined in Settings > Sales > Checkout)
                $this->_optionUseImageFromConfigurableChild = $optionUseImageFromConfigurableChild =
                    $this->_optionUseImageFromConfigurableChild
                        ? $this->_optionUseImageFromConfigurableChild
                        : $this->_configHelper->getStoreConfig(
                            \Magento\ConfigurableProduct\Model\Product\Configuration\Item\ItemProductResolver::CONFIG_THUMBNAIL_SOURCE,
                            $storeId
                        );

                $childThumbnail = $product->getThumbnail();

                // Check if we need to use the configurable child image and if child has own image
                if($optionUseImageFromConfigurableChild === \Magento\Catalog\Model\Config\Source\Product\Thumbnail::OPTION_USE_OWN_IMAGE && $childThumbnail && $childThumbnail !== "no_selection") {
                    $image = $childThumbnail;
                } else { // Otherwise keep parent image
                    $image = $quoteProduct->getThumbnail();
                }

                $imageUrl = $this->_mediaUrl[$storeId] . "catalog/product" . $image;
                $absoluteUrl = $quoteProduct->getProductUrl(); // Always take parent product URL
            }

            return [
                "id_product" => (string) $product->getId(),
                "id_product_attribute" => (string) $product->getAttributeSetId(),
                "cart_quantity" => (string) $quoteItem->getQty(),
                "id_shop" => (string) $storeId,
                "reference" => (string) $product->getSku(),
                "reduction" => (string) $quoteItem->getDiscountAmount(),
                "reduction_type" => "",
                "total_wt" => $this->_formatPrice($quoteItem->getBaseRowTotalInclTax()),
                "total_unit_wt" => $this->_formatPrice($quoteItem->getBasePriceInclTax()),
                "product_type" => $quoteProduct->getTypeId(),
                "name" => $quoteProduct->getName(),
                "absolute_url" => $absoluteUrl,
                "image_url" => $imageUrl,
                "id_category_default" => $this->_formatterHelper->getLowerCategory(
                    $product->getCategoryIds(),
                    $product->getStoreId()
                )
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
                    "name" => "customer_email",
                    "type" => "string"
                ],
                [
                    "name" => "customer_email_type",
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
