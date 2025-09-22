<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class CookieHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

    const KILIBA_CUSTOMER_KEY_COOKIE = "ki_cus";

    /**
     * @var KilibaLogger
     */
    protected $_kilibaLogger;

    /**
     * @var CookieMetadataFactory
     */
    protected $_cookieMetadataFactory;

    /**
     * @var CookieManagerInterface
     */
    protected $_cookieManager;

    /**
     * @param KilibaLogger $kilibaLogger
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        KilibaLogger $kilibaLogger,
        CookieMetadataFactory $cookieMetadataFactory,
        ?CookieManagerInterface $cookieManager = null
    ) {
        $this->_kilibaLogger = $kilibaLogger;
        $this->_cookieMetadataFactory = $cookieMetadataFactory;
        $this->_cookieManager = $cookieManager ?: ObjectManager::getInstance()->get(CookieManagerInterface::class);
    }

    /**
     * Read cookie to get Kiliba Customer Key
     */
    public function getKilibaCustomerKeyCookie() {
        return $this->_cookieManager->getCookie(self::KILIBA_CUSTOMER_KEY_COOKIE);
    }

    /**
     * Read email from Kiliba Customer Key
     * @param string $customer_key
     */
    public function getCustomerEmailViaKilibaCustomerKey($customer_key) {
        try {
            return json_decode(base64_decode($customer_key))->email;
        } catch (\Exception $e) {
            return "";
        }
    }

    /**
     * Write customer data to Kiliba Customer Key
     * @param string $customer_email
     */
    public function setKilibaCustomerKeyCookie($customer_email) {
        $ki_cus = base64_encode(json_encode(array("email" => $customer_email)));

        $cookieMetadata = $this->_cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration(3600 * 24 * 365)
            ->setPath("/");

        if(method_exists($cookieMetadata, "setSameSite")) {
            $cookieMetadata->setSameSite("Strict");
        }

        $this->_cookieManager->setPublicCookie(self::KILIBA_CUSTOMER_KEY_COOKIE, $ki_cus, $cookieMetadata);

        return $ki_cus;
    }
}