<?php
/*
 * Copyright © Kiliba. All rights reserved.
 * 
 * This example demonstrate how to customize final product prices before being send.
 * Enable it in etc/di.xml followed by setup:di:compile
 */

namespace Kiliba\Connector\Plugin;

class CustomPrices
{
    public function afterFormatData(
        \Kiliba\Connector\Model\Import\Product\Interceptor $subject,
        $result
    ) {
        if($result["product_type"] === \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) {
            $result["price"] = "999.95";
            $result["price_wt"] = "999.98";
            $result["special_price"] = "999.90";
            $result["special_price_wt"] = "999.93";
        }
        return $result;
    }
}