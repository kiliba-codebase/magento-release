<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Config\Backend;

class ReadonlyConfig extends \Magento\Framework\App\Config\Value
{
    /**
     * @return void
     */
    public function beforeSave()
    {
        $this->_dataSaveAllowed = false;
    }
}
