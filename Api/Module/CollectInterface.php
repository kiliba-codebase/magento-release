<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Api\Module;

interface CollectInterface
{
    /**
     * @return string[]
     */
    public function pullDatas();

    /**
     * @return string[]
     */
    public function pullIds();
}
