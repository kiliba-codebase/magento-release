<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Controller\Record;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Kiliba\Connector\Model\Import\Visit as ImportVisit;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Customer\Model\Session;

class Visit extends \Magento\Framework\App\Action\Action
{

    /**
     * @var FormKeyValidator
     */
    protected $_formKeyValidator;

    /**
     * @var ImportVisit
     */
    protected $_visitImportModel;

    /**
     * @var SerializerInterface
     */
    protected $_serializer;

    /**
     * @var Session
     */
    protected $_customer;

    public function __construct(
        Context $context,
        FormKeyValidator $formKeyValidator,
        ImportVisit $visitImportModel,
        SerializerInterface $serializer,
        Session $customer
    ) {
        parent::__construct($context);
        $this->_formKeyValidator = $formKeyValidator;
        $this->_visitImportModel = $visitImportModel;
        $this->_serializer = $serializer;
        $this->_customer = $customer;
    }

    public function execute()
    {
        if (!$this->_formKeyValidator->validate($this->getRequest()) && $this->getRequest()->isPost()) {
            return;
        }

        if(!$this->_customer->isLoggedIn()) {
            return;
        }

        $data = [
            "url" => $this->_request->getParam("url"),
            "id_customer" => $this->_customer->getId(),
            "date" => date('Y-m-d H:i:s'),
            "id_product" => $this->_request->getParam("productId"),
            "id_category" => $this->_request->getParam("categoryId")
        ];

        $jsonData = $this->_serializer->serialize($data);
        $this->_visitImportModel->recordVisit($jsonData, $this->_request->getParam("storeId"));
    }
}
