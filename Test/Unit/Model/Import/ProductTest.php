<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Model\Import;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Magento\Catalog\Api\Data\ProductExtensionInterface;

class ProductTest extends TestCase
{
    /**
     * @var \Magento\Catalog\Model\Product|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $_product;

    /**
     * Is called before running a test
     */
    protected function setUp() : void
    {
        $this->_product = $this->createMock(\Magento\Catalog\Model\Product::class);
    }

    /**
     * The test itself, every test function must start with 'test'
     */
    public function testFormatProductData()
    {
        $productId = 1;
        $priceTtc = 60;
        $helper = new ObjectManager($this);

        // Create mock for subject tested
        $constrArgs = $helper->getConstructArguments(
            \Kiliba\Connector\Model\Import\Product::class
        );

        $stockRepository = $this->createMock(
            \Magento\CatalogInventory\Model\Stock\StockItemRepository::class
        );
        $stockItem = $this->createMock(
            \Magento\CatalogInventory\Api\Data\StockItemInterface::class
        );
        $formatterHelper = $this->createMock(
            \Kiliba\Connector\Helper\FormatterHelper::class
        );

        $stockRepository
            ->expects($this->any())
            ->method('get')
            ->with($productId)
            ->willReturn($stockItem);
        $formatterHelper
            ->expects($this->any())
            ->method('getProductPriceIncludingTax')
            ->willReturn($priceTtc);

        $constrArgs["stockItemRepository"] = $stockRepository;
        $constrArgs["formatterHelper"] = $formatterHelper;

        $productFormatter = $this->getMockBuilder(
            \Kiliba\Connector\Model\Import\Product::class
        )->setConstructorArgs(
            $constrArgs
        )->getMock();

        // Set data for test
        $this->_product
            ->expects($this->any())
            ->method('getId')
            ->willReturn($productId);
        $this->_product
            ->expects($this->any())
            ->method('isSaleable')
            ->willReturn(true);
        $this->_product
            ->expects($this->any())
            ->method('getPrice')
            ->willReturn(20.000);
        $this->_product
            ->expects($this->any())
            ->method('getSpecialPrice')
            ->willReturn(12.000);

        $stockItem
            ->expects($this->any())
            ->method('getBackorders')
            ->willReturn(StockItemInterface::BACKORDERS_NO);
        $stockItem
            ->expects($this->any())
            ->method('getManageStock')
            ->willReturn(true);
        $stockItem
            ->expects($this->any())
            ->method('getIsInStock')
            ->willReturn(true);
        $stockItem
            ->expects($this->any())
            ->method('getQtyIncrements')
            ->willReturn(2);

        // Prepare and call tested function
        $testFormatter = new \ReflectionMethod(
            \Kiliba\Connector\Model\Import\Product::class,
            '_formatProductData'
        );
        $testFormatter->setAccessible(true);

        $formattedData = $testFormatter->invoke($productFormatter, $this->_product, $this->_product->getStoreId());

        // Checking data returned
        $productData = $formattedData["value"];
        $this->assertEquals(
            $this->_product->getId(),
            $productData["id"]
        );
        $this->assertEquals(
            $this->_product->getAttributeSetId(),
            $productData["id_attribute"]
        );
        $this->assertEquals(
            null,
            $productData["parent_id"]
        );
        $this->assertEquals(
            $this->_product->getTypeId(),
            $productData["product_type"]
        );
        $this->assertEquals(
            $this->_product->isSaleable(),
            $productData["on_sale"]
        );
        $this->assertEquals(
            null,
            $productData["id_category_default"]
        );
        $this->assertEquals(
            $stockItem->getMinQty(),
            $productData["minimal_quantity"]
        );
        $this->assertEquals(
            $this->_product->getStatus(),
            $productData["active"]
        );
        $this->assertEquals(
            null,
            $productData["visibility"]
        );
        $this->assertEquals(
            $this->_product->getVisibility(),
            $productData["visibility_simple"]
        );
        $this->assertEquals(
            $this->_product->getPrice(),
            $productData["price"]
        );
        $this->assertEquals(
            $priceTtc,
            $productData["price_wt"]
        );
        $this->assertEquals(
            $this->_product->getSpecialPrice(),
            $productData["special_price"]
        );
        $this->assertEquals(
            $this->_product->getSpecialFromDate(),
            $productData["special_from_date"]
        );
        $this->assertEquals(
            $this->_product->getSpecialToDate(),
            $productData["special_to_date"]
        );
        $this->assertEquals(
            $stockItem->getQty(),
            $productData["available_for_order"]
        );
        $this->assertEquals(
            $stockItem->getBackorders(),
            $productData["available_when_out_of_stock"]
        );
        $this->assertEquals(
            $this->_product->getCreatedAt(),
            $productData["date_add"]
        );
        $this->assertEquals(
            $this->_product->getUpdatedAt(),
            $productData["date_update"]
        );
        $this->assertEquals(
            $stockItem->getQty(),
            $productData["physical_quantity"]
        );
        $this->assertEquals(
            $stockItem->getManageStock(),
            $productData["depends_on_stock"]
        );
        $this->assertEquals(
            !$stockItem->getIsInStock(),
            $productData["out_of_stock"]
        );
        $this->assertEquals(
            $stockItem->getQtyIncrements(),
            $productData["qty_increment"]
        );
        $this->assertEquals(
            null,
            $productData["image_url"]
        );
        $this->assertEquals(
            $this->_product->getProductUrl(),
            $productData["absolute_url"]
        );
        $this->assertEquals(
            $this->_product->getUrlKey(),
            $productData["relative_url"]
        );
        $this->assertEquals(
            $this->_product->getName(),
            $productData["name"]
        );
        $this->assertEquals(
            $this->_product->getDescription(),
            $productData["description"]
        );
        $this->assertEquals(
            '0',
            $productData["deleted"]
        );
    }
}
