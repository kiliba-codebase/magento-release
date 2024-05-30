<?php
/*
 * Copyright © Kiliba. All rights reserved.
 * 
 * This example demonstrate how to customize final product data before being send.
 * Enable it in etc/di.xml followed by setup:di:compile
 */

namespace Kiliba\Connector\Plugin;

class CustomStocks
{
    public function afterFormatData(
        \Kiliba\Connector\Model\Import\Product\Interceptor $subject,
        $result
    ) {
        // Product ID available with $result["id"]
        $result["salable_quantity"] = [];
        $result["physical_quantity"] = "999";
        return $result;
    }
}