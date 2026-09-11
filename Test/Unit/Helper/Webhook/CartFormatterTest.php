<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Helper\Webhook;

use PHPUnit\Framework\TestCase;

class CartFormatterTest extends TestCase
{
    /**
     * @dataProvider productImageUrlProvider
     */
    public function testBuildProductImageUrl($mediaUrl, $image, $expectedUrl)
    {
        $reflection = new \ReflectionClass(\Kiliba\Connector\Helper\Webhook\CartFormatter::class);
        $cartFormatter = $reflection->newInstanceWithoutConstructor();
        $buildProductImageUrl = $reflection->getMethod("buildProductImageUrl");
        $buildProductImageUrl->setAccessible(true);

        $this->assertSame(
            $expectedUrl,
            $buildProductImageUrl->invoke($cartFormatter, $mediaUrl, $image)
        );
    }

    public function productImageUrlProvider()
    {
        return [
            "Magento path with leading slash" => [
                "https://shop.example/media/",
                "/5/_/product.jpg",
                "https://shop.example/media/catalog/product/5/_/product.jpg",
            ],
            "Magento path without leading slash" => [
                "https://shop.example/media/",
                "5/_/product.jpg",
                "https://shop.example/media/catalog/product/5/_/product.jpg",
            ],
            "media URL without trailing slash" => [
                "https://shop.example/media",
                "/5/_/product.jpg",
                "https://shop.example/media/catalog/product/5/_/product.jpg",
            ],
        ];
    }
}
