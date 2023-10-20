<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Observer\PriceRule;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Helper\ConfigHelper;

class RecordPriceRuleDeletion implements ObserverInterface
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
     * event : salesrule_rule_delete_before
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(Observer $observer)
    {
        $priceRule = $observer->getEvent()->getRule();

        // check if store is link to kiliba
        $websiteIds = $priceRule->getWebsiteIds();
        $kilibaLinked = false;

        foreach ($websiteIds as $key => $websiteId) {
            if (!empty($this->_configHelper->getClientId($websiteId))) {
                $kilibaLinked = true;
            }
        }

        if ($kilibaLinked) {
            $this->_deletedItemManager->recordDeletion($priceRule->getId(), "priceRule");
        }
    }
}
