<?php
/**
 * Copyright © Kiliba. All rights reserved.
 */
namespace Kiliba\Connector\Observer\Customer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Kiliba\Connector\Helper\CookieHelper;

/**
 * Customer log observer.
 */
class LogLastLoginAtObserver implements ObserverInterface {

    /**
     * @var CookieHelper
     */
    private $_cookieHelper;

    /**
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        CookieHelper $cookieHelper
    ) {
        $this->_cookieHelper = $cookieHelper;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer) {
        $customer = $observer->getEvent()->getCustomer();
        $this->_cookieHelper->setKilibaCustomerKeyCookie($customer->getEmail());
    }
}
