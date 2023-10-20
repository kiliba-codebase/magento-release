<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;

class Category extends AbstractModel
{
    /**
     * @var CategoryRepositoryInterface
     */
    protected $_categoryRepository;

    /**
     * @var CollectionFactory
     */
    protected $_categoryCollectionFactory;

    protected $_coreTable = "catalog_category_entity";

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        CategoryRepositoryInterface $categoryRepository,
        CollectionFactory $categoryCollectionFactory
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
        $this->_categoryRepository = $categoryRepository;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @param int $entityId
     * @return \Magento\Catalog\Api\Data\CategoryInterface
     * @throws NoSuchEntityException
     */
    public function getEntity($entityId)
    {
        return $this->_categoryRepository->get($entityId);
    }

    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $criteria = $searchCriteria->create();

        return $this->_categoryCollectionFactory
            ->create()
            ->addOrderField("id")
            ->addAttributeToSelect('*')
            ->setPage($criteria->getCurrentPage(), $criteria->getPageSize());

    }

    public function getTotalCount($websiteId) {
        return $this->_categoryCollectionFactory
            ->create()
            ->getSize();
    }

    public function prepareDataForApi($collection, $websiteId)
    {
        $categoriesData = [];
        try {
            foreach ($collection as $category) {
                if ($category->getId()) {
                    $data = $this->formatData($category, $websiteId);
                    if (!array_key_exists("error", $data)) {
                        $categoriesData[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "Format data category";
            if (isset($category)) {
                $message .= " category id " . $category->getId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }
        return $categoriesData;
    }


    /**
     * @param \Magento\Catalog\Api\Data\CategoryInterface $category
     * @param int $websiteId
     * @return array
     */
    public function formatData($category, $websiteId)
    {
        try {
            $data = [
                "id" => (string)$category->getId(),
                "name" => (string)$category->getName(),
                "parent_id" => (string)$category->getParentId(),
                "isActive" => (string)$category->getIsActive(),
                "position" => (string)$category->getPosition(),
                "level" => (string)$category->getLevel(),
                "product_count" => (string)$category->getProductCount(),
                "path" => (string)$category->getPath(),
                "available_sort_by" => $category->getAvailableSortBy(),
                "include_in_menu" => (string)$category->getIncludeInMenu(),
                "created_at" => (string)$category->getCreatedAt(),
                "updated_at" => (string)$category->getUpdatedAt(),
            ];
            return $data;
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Format category data, id = " . $category->getId(),
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
            "name" => "category",
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
                    "name" => "parent_id",
                    "type" => "string"
                ],
                [
                    "name" => "isActive",
                    "type" => "string"
                ],
                [
                    "name" => "position",
                    "type" => "string"
                ],
                [
                    "name" => "level",
                    "type" => "string"
                ],
                [
                    "name" => "product_count",
                    "type" => "string"
                ],
                [
                    "name" => "path",
                    "type" => "string"
                ],
                [
                    "name" => "available_sort_by",
                    "type" => "array",
                    "items" => [
                        "type" => "string"
                    ]
                ],
                [
                    "name" => "include_in_menu",
                    "type" => "string"
                ],
                [
                    "name" => "created_at",
                    "type" => "string"
                ],
                [
                    "name" => "updated_at",
                    "type" => "string"
                ],
            ],
        ];

        return $this->_serializer->serialize($schema);
    }
}