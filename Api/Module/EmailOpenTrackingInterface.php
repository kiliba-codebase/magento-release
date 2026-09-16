<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Api\Module;

interface EmailOpenTrackingInterface
{
    /**
     * Update the open-tracking state stored by the Magento customer integration.
     *
     * @return string[]
     */
    public function setCustomerEmailOpenTracking();
}
