<?php


namespace Kiliba\Connector\Model\Import;


use Kiliba\Connector\Api\Data\DeletedItemInterface;
use Kiliba\Connector\Api\Data\DeletedItemInterfaceFactory;
use Kiliba\Connector\Api\DeletedItemRepositoryInterface;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;

class DeletedItem extends AbstractModel
{
    /**
     * @var DeletedItemInterfaceFactory
     */
    protected $_dataDeletedItemFactory;

    /**
     * @var DeletedItemRepositoryInterface
     */
    protected $_deletedItemRepository;

    /**
     * @var Product
     */
    protected $_dataProductManager;

    /**
     * @var Customer
     */
    protected  $_dataCustomerManager;

    /**
     * @var PriceRule
     */
    protected  $_dataPriceRuleManager;

    // store data
    protected $_currentEntityType;

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaCaller $kilibaCaller,
        KilibaLogger $kilibaLogger,
        SerializerInterface $serializer,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ResourceConnection $resourceConnection,
        DeletedItemInterfaceFactory $dataDeletedItemFactory,
        DeletedItemRepositoryInterface $deletedItemRepository,
        Product $dataProductManager,
        Customer $dataCustomerManager,
        PriceRule $dataPriceRuleManager
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
        $this->_dataDeletedItemFactory = $dataDeletedItemFactory;
        $this->_deletedItemRepository = $deletedItemRepository;
        $this->_dataProductManager = $dataProductManager;
        $this->_dataCustomerManager = $dataCustomerManager;
        $this->_dataPriceRuleManager = $dataPriceRuleManager;
    }

    /**
     * @param int $entityId
     * @param string $entityType
     */
    public function recordDeletion($entityId, $entityType)
    {
        /** @var DeletedItemInterface $deletedItem */
        $deletedItem = $this->_dataDeletedItemFactory->create();
        $deletedItem->setEntityId($entityId)
            ->setEntityType($entityType)
            ->setStoreId(0);
        try {
            $this->_deletedItemRepository->save($deletedItem);
        } catch (LocalizedException $e) {
        }
    }

    public function getDeletedCollection($entityType, $website, $limit, $offset, $createdAt = null, $withData = true)
    {
        $this->_currentEntityType = $entityType;
        return $this->getSyncCollection($website, $limit, $offset, $createdAt, null, $withData);
    }

    protected function getModelCollection($searchCriteria, $websiteId)
    {
        $searchCriteria
            ->addFilter("entity_type", $this->_currentEntityType);
            
        return $this->_deletedItemRepository->getList($searchCriteria->create())->getItems();
    }

    /**
     * @param \Kiliba\Connector\Model\ResourceModel\DeletedItem\Collection $collection
     * @return array
     */
    public function prepareDataForApi($collection, $websiteId)
    {
        $deletedDatas = [];
        try {
            /** @var DeletedItemInterface $deletedItem */
            foreach ($collection as $deletedItem) {
                if ($deletedItem->getDeleteditemId()) {
                    switch ($this->_currentEntityType) {
                        case "product":
                            $data = $this->_dataProductManager->formatDeletedProduct(
                                $deletedItem->getEntityId(),
                                $websiteId
                            );
                            break;
                        case "customer":
                            $data = $this->_dataCustomerManager->formatDeletedCustomer(
                                $deletedItem->getEntityId(),
                                $websiteId
                            );
                            break;
                        case "priceRule":
                            $data = $this->_dataPriceRuleManager->formatDeletedPriceRule(
                                $deletedItem->getEntityId(),
                                $websiteId
                            );
                            break;
                    }
                    if (!array_key_exists("error", $data)) {
                        $deletedDatas[] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $message = "Format data deleted " . $this->_currentEntityType;
            if (isset($deletedItem)) {
                $message .= $this->_currentEntityType . " id " . $deletedItem->getDeleteditemId();
            }
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                $message,
                $e->getMessage(),
                $websiteId
            );
        }
        return $deletedDatas;
    }

    public function getTotalCount($websiteId)
    {
        $connection = $this->_resourceConnection->getConnection();
        $table = $this->_resourceConnection->getTableName("kiliba_connector_deleteditem");

        $select = $connection->select()
            ->from(
                $table,
                [new \Zend_Db_Expr('COUNT(*)')]
            )
            ->where(
                "entity_type = :entity_type"
            );

        return $connection->fetchOne(
            $select,
            ["entity_type" => $this->_currentEntityType]
        );
    }

    public function cleanDeletedItem()
    {
        $cleanBefore = date_create("15 days ago")->format("Y-m-d");
        $this->_searchCriterieBuilder->addFilter(DeletedItemInterface::CREATED_AT, $cleanBefore, "lteq");

        $deletedToClean = $this->_deletedItemRepository->getList($this->_searchCriterieBuilder->create());

        $numberCleaned = 0;
        foreach ($deletedToClean->getItems() as $visit) {
            try {
                $this->_deletedItemRepository->delete($visit);
                $numberCleaned++;
            } catch (LocalizedException $e) {
                $this->_kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_ERROR,
                    "Clean old deleted item before date = " . $cleanBefore,
                    $e->getMessage(),
                    0
                );
            }
        }

        $this->_kilibaLogger->addLog(
            KilibaLogger::LOG_TYPE_INFO,
            "Clean old deleted item before date = " . $cleanBefore,
            $numberCleaned . " deleted item removed / " . $deletedToClean->getTotalCount() . " to delete",
            0
        );
        return $numberCleaned;
    }
}
