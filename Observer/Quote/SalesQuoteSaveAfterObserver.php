<?php
/**
 * Copyright © Kiliba. All rights reserved.
 */
namespace Kiliba\Connector\Observer\Quote;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Kiliba\Connector\Helper\CookieHelper;
use Kiliba\Connector\Helper\KilibaLogger;

class SalesQuoteSaveAfterObserver implements ObserverInterface
{
    /**
     * @var KilibaLogger
     */
    protected $_kilibaLogger;

    /**
     * @var CookieHelper
     */
    protected $_cookieHelper;

    /**
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        KilibaLogger $kilibaLogger,
        CookieHelper $cookieHelper
    ) {
        $this->_kilibaLogger = $kilibaLogger;
        $this->_cookieHelper = $cookieHelper;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer) {
        try {
            $quote = $observer->getEvent()->getQuote();
            $kicus = $this->_cookieHelper->getKilibaCustomerKeyCookie();
            if(!empty($kicus)) {
                $quote->setKilibaCustomerKey($kicus);
                $quote->save();
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "SalesQuoteSaveAfterObserver::execute",
                $e->getMessage(),
                1
            );
        }
    }
}
