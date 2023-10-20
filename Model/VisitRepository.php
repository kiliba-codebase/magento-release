<?php

namespace Kiliba\Connector\Model;

use Kiliba\Connector\Api\Data\VisitInterfaceFactory;
use Kiliba\Connector\Api\Data\VisitSearchResultsInterfaceFactory;
use Kiliba\Connector\Api\VisitRepositoryInterface;
use Kiliba\Connector\Model\ResourceModel\Visit as ResourceVisit;
use Kiliba\Connector\Model\ResourceModel\Visit\CollectionFactory as VisitCollectionFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Store\Model\StoreManagerInterface;

class VisitRepository implements VisitRepositoryInterface
{

    protected $visitCollectionFactory;

    protected $dataObjectHelper;

    protected $extensibleDataObjectConverter;
    private $collectionProcessor;

    protected $visitFactory;

    protected $searchResultsFactory;

    protected $resource;

    protected $extensionAttributesJoinProcessor;

    protected $dataObjectProcessor;

    protected $dataVisitFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param ResourceVisit $resource
     * @param VisitFactory $visitFactory
     * @param VisitInterfaceFactory $dataVisitFactory
     * @param VisitCollectionFactory $visitCollectionFactory
     * @param VisitSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param DataObjectProcessor $dataObjectProcessor
     * @param StoreManagerInterface $storeManager
     * @param CollectionProcessorInterface $collectionProcessor
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     */
    public function __construct(
        ResourceVisit $resource,
        VisitFactory $visitFactory,
        VisitInterfaceFactory $dataVisitFactory,
        VisitCollectionFactory $visitCollectionFactory,
        VisitSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor,
        StoreManagerInterface $storeManager,
        CollectionProcessorInterface $collectionProcessor,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter
    ) {
        $this->resource = $resource;
        $this->visitFactory = $visitFactory;
        $this->visitCollectionFactory = $visitCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataVisitFactory = $dataVisitFactory;
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
        \Kiliba\Connector\Api\Data\VisitInterface $visit
    ) {

        $visitData = $this->extensibleDataObjectConverter->toNestedArray(
            $visit,
            [],
            \Kiliba\Connector\Api\Data\VisitInterface::class
        );

        $visitModel = $this->visitFactory->create()->setData($visitData);

        try {
            $this->resource->save($visitModel);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the visit: %1',
                $exception->getMessage()
            ));
        }
        return $visitModel->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function get($visitId)
    {
        $visit = $this->visitFactory->create();
        $this->resource->load($visit, $visitId);
        if (!$visit->getId()) {
            throw new NoSuchEntityException(__('Visit with id "%1" does not exist.', $visitId));
        }
        return $visit->getDataModel();
    }

    /**
     * {@inheritdoc}
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        $collection = $this->visitCollectionFactory->create();

        $this->extensionAttributesJoinProcessor->process(
            $collection,
            \Kiliba\Connector\Api\Data\VisitInterface::class
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
        \Kiliba\Connector\Api\Data\VisitInterface $visit
    ) {
        try {
            $visitModel = $this->visitFactory->create();
            $this->resource->load($visitModel, $visit->getVisitId());
            $this->resource->delete($visitModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the Visit: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($visitId)
    {
        return $this->delete($this->get($visitId));
    }
}
