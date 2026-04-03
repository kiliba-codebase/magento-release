<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model;

use Kiliba\Connector\Api\Data\LogInterface;
use Kiliba\Connector\Api\Data\LogInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;

class Log extends \Magento\Framework\Model\AbstractModel
{

    protected $dataObjectHelper;

    protected $logDataFactory;

    protected $_eventPrefix = 'kiliba_connector_log';

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param LogInterfaceFactory $logDataFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param \Kiliba\Connector\Model\ResourceModel\Log $resource
     * @param \Kiliba\Connector\Model\ResourceModel\Log\Collection $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        LogInterfaceFactory $logDataFactory,
        DataObjectHelper $dataObjectHelper,
        \Kiliba\Connector\Model\ResourceModel\Log $resource,
        \Kiliba\Connector\Model\ResourceModel\Log\Collection $resourceCollection,
        array $data = []
    ) {
        $this->logDataFactory = $logDataFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve log model with log data
     * @return LogInterface
     */
    public function getDataModel()
    {
        $logData = $this->getData();

        $logDataObject = $this->logDataFactory->create();
        $this->dataObjectHelper->populateWithArray(
            $logDataObject,
            $logData,
            LogInterface::class
        );

        return $logDataObject;
    }
}
