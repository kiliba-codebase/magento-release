<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Api\Module;

interface ConfigurationInterface
{
    /**
     * @return string[]
     */
    public function setConfigValue();

    /**
     * @return string[]
     */
    public function getConfigValue();
}
