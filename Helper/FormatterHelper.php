<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper;

use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;

class FormatterHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var Data
     */
    protected $_taxHelper;

    /**
     * @var CategoryCollectionFactory
     */
    protected $_categoryCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var SerializerInterface
     */
    protected $_serializer;

    // store data
    /**
     * @var string
     */
    protected $_keySchema;

    public function __construct(
        Context $context,
        Data $taxHelper,
        CategoryCollectionFactory $categoryCollectionFactory,
        StoreManagerInterface $storeManager,
        SerializerInterface $serializer
    ) {
        parent::__construct($context);
        $this->_taxHelper = $taxHelper;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_storeManager = $storeManager;
        $this->_serializer = $serializer;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return float
     */
    public function getProductPriceIncludingTax($product, $price)
    {
        return $this->_taxHelper->getTaxPrice($product, $price, true);
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return float
     */
    public function getProductPriceExcludingTax($product, $price)
    {
        return $this->_taxHelper->getTaxPrice($product, $price, false);
    }

    /**
     * @param int $storeId
     * @return int|void
     */
    public function getWebsiteIdFromStore($storeId)
    {
        try {
            return $this->_storeManager->getStore($storeId)->getWebsiteId();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @param string[] $categoriesIds
     * @param string $storeId
     * @return string
     */
    public function getLowerCategory($categoriesIds, $storeId)
    {
        try {
            $storeRootCateg = $this->_storeManager->getStore($storeId)->getRootCategoryId();
        } catch (NoSuchEntityException $e) {
            return "";
        }

        return (string) $this->_categoryCollectionFactory->create()
            ->addFieldToFilter('entity_id', ["in" => $categoriesIds])
            ->addFieldToFilter('is_active', ["eq" => 1])
            ->addFieldToFilter('path', ["like" => "1/" .$storeRootCateg . "%"]) // only store category
            ->setOrder("level", 'DESC')
            ->getFirstItem()
            ->getId()
            ;
    }

    /**
     * @return false|string
     */
    public function getKeySchema()
    {
        if (!isset($this->_keySchema)) {
            $schema = [
                "type" => "record",
                "name" => "Key",
                "fields" => [
                    [
                        "name" => "id",
                        "type" => "string"
                    ]
                ],
            ];

            $this->_keySchema = $this->_serializer->serialize($schema);
        }

        return $this->_keySchema;
    }
}
