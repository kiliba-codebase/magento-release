<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;
use Magento\Customer\Model\Session;

class VisitRecorder extends \Magento\Framework\View\Element\Template
{

    const PRODUCT_PAGE = "catalog_product_view";
    const CATEGORY_PAGE = "catalog_category_view";

    /**
     * @var Registry
     */
    protected $_registry;

    /**
     * @var string
     */
    protected $_pageType;

    /**
     * @var Session
     */
    protected $_customerSession;

    public function __construct(
        Template\Context $context,
        Registry $registry,
        Session $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_registry = $registry;
        $this->_customerSession = $customerSession;
    }

    /**
     * @return string
     */
    public function getProductId()
    {
        if ($this->_getPageType() == self::PRODUCT_PAGE) {
            return $this->_registry->registry("current_product")->getId();
        }
        return "0";
    }

    /**
     * @return string
     */
    public function getCategoryId()
    {
        if ($this->_getPageType() == self::CATEGORY_PAGE) {
            return $this->_registry->registry("current_category")->getId();
        }
        return "0";
    }

    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    public function getCustomerIsLoggedIn()
    {
        return $this->_customerSession->isLoggedIn();
    }

    protected function _getPageType()
    {
        if (!isset($this->_pageType)) {
            $this->_pageType = $this->_request->getFullActionName();
        }

        return $this->_pageType;
    }
}
