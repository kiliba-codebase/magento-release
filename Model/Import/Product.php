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
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Module\Manager;
use Magento\Store\Model\StoreManagerInterface;

class Product extends AbstractModel
{

    const PRODUCT_TYPE_SYNC = [
        \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE,
        \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL,
        \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE,
        \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE
    ];

    /**
     * @var ProductRepositoryInterface
     */
    protected $_productRepository;

    /**
     * @var StockItemRepository
     */
    protected $_stockItemRepository;

    /**
     * @var CollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @var ProductFactory
     */
    protected $_productFactory;

    /**
     * @var Configurable
     */
    protected $_configurable;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var StockItemCriteriaInterfaceFactory
     */
    private $_criteriaInterfaceFactory;
    
    /**
     * @var SortOrderBuilder
     */
    private $_sortOrderBuilder;

    /**
     * @var StockConfigurationInterface
     */
    private $_stockConfiguration;

    /**
     * @var ObjectManagerInterface
     */
    private $_objectManager;

    /**
     * @var Manager
     */
    protected $_moduleManager;

    /**
     * @var \Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku
     */
    private $_getSalableQuantityDataBySku;

    protected $_coreTable = "catalog_product_entity";

    // store data
    /**
     * @var string[]
     */
    protected $_productUrlSuffix;
    protected $_mediaUrl;

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        ResourceConnection $resourceConnection,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $productCollectionFactory,
        ProductFactory $productFactory,
        StockItemRepository $stockItemRepository,
        Configurable $configurable,
        StoreManagerInterface $storeManager,
        StockItemCriteriaInterfaceFactory $criteriaInterfaceFactory,
        StockConfigurationInterface $stockConfiguration,
        ObjectManagerInterface $objectManager,
        Manager $moduleManager
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
        $this->_productRepository = $productRepository;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_productFactory = $productFactory;
        $this->_stockItemRepository = $stockItemRepository;
        $this->_configurable = $configurable;
        $this->_storeManager = $storeManager;
        $this->_criteriaInterfaceFactory = $criteriaInterfaceFactory;
        $this->_sortOrderBuilder = $sortOrderBuilder;
        $this->_stockConfiguration = $stockConfiguration;
        $this->_objectManager = $objectManager;
        $this->_moduleManager = $moduleManager;

        if($this->_moduleManager->isEnabled('Magento_InventorySalesAdminUi')) {
            try {
                $this->_getSalableQuantityDataBySku = $this->_objectManager->create("Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku");
            } catch(\Exception $e) {}
        }
    }

    /**
     * @param int $entityId
     * @param int $websiteId
     * @return \Magento\Catalog\Api\Data\ProductInterface
     * @throws NoSuchEntityException
     */
    public function getEntity($entityId, $websiteId)
    {
        return $this->_productRepository->getById(
            $entityId,
            false,
            $websiteId
        );
    }

    public function getTotalCount($websiteId) {
        return $this->_productCollectionFactory->create()
            ->addWebsiteFilter($websiteId)
            ->addFieldToFilter("type_id", ["in" => self::PRODUCT_TYPE_SYNC])
            ->getSize();
    }

    /**
     * @param SearchCriteriaBuilder $searchCriteria
     * @param $websiteId
     * @return \Magento\Catalog\Api\Data\ProductInterface[]|string
     */
    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $sortOrder = $this->_sortOrderBuilder->setField('entity_id')->setDirection('ASC')->create();

        $searchCriteria
            ->addFilter("website_id", $websiteId)
            ->addFilter("type_id", self::PRODUCT_TYPE_SYNC, "in")
            ->setSortOrders([$sortOrder]);

        return $this->_productRepository->getList($searchCriteria->create())->getItems();
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection
     * @return bool|mixed
     */
    public function prepareDataForApi($collection, $websiteId)
    {
        $productsData = [];
        try {
            foreach ($collection as $product) {
                if ($product->getId()) {
                    $data = $this->formatData($product, $websiteId);
                    if (!array_key_exists("error", $data)) {
                        $productsData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "Format data product";
            if (isset($product)) {
                $message .= " product id " . $product->getId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }
        return $productsData;
    }

    public function computeProductPrice($product) {
        return array(
            "price" => $this->_formatterHelper->getProductPriceExcludingTax($product, $product->getPrice()),
            "price_wt" => $this->_formatterHelper->getProductPriceIncludingTax($product, $product->getPrice()),
            "special_price" => $this->_getProductDiscountPrice($product),
            "special_price_wt" => $this->_getProductDiscountPrice($product, true),
        );
    }

    /**
     * @param \Magento\Catalog\Model\Product|\Magento\Catalog\Api\Data\ProductInterface $product
     * @param int $websiteId
     * @return array
     */
    public function formatData($product, $websiteId)
    {
        try {
            if (!isset($this->_productUrlSuffix[$websiteId])) {
                $website = $this->_configHelper->getWebsiteById($websiteId);
                $store = $website->getDefaultStore();
                $this->_productUrlSuffix[$websiteId] = $this->_configHelper->getValueFromCoreConfig(
                    ProductUrlPathGenerator::XML_PATH_PRODUCT_URL_SUFFIX,
                    $store->getId()
                );
            }

            if (!isset($this->_mediaUrl[$websiteId])) {
                $website = $this->_configHelper->getWebsiteById($websiteId);
                $store = $website->getDefaultStore();
                $this->_mediaUrl[$websiteId] = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
            }

            $productParent = $this->_getProductParent($product, $product->getStoreId());

            $parentId = "";
            $parentVisibility = "";
            $imageUrl = "";
            $description = $product->getDescription();
            $name = $product->getName();
            $url = $product->getProductUrl();
            $urlKey = $product->getUrlKey() . $this->_productUrlSuffix[$websiteId];
            $image = $product->getImage();
            $productVisibility = $product->getVisibility();
            $isSaleable = $product->isSaleable();

            if (!empty($productParent)) {
                $urlParams = $this->_getAttributeUrl($product, $productParent);

                $parentId = $productParent->getId();
                $parentVisibility = $productParent->getVisibility();
                $description = $productParent->getDescription();
                $name = $productParent->getName();

                if ($productVisibility == Visibility::VISIBILITY_NOT_VISIBLE) {
                    $url = $productParent->getProductUrl() . $urlParams;
                    $urlKey = $productParent->getUrlKey() . $this->_productUrlSuffix[$websiteId] . $urlParams;

                    if ($productParent->isSaleable() == 0) {
                        $isSaleable = 0;
                    }
                }

                if (!$image || $image == "no_selection") {
                    $image = $productParent->getImage();
                }
            }

            if ($image && $image != "no_selection") {
                $imageUrl = $this->_mediaUrl[$websiteId] . "catalog/product" . $image;
            }

            $salableQuantity = [];
            try {
                if($this->_getSalableQuantityDataBySku) {
                    $salableQuantityData = $this->_getSalableQuantityDataBySku->execute($product->getSku());
                    foreach($salableQuantityData as $sqty) {
                        $salableQuantity[] = [
                            "stock_id" => (string) (isset($sqty["stock_id"]) ? $sqty["stock_id"] : ""),
                            "stock_name" => (string) ($sqty["stock_name"] ? $sqty["stock_name"] : ""),
                            "qty" => (string) (isset($sqty["qty"]) ? $sqty["qty"] : ""),
                            "manage_stock" => (string) (isset($sqty["manage_stock"]) ? $this->_formatBoolean($sqty["manage_stock"]) : "")
                        ];
                    }
                }
            } catch (\Exception $e) {}

            $stockItemConfiguration = $this->getProductStockItem($product);
            if(empty($stockItemConfiguration)){
                $allowBackorders = false;
            }else{
                $allowBackorders = $stockItemConfiguration->getBackorders() == 0 ? false : true;
            }

            $images = array();
            foreach($product->getMediaGalleryImages() as $child){
                $images[] = [
                    "url" => (string) $child->getUrl()
                ];
            }

            $translatedNameMap = array();
            $translatedDescriptionMap = array();
            foreach($this->_website->getStores() as $store) {
                $translatedStoreId = $store->getId();
                $translatedStoreLocale = $this->_configHelper->getStoreLocale($translatedStoreId);

                $translatedProductId = $productParent ? $productParent->getId() : $product->getId();
                $translatedProduct = $this->_productRepository->getById($product->getId(), false, $translatedStoreId);

                $translatedName = (string) $translatedProduct->getName();
                $translatedDescription = (string) $translatedProduct->getDescription();

                // fr_FR / fr_LU / de_DE
                // $translatedNameMap[$translatedStoreLocale] = $translatedName;
                // $translatedDescriptionMap[$translatedStoreLocale] = $translatedDescription;

                // fr_FR -> fr / fr_LU -> fr / de_DE -> de
                $shortLang = $this->_configHelper->extractLangFromLocale($translatedStoreLocale);
                if(!isset($translatedNameMap[$shortLang])) {
                    $translatedNameMap[$shortLang] = $translatedName;
                }
                if(!isset($translatedDescriptionMap[$shortLang])) {
                    $translatedDescriptionMap[$shortLang] = $translatedDescription;
                }
            }

            $prices = $this->computeProductPrice($product);

            $data = [
                "id" => (string) $product->getId(),
                "reference" => (string) $product->getSku(),
                "id_shop_group" => (string) $websiteId,
                "id_shop" => (string)$product->getStoreId(),
                "id_attribute" => (string) $product->getAttributeSetId(),
                "parent_id" => (string) $parentId,
                "product_type" => (string) $product->getTypeId(),
                "on_sale" => $this->_formatBoolean($isSaleable),
                "id_category_default" => $this->_formatterHelper->getLowerCategory(
                    $product->getCategoryIds(),
                    $product->getStoreId()
                ),
                "category_ids" => $product->getCategoryIds(),
                "minimal_quantity" => (string) (empty($stockItemConfiguration)? 1 : $stockItemConfiguration->getMinSaleQty()),
                "active" => (string) $product->getStatus(),
                "visibility" => (string) $parentVisibility,
                "visibility_simple" => (string) $productVisibility,
                "price" => $this->_formatPrice($prices["price"]),
                "price_wt" => $this->_formatPrice($prices["price_wt"]),
                "special_price" => $this->_formatPrice($prices["special_price"]),
                "special_price_wt" => $this->_formatPrice($prices["special_price_wt"]),
                "special_from_date" => (string) $product->getSpecialFromDate(),
                "special_to_date" => (string) $product->getSpecialToDate(),
                "available_for_order" => "1",
                "available_when_out_of_stock" => $this->_formatBoolean($allowBackorders), // simple or parent ?
                "date_add" => (string) $product->getCreatedAt(),
                "date_update" => (string) $product->getUpdatedAt(),
                "physical_quantity" => (string)  (empty($stockItemConfiguration)? -1 : $stockItemConfiguration->getQty()),
                "salable_quantity" => $salableQuantity,
                "depends_on_stock" => $this->_formatBoolean(empty($stockItemConfiguration)? 0 : $stockItemConfiguration->getManageStock()),
                "out_of_stock" => $this->_formatBoolean( empty($stockItemConfiguration)? 1 : !$stockItemConfiguration->getIsInStock()),
                "qty_increment" => (string)  (empty($stockItemConfiguration)? 1 : $stockItemConfiguration->getQtyIncrements()),
                "image_url" => (string) $imageUrl, // simple if exist else parent
                "images" => $images,
                "absolute_url" => (string) $url,
                "relative_url" => (string) $urlKey,
                "name" => $translatedNameMap,
                "description" => $translatedDescriptionMap, // TODO : need to remove html ??
                "deleted" => $this->_formatBoolean(false), // always false when use this function
            ];

            return $data;
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format product data, id = " . $product->getId(),
                $e->getMessage(),
                $websiteId
            );
            return ["error" => $e->getMessage()];
        }
    }

    /**
     * @param int $productId
     * @param int $websiteId
     * @return array
     */
    public function formatDeletedProduct($productId, $websiteId)
    {
        $data = [
            "id" => $productId,
            "reference" => "",
            "id_shop_group" => "",
            "id_shop" => $websiteId,
            "id_attribute" => "",
            "parent_id" => "",
            "product_type" => "",
            "on_sale" => "",
            "id_category_default" => "",
            "minimal_quantity" => "",
            "active" => "",
            "visibility" => "",
            "visibility_simple" => "",
            "price" => "",
            "price_wt" => "",
            "special_price" => "",
            "special_price_wt" => "",
            "special_from_date" => "",
            "special_to_date" => "",
            "available_for_order" => "",
            "available_when_out_of_stock" => "",
            "date_add" => "",
            "date_update" => "",
            "physical_quantity" => "",
            "depends_on_stock" => "",
            "out_of_stock" => "",
            "qty_increment" => "",
            "image_url" => "",
            "images" => [],
            "absolute_url" => "",
            "relative_url" => "",
            "name" => "",
            "description" => "",
            "deleted" => $this->_formatBoolean(true), // always true when use this function
        ];

        return $data;
    }

    /**
     * @param $product
     * @param $websiteId
     * @return \Magento\Catalog\Api\Data\ProductInterface|null
     */
    protected function _getProductParent($product, $websiteId)
    {
        if (in_array($product->getTypeId(), self::PRODUCT_TYPE_SYNC)) {
            $parentProductIds = $this->_configurable->getParentIdsByChild($product->getId());
            if (!empty($parentProductIds)) {
                // need to specify store ?
                try {
                    return $this->_productRepository->getById($parentProductIds[0], false, $websiteId);
                } catch (NoSuchEntityException $e) {
                    return null;
                }
            }
        }

        return null;
    }

    public function getProductStockItem($product)
    {
        try {
            /** @var \Magento\CatalogInventory\Api\StockItemCriteriaInterface  $criteria */
            $criteria = $this->_criteriaInterfaceFactory->create();
            $criteria->setScopeFilter($this->_stockConfiguration->getDefaultScopeId());

            $criteria->setProductsFilter($product->getId());
            $criteria->setLimit(0, 1);
            return current($this->_stockItemRepository->getList($criteria)->getItems());
        } catch (\Exception $e) {
            return $product->getExtensionAttributes()->getStockItem();
        }
    }

    /**
     * @param \Magento\Catalog\Model\Product|\Magento\Catalog\Api\Data\ProductInterface $product
     * @param bool $withTax
     */
    protected function _getProductDiscountPrice($product, $withTax = false)
    {
        $discountPrice = $finalPrice = null;
        $tierPrice = $product->getTierPrice(1); // all group and not logged in
        if ($product->getFinalPrice() < $product->getPrice()) {
            $discountPrice = $product->getFinalPrice();
        }

        if (!empty($discountPrice) || !empty($tierPrice)) {
            $finalPrice = min(array_diff([$discountPrice, $tierPrice], [null])); // take smaller value but not null
        } else {
            $finalPrice = 0;
        }

        if ($withTax) {
            return $this->_formatterHelper->getProductPriceIncludingTax(
                $product,
                $finalPrice
            );
        }

        return $this->_formatterHelper->getProductPriceExcludingTax(
            $product,
            $finalPrice
        );
    }

    /**
     * @param \Magento\Catalog\Model\Product|\Magento\Catalog\Api\Data\ProductInterface $childProduct
     * @param \Magento\Catalog\Api\Data\ProductInterface $parentProduct
     * @return string
     */
    protected function _getAttributeUrl($childProduct, $parentProduct)
    {
        $childConfigurations = [];
        $productAttributeOptions = $this->_configurable->getConfigurableAttributesAsArray($parentProduct);

        $i = 0;
        foreach ($productAttributeOptions as $attributeOption) {
            $childConfigurations[$i]["attribute_id"] = $attributeOption["attribute_id"];

            $values = $attributeOption["values"];
            $currentValue = $childProduct->getData($attributeOption["attribute_code"]);

            foreach ($values as $value) {
                if ($value["value_index"] == $currentValue) {
                    $childConfigurations[$i]["option_id"] = $value["value_index"];
                }
            }
            $i++;
        }

        $urlParams = "";
        foreach ($childConfigurations as $configData) {
            if (isset($configData["attribute_id"]) && isset($configData["option_id"])) {
                $urlParams .= empty($urlParams) ? "#" : "&";
                $urlParams .= $configData["attribute_id"];
                $urlParams .= "=";
                $urlParams .= $configData["option_id"];
            }
        }

        return $urlParams;
    }

    /**
     * @return false|string
     */
    public function getSchema()
    {
        $schema = [
            "type" => "record",
            "name" => "Product",
            "fields" => [
                [
                    "name" => "id",
                    "type" => "string"
                ],
                [
                    "name" => "reference",
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
                    "name" => "id_attribute",
                    "type" => "string"
                ],
                [
                    "name" => "parent_id",
                    "type" => "string"
                ],
                [
                    "name" => "product_type",
                    "type" => "string"
                ],
                [
                    "name" => "on_sale",
                    "type" => "string"
                ],
                [
                    "name" => "id_category_default",
                    "type" => "string"
                ],
                [
                    "name" => "category_ids",
                    "type" => "array",
                    "items" => [
                        "type" => "string"
                    ]
                ],
                [
                    "name" => "minimal_quantity",
                    "type" => "string"
                ],
                [
                    "name" => "active",
                    "type" => "string"
                ],
                [
                    "name" => "visibility",
                    "type" => "string"
                ],
                [
                    "name" => "visibility_simple",
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
                    "name" => "special_price",
                    "type" => "string"
                ],
                [
                    "name" => "special_price_wt",
                    "type" => "string",
                    "default" => "0.0000"
                ],
                [
                    "name" => "special_from_date",
                    "type" => "string"
                ],
                [
                    "name" => "special_to_date",
                    "type" => "string"
                ],
                [
                    "name" => "available_for_order",
                    "type" => "string"
                ],
                [
                    "name" => "available_when_out_of_stock",
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
                    "name" => "physical_quantity",
                    "type" => "string"
                ],
                [
                    "name" => "salable_quantity",
                    "type" => "array",
                    "items" => [
                        "type" => "record",
                        "fields" => [
                            "stock_id" => "string",
                            "stock_name" => "string",
                            "qty" => "string",
                            "manage_stock" => "string"
                        ]
                    ]
                ],
                [
                    "name" => "depends_on_stock",
                    "type" => "string"
                ],
                [
                    "name" => "out_of_stock",
                    "type" => "string"
                ],
                [
                    "name" => "qty_increment",
                    "type" => "string"
                ],
                [
                    "name" => "image_url",
                    "type" => "string"
                ],
                [
                    "name" => "images",
                    "type" => "array",
                    "items" => [
                        "type" => "record",
                        "fields" => [
                            "name" => "url",
                            "type" => "string"
                        ]
                    ]
                ],
                [
                    "name" => "absolute_url",
                    "type" => "string"
                ],
                [
                    "name" => "relative_url",
                    "type" => "string"
                ],
                [
                    "name" => "name",
                    "type" => "string"
                ],
                [
                    "name" => "description",
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
