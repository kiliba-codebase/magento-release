<?php

namespace Kiliba\Connector\Setup\Patch\Data;

use Kiliba\Connector\Setup\Patch\DataPatchNotifyUpgrade;

class NotifyUpgrade2812 extends DataPatchNotifyUpgrade
{
    protected function getInstalledVersion()
    {
        return '2.8.12';
    }
}
