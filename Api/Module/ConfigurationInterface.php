<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Api\Module;

interface ConfigurationInterface
{
    /**
     * Return Magento configuration for Kiliba
     * @return string[]
     */
    public function getConfigValue();

    /**
     * Refresh website token for Kiliba module API authentication
     * @return string[]
     */
    public function refreshToken();
}
