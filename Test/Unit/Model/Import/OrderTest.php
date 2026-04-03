<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Model\Import;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    /**
     * @var \Magento\Sales\Model\Order|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $_order;

    /**
     * Is called before running a test
     */
    protected function setUp() : void
    {
        $this->_order = $this->createMock(
            \Magento\Sales\Model\Order::class
        );

        $this->_order
            ->expects($this->any())
            ->method('getBaseGrandTotal')
            ->willReturn(62);
        $this->_order
            ->expects($this->any())
            ->method('getBaseSubtotalInclTax')
            ->willReturn(50);
        $this->_order
            ->expects($this->any())
            ->method('getBaseTaxAmount')
            ->willReturn(12);
        $this->_order
            ->expects($this->any())
            ->method('getBaseSubtotal')
            ->willReturn(40);
        $this->_order
            ->expects($this->any())
            ->method('getBaseShippingInclTax')
            ->willReturn(12);
        $this->_order
            ->expects($this->any())
            ->method('getBaseShippingAmount')
            ->willReturn(10);
        $this->_order
            ->expects($this->any())
            ->method('getBaseDiscountAmount')
            ->willReturn(0);
    }

    /**
     * The test itself, every test function must start with 'test'
     */
    public function testFormatOrderData()
    {
        $websiteId = 1;
        $priceTtc = 60;
        $helper = new ObjectManager($this);

        // Create mock for subject tested
        $constrArgs = $helper->getConstructArguments(
            \Kiliba\Connector\Model\Import\Order::class
        );

        $formatterHelper = $this->createMock(
            \Kiliba\Connector\Helper\FormatterHelper::class
        );
        $formatterHelper
            ->expects($this->any())
            ->method('getWebsiteIdFromStore')
            ->willReturn($websiteId);
        $formatterHelper
            ->expects($this->any())
            ->method('getProductPriceIncludingTax')
            ->willReturn($priceTtc);

        $constrArgs["formatterHelper"] = $formatterHelper;

        $orderFormatter = $this->getMockBuilder(
            \Kiliba\Connector\Model\Import\Order::class
        )->setConstructorArgs(
            $constrArgs
        )->getMock();

        // Create mock that will be used
        $orderItem = $this->createMock(
            \Magento\Sales\Model\Order\Item::class
        );

        $product = $this->createMock(
            \Magento\Catalog\Model\Product::class
        );

        $address = $this->createMock(
            \Magento\Customer\Api\Data\AddressInterface::class
        );

        // Set data for test
        $this->_order
            ->expects($this->any())
            ->method('getAllVisibleItems')
            ->willReturn([$orderItem]);
        $this->_order
            ->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($address);

        $orderItem
            ->expects($this->any())
            ->method('getProduct')
            ->willReturn($product);
        $orderItem
            ->expects($this->any())
            ->method('getBasePrice')
            ->willReturn(40.000);
        $orderItem
            ->expects($this->any())
            ->method('getBaseRowTotalInclTax')
            ->willReturn(50.000);

        // Prepare and call tested function
        $testFormatter = new \ReflectionMethod(
            \Kiliba\Connector\Model\Import\Order::class,
            '_formatOrderData'
        );
        $testFormatter->setAccessible(true);

        $formattedData = $testFormatter->invoke($orderFormatter, $this->_order, $this->_order->getStoreId());

        // Checking data returned
        $orderData = $formattedData["value"];
        $this->assertEquals(
            $this->_order->getId(),
            $orderData["id"]
        );
        $this->assertEquals(
            $websiteId,
            $orderData["id_shop_group"]
        );
        $this->assertEquals(
            $this->_order->getStoreId(),
            $orderData["id_shop"]
        );
        $this->assertEquals(
            $this->_order->getCustomerId(),
            $orderData["id_customer"]
        );
        $this->assertEquals(
            $this->_order->getBaseCurrencyCode(),
            $orderData["id_currency"]
        );
        $this->assertEquals(
            $this->_order->getQuoteId(),
            $orderData["id_cart"]
        );
        $this->assertEquals(
            $this->_order->getStatus(),
            $orderData["current_state"]
        );
        $this->assertEquals(
            $this->_order->getIncrementId(),
            $orderData["reference"]
        );
        $this->assertEquals(
            $this->_order->getBillingAddressId(),
            $orderData["id_address_invoice"]
        );
        $this->assertEquals(
            $this->_order->getShippingAddress()->getId(),
            $orderData["id_address_delivery"]
        );
        $this->assertEquals(
            $this->_order->getCreatedAt(),
            $orderData["date_add"]
        );
        $this->assertEquals(
            $this->_order->getUpdatedAt(),
            $orderData["date_update"]
        );
        $this->assertEquals(
            $this->_order->getBaseGrandTotal(),
            $orderData["total_paid"]
        );
        $this->assertEquals(
            $this->_order->getBaseGrandTotal(),
            $orderData["total_with_tax"]
        );
        $this->assertEquals(
            $this->_order->getBaseGrandTotal() - $this->_order->getBaseTaxAmount(),
            $orderData["total_without_tax"]
        );
        $this->assertEquals(
            $this->_order->getBaseSubtotalInclTax(),
            $orderData["total_products_with_tax"]
        );
        $this->assertEquals(
            $this->_order->getBaseSubtotal(),
            $orderData["total_products_without_tax"]
        );
        $this->assertEquals(
            $this->_order->getBaseShippingInclTax(),
            $orderData["total_shipping_with_tax"]
        );
        $this->assertEquals(
            $this->_order->getBaseShippingAmount(),
            $orderData["total_shipping_without_tax"]
        );
        $this->assertEquals(
            $this->_order->getBaseDiscountAmount(),
            $orderData["total_discount_with_tax"]
        );
        $this->assertEquals(
            $this->_order->getBaseDiscountAmount(),
            $orderData["total_discount_without_tax"]
        );

        $orderItem = $this->_order->getAllVisibleItems()[0];
        $productData = $orderData["products"][0];
        $this->assertEquals(
            $orderItem->getProductId(),
            $productData["id_product"]
        );
        $this->assertEquals(
            $orderItem->getProduct()->getAttributeSetId(),
            $productData["id_product_attribute"]
        );
        $this->assertEquals(
            $orderItem->getQtyOrdered(),
            $productData["cart_quantity"]
        );
        $this->assertEquals(
            $orderItem->getSku(),
            $productData["reference"]
        );
        $this->assertEquals(
            $orderItem->getDiscountAmount(),
            $productData["reduction_amount"]
        );
        $this->assertEquals(
            $orderItem->getDiscountPercent(),
            $productData["reduction_percent"]
        );
        $this->assertEquals(
            $orderItem->getBasePrice(),
            $productData["price"]
        );
        $this->assertEquals(
            $priceTtc,
            $productData["price_wt"]
        );
        $this->assertEquals(
            $orderItem->getBaseRowTotalInclTax(),
            $productData["total_wt"]
        );
        $this->assertEmpty(
            $productData["id_category_default"]
        );
    }
}
