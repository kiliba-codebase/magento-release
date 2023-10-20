<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Api\Module;

interface LogInterface
{
    /**
     * @return \Kiliba\Connector\Api\Data\LogInterface[]
     */
    public function getLogs();

    /**
     * @return int
     */
    public function clearLogs();
}
