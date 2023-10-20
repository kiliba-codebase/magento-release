<?php
/*
 * Copyright © 410 Gone (contact@410-gone.fr). All rights reserved.
 * See LICENSE.txt for license details (http://opensource.org/licenses/osl-3.0.php).
 */

namespace Kiliba\Connector\Observer\Product;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Model\Import\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RecordProductDeletion implements ObserverInterface
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
     * event : catalog_product_delete_before
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getProduct();
        //check product type
        if(!in_array($product->getTypeId(), Product::PRODUCT_TYPE_SYNC)) {
            return;
        }
        $this->_deletedItemManager->recordDeletion($product->getId(), "product");
    }
}
