<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model;

class Quote extends \Magento\Quote\Model\Quote
{
    const KEY_KILIBA_CONNECTOR_CUSTOMER_KEY = 'kiliba_connector_customer_key';

    /**
     * @param string $customerKey
     */
    public function setKilibaCustomerKey($customerKey) {
        return $this->setData(self::KEY_KILIBA_CONNECTOR_CUSTOMER_KEY, $customerKey);
    }

    public function getKilibaCustomerKey() {
        return $this->_getData(self::KEY_KILIBA_CONNECTOR_CUSTOMER_KEY);
    }
}