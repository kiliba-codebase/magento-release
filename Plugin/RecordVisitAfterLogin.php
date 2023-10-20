<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Plugin;

use Magento\Framework\Stdlib\CookieManagerInterface;
use Kiliba\Connector\Model\Import\Visit as ImportVisit;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;

class RecordVisitAfterLogin
{
    /**
     * @var CookieManagerInterface
     */
    protected $_cookieManager;

    /**
     * @var ImportVisit
     */
    protected $_visitImportModel;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var Session
     */
    protected $_customerSession;

    public function __construct(
        CookieManagerInterface $cookieManager,
        ImportVisit $visitImportModel,
        StoreManagerInterface $storeManager,
        Session $customerSession
    ) {
        $this->_cookieManager = $cookieManager;
        $this->_visitImportModel = $visitImportModel;
        $this->_storeManager = $storeManager;
        $this->_customerSession = $customerSession;
    }

    /**
     * Change redirect after login to home instead of dashboard.
     *
     * @param \Magento\Customer\Controller\Account\LoginPost $subject
     * @param \Magento\Framework\Controller\Result\Redirect $result
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function afterExecute(
        \Magento\Customer\Controller\Account\LoginPost $subject,
        \Magento\Framework\Controller\Result\Redirect $result
    ) {
        $recentlyViewProduct = $this->_cookieManager->getCookie("kiliba_recent");

        if (!empty($recentlyViewProduct) && $recentlyViewProduct != "{}") {
            $storeId = $this->_storeManager->getStore()->getId();
            $customerId = $this->_customerSession->getCustomerId();
            $this->_visitImportModel->addPreviousVisits($recentlyViewProduct, $customerId, $storeId);
        }

        return $result;
    }
}
