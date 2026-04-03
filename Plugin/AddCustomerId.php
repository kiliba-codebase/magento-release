<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Plugin;

use Magento\Customer\Helper\Session\CurrentCustomer;

class AddCustomerId
{
    /**
     * @var CurrentCustomer
     */
    protected $_currentCustomer;

    public function __construct(
        CurrentCustomer $currentCustomer
    ) {
        $this->_currentCustomer = $currentCustomer;
    }

    public function afterGetSectionData(
        \Magento\Customer\CustomerData\Customer $subject,
        $result
    ) {
        if ($this->_currentCustomer->getCustomerId()) {
            $result["id"] = $this->_currentCustomer->getCustomerId();
        }

        return $result;
    }
}
