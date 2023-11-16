<?php
/**
 * Copyright © Kiliba. All rights reserved.
 */
namespace Kiliba\Connector\Plugin;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\AccountManagement;
use Magento\Checkout\Model\Session;
use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Helper\CookieHelper;

class PersistGuestIdentityEmail
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
     * @var Session
     */
    protected $_session;

    /**
     * @param KilibaLogger $kilibaLogger
     * @param CookieHelper $cookieHelper
     * @param Session $session
     */
    public function __construct(
        KilibaLogger $kilibaLogger,
        CookieHelper $cookieHelper,
        Session $session
    ) {
        $this->_kilibaLogger = $kilibaLogger;
        $this->_cookieHelper = $cookieHelper;
        $this->_session = $session;
    }

    public function afterIsEmailAvailable(
        AccountManagement $subject,
        $result,
        $customerEmail,
        $websiteId = null
    ) {
        try {
            $quote = $this->_session->getQuote();
            if(!empty($quote->getId()) && !empty($customerEmail)) {
                $customerKey = $this->_cookieHelper->setKilibaCustomerKeyCookie($customerEmail);
                $quote->setKilibaCustomerKey($customerKey);
                $quote->save();
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Plugin\PersistGuestIdentityEmail::afterIsEmailAvailable",
                $e->getMessage(),
                1
            );
        }

        return $result;
    }
}
