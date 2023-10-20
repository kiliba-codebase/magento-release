<?php


namespace Kiliba\Connector\Model;

use Kiliba\Connector\Api\Data\DeletedItemInterfaceFactory;
use Kiliba\Connector\Api\Data\DeletedItemSearchResultsInterfaceFactory;
use Kiliba\Connector\Api\DeletedItemRepositoryInterface;
use Kiliba\Connector\Model\ResourceModel\DeletedItem as ResourceDeletedItem;
use Kiliba\Connector\Model\ResourceModel\DeletedItem\CollectionFactory as DeletedItemCollectionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\StoreManagerInterface;

class DeletedItemRepository implements DeletedItemRepositoryInterface
{

    protected $deletedItemFactory;

    protected $dataObjectHelper;

    protected $extensibleDataObjectConverter;
    protected $deletedItemCollectionFactory;

    private $collectionProcessor;

    private $storeManager;

    protected $dataDeletedItemFactory;

    protected $searchResultsFactory;

    protected $resource;

    protected $extensionAttributesJoinProcessor;

    protected $dataObjectProcessor;


    /**
     * @param ResourceDeletedItem $resource
     * @param DeletedItemFactory $deletedItemFactory
     * @param DeletedItemInterfaceFactory $dataDeletedItemFactory
     * @param DeletedItemCollectionFactory $deletedItemCollectionFactory
     * @param DeletedItemSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param DataObjectProcessor $dataObjectProcessor
     * @param StoreManagerInterface $storeManager
     * @param CollectionProcessorInterface $collectionProcessor
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     */
    public function __construct(
        ResourceDeletedItem $resource,
        DeletedItemFactory $deletedItemFactory,
        DeletedItemInterfaceFactory $dataDeletedItemFactory,
        DeletedItemCollectionFactory $deletedItemCollectionFactory,
        DeletedItemSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager,
        CollectionProcessorInterface $collectionProcessor,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter
    ) {
        $this->resource = $resource;
        $this->deletedItemFactory = $deletedItemFactory;
        $this->deletedItemCollectionFactory = $deletedItemCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataDeletedItemFactory = $dataDeletedItemFactory;
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->storeManager = $storeManager;
        $this->collectionProcessor = $collectionProcessor;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function save(
        \Kiliba\Connector\Api\Data\DeletedItemInterface $deletedItem
    ) {
        $deletedItemData = $this->extensibleDataObjectConverter->toNestedArray(
            $deletedItem,
            [],
            \Kiliba\Connector\Api\Data\DeletedItemInterface::class
        );

        $deletedItemModel = $this->deletedItemFactory->create()->setData($deletedItemData);

        try {
            $this->resource->save($deletedItemModel);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the deletedItem: %1',
                $exception->getMessage()
            ));
        }
        return $deletedItemModel->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function get($deletedItemId)
    {
        $deletedItem = $this->deletedItemFactory->create();
        $this->resource->load($deletedItem, $deletedItemId);
        if (!$deletedItem->getId()) {
            throw new NoSuchEntityException(__('DeletedItem with id "%1" does not exist.', $deletedItemId));
        }
        return $deletedItem->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        $collection = $this->deletedItemCollectionFactory->create();

        $this->extensionAttributesJoinProcessor->process(
            $collection,
            \Kiliba\Connector\Api\Data\DeletedItemInterface::class
        );

        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

        $items = [];
        foreach ($collection as $model) {
            $items[] = $model->getDataModel();
        }

        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(
        \Kiliba\Connector\Api\Data\DeletedItemInterface $deletedItem
    ) {
        try {
            $deletedItemModel = $this->deletedItemFactory->create();
            $this->resource->load($deletedItemModel, $deletedItem->getDeleteditemId());
            $this->resource->delete($deletedItemModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the DeletedItem: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($deletedItemId)
    {
        return $this->delete($this->get($deletedItemId));
    }
}
