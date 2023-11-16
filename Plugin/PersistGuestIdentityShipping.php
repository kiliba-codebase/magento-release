<?php
/**
 * Copyright © Kiliba. All rights reserved.
 */
namespace Kiliba\Connector\Plugin;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Helper\CookieHelper;

class PersistGuestIdentityShipping
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
     * @var CartHelper
     */
    protected $_cartHelper;

    /**
     * @param KilibaLogger $kilibaLogger
     * @param CookieHelper $cookieHelper
     * @param CartHelper $cartHelper
     */
    public function __construct(
        KilibaLogger $kilibaLogger,
        CookieHelper $cookieHelper,
        CartHelper $cartHelper
    ) {
        $this->_kilibaLogger = $kilibaLogger;
        $this->_cookieHelper = $cookieHelper;
        $this->_cartHelper = $cartHelper;
    }

    public function afterSaveAddressInformation(
        ShippingInformationManagement $subject,
        $result,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        try {
            $quote = $this->_cartHelper->getQuote();

            $customer_email = $quote->getCustomerEmail();
            if(empty($customer_email)) {
                $customer_email = $quote->getBillingAddress()->getEmail();
            }

            if(!empty($customer_email)) {
                $customerKey = $this->_cookieHelper->setKilibaCustomerKeyCookie($customer_email);
                $quote->setKilibaCustomerKey($customerKey);
                $quote->save();
            }
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Plugin\PersistGuestIdentityShipping::afterSaveAddressInformation",
                $e->getMessage(),
                1
            );
        }

        return $result;
    }
}
