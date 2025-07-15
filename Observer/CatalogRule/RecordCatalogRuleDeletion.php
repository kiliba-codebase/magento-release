<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Observer\CatalogRule;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Model\Import\DeletedItem;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RecordCatalogRuleDeletion implements ObserverInterface
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
     * Event: catalogrule_rule_delete_before
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $catalogRule = $observer->getEvent()->getRule();

        // Catalog rules can be scoped to websites. Ensure at least one of them is linked to Kiliba.
        $websiteIds   = $catalogRule->getWebsiteIds();
        $kilibaLinked = false;

        foreach ($websiteIds as $websiteId) {
            if (!empty($this->_configHelper->getClientId($websiteId))) {
                $kilibaLinked = true;
                break;
            }
        }

        if ($kilibaLinked) {
            $this->_deletedItemManager->recordDeletion($catalogRule->getId(), 'catalogRule');
        }
    }
}
