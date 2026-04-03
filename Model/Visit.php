<?php


namespace Kiliba\Connector\Model;

use Kiliba\Connector\Api\Data\VisitInterface;
use Kiliba\Connector\Api\Data\VisitInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;

class Visit extends \Magento\Framework\Model\AbstractModel
{

    protected $visitDataFactory;

    protected $dataObjectHelper;

    protected $_eventPrefix = 'kiliba_connector_visit';

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param VisitInterfaceFactory $visitDataFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param \Kiliba\Connector\Model\ResourceModel\Visit $resource
     * @param \Kiliba\Connector\Model\ResourceModel\Visit\Collection $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        VisitInterfaceFactory $visitDataFactory,
        DataObjectHelper $dataObjectHelper,
        \Kiliba\Connector\Model\ResourceModel\Visit $resource,
        \Kiliba\Connector\Model\ResourceModel\Visit\Collection $resourceCollection,
        array $data = []
    ) {
        $this->visitDataFactory = $visitDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve visit model with visit data
     * @return VisitInterface
     */
    public function getDataModel()
    {
        $visitData = $this->getData();

        $visitDataObject = $this->visitDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $visitDataObject,
            $visitData,
            VisitInterface::class
        );

        return $visitDataObject;
    }
}