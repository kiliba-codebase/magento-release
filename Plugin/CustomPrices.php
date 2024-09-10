<?php
/*
 * Copyright © Kiliba. All rights reserved.
 * 
 * This example demonstrate how to customize final product prices before being send.
 * Enable it in etc/di.xml followed by setup:di:compile
 */

namespace Kiliba\Connector\Plugin; // To change depending on the location

class CustomPrices
{
    public function afterFormatData(
        \Kiliba\Connector\Model\Import\Product\Interceptor $subject,
        $result
    ) {
        if($result["product_type"] === \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) {
            //
            // Change below the prices for the bundle (from custom logic or custom data).
            // Current numbers are here for example, do not use them in production.
            //

            // Price without tax (without reductions)
            $result["price"] = "999.95";
            // Price with tax (without reductions)
            $result["price_wt"] = "999.98";
            
            
            // Final price without tax (with reductions)
            $result["special_price"] = "999.90";
            // Final price with tax (with reductions)
            $result["special_price_wt"] = "999.93";
        }
        return $result;
    }
}