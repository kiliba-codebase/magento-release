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
    public function aroundComputeProductPrice(
        \Kiliba\Connector\Model\Import\Product\Interceptor $subject,
        callable $proceed,
        \Magento\Catalog\Api\Data\ProductInterface $product
    ) { 
        // Example: Override only bundle products price calculation
        if($product->getTypeId() === \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE) { 
            //
            // Change below the prices for the bundle (from custom logic or custom data).
            // Current numbers are here for example, do not use them in production.
            //
            // Example: Price with reductions
            // $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue()
            //
            // Example: Standard price
            // $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue()

            return array(
                // Price without tax (without reductions)
                "price" => "999.95",
                // Price with tax (without reductions)
                "price_wt" => "999.98",

                // Final price without tax (with reductions)
                "special_price" => "999.90",
                // Final price with tax (with reductions)
                "special_price_wt" => "999.93"
            );
        }

        // Otherwise, use the default module method to compute prices for other product types
        return $proceed($product);
    }
}