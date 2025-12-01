<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\ResourceModel\PopupCustomer;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            \Kiliba\Connector\Model\PopupCustomer::class,
            \Kiliba\Connector\Model\ResourceModel\PopupCustomer::class
        );
    }
}
