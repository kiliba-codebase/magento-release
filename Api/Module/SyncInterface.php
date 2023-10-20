<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Api\Module;

interface SyncInterface
{

    /**
     * @return string[]
     */
    public function forceSync();
}
