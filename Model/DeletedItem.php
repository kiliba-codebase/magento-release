<?php


namespace Kiliba\Connector\Model;

use Kiliba\Connector\Api\Data\DeletedItemInterface;
use Kiliba\Connector\Api\Data\DeletedItemInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;

class DeletedItem extends \Magento\Framework\Model\AbstractModel
{

    protected $deleteditemDataFactory;

    protected $dataObjectHelper;

    protected $_eventPrefix = 'kiliba_connector_deleteditem';

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param DeletedItemInterfaceFactory $deleteditemDataFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param \Kiliba\Connector\Model\ResourceModel\DeletedItem $resource
     * @param \Kiliba\Connector\Model\ResourceModel\DeletedItem\Collection $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        DeletedItemInterfaceFactory $deleteditemDataFactory,
        DataObjectHelper $dataObjectHelper,
        \Kiliba\Connector\Model\ResourceModel\DeletedItem $resource,
        \Kiliba\Connector\Model\ResourceModel\DeletedItem\Collection $resourceCollection,
        array $data = []
    ) {
        $this->deleteditemDataFactory = $deleteditemDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve deleteditem model with deleteditem data
     * @return DeletedItemInterface
     */
    public function getDataModel()
    {
        $deleteditemData = $this->getData();

        $deleteditemDataObject = $this->deleteditemDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $deleteditemDataObject,
            $deleteditemData,
            DeletedItemInterface::class
        );

        return $deleteditemDataObject;
    }
}