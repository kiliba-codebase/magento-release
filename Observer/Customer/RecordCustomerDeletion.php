<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Observer\Customer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Helper\ConfigHelper;

class RecordCustomerDeletion implements ObserverInterface
{

    /**
     * @var ConfigHelper
     */
    protected $_configHelper;

    /**
     * @var DeletedItem
     */
    protected $_deletedItemManager;

    public function __construct(
        ConfigHelper $configHelper,
        DeletedItem $deletedItemManager
    ) {
        $this->_configHelper = $configHelper;
        $this->_deletedItemManager = $deletedItemManager;
    }

    /**
     * event : customer_delete_after
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        
        // check if store is link to kiliba
        $store = $customer->getStore();
        if (empty($store)) return;

        $website = $store->getWebsite();
        $websiteId = $website->getId();
        if (!empty($this->_configHelper->getClientId($websiteId))) {
            $this->_deletedItemManager->recordDeletion($customer->getId(), "customer");
        }
    }
}
