<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Model\Import;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class QuoteTest extends TestCase
{
    /**
     * @var \Magento\Quote\Model\Quote|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $_quote;

    /**
     * Is called before running a test
     */
    protected function setUp() : void
    {
        $this->_quote = $this->createMock(
            \Magento\Quote\Model\Quote::class
        );
    }

    /**
     * The test itself, every test function must start with 'test'
     */
    public function testFormatQuoteData()
    {
        $websiteId = 1;
        $priceTtc = 60;
        $helper = new ObjectManager($this);

        // Create mock for subject tested
        $constrArgs = $helper->getConstructArguments(
            \Kiliba\Connector\Model\Import\Quote::class
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
        $quoteFormatter = $this->getMockBuilder(
            \Kiliba\Connector\Model\Import\Quote::class
        )->setConstructorArgs(
            $constrArgs
        )->getMock();

        // Create mock that will be used
        $quoteItem = $this->getMockBuilder(
            \Magento\Quote\Model\Quote\Item::class
        )->disableOriginalConstructor()
        ->onlyMethods(["getProduct"])
        ->addMethods(["getBaseRowTotalInclTax"])
        ->getMock();

        $product = $this->createMock(
            \Magento\Catalog\Model\Product::class
        );

        $address = $this->createMock(
            \Magento\Customer\Api\Data\AddressInterface::class
        );

        // Set data for test
        $this->_quote
            ->expects($this->any())
            ->method('getAllVisibleItems')
            ->willReturn([$quoteItem]);
        $this->_quote
            ->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn($address);
        $this->_quote
            ->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($address);

        $quoteItem
            ->expects($this->any())
            ->method('getProduct')
            ->willReturn($product);
        $quoteItem
            ->expects($this->any())
            ->method('getBaseRowTotalInclTax')
            ->willReturn(50);

        // Prepare and call tested function
        $testFormatter = new \ReflectionMethod(
            \Kiliba\Connector\Model\Import\Quote::class,
            '_formatQuoteData'
        );
        $testFormatter->setAccessible(true);

        $formattedData = $testFormatter->invoke($quoteFormatter, $this->_quote, $this->_quote->getStoreId());

        // Checking data returned
        $quoteData = $formattedData["value"];
        $this->assertEquals(
            $this->_quote->getId(),
            $quoteData["id"]
        );
        $this->assertEquals(
            $websiteId,
            $quoteData["id_shop_group"]
        );
        $this->assertEquals(
            $this->_quote->getStoreId(),
            $quoteData["id_shop"]
        );
        $this->assertEquals(
            $this->_quote->getCustomerId(),
            $quoteData["id_customer"]
        );
        $this->assertEquals(
            $this->_quote->getBaseCurrencyCode(),
            $quoteData["id_currency"]
        );
        $this->assertEquals(
            $this->_quote->getShippingAddress()->getId(),
            $quoteData["id_address_delivery"]
        );
        $this->assertEquals(
            $this->_quote->getBillingAddress()->getId(),
            $quoteData["id_address_invoice"]
        );
        $this->assertEquals(
            $this->_quote->getCreatedAt(),
            $quoteData["date_add"]
        );
        $this->assertEquals(
            $this->_quote->getUpdatedAt(),
            $quoteData["date_update"]
        );
        $this->assertEquals(
            $this->_quote->getBaseGrandTotal(),
            $quoteData["total_with_tax_with_shipping_with_discount"]
        );
        $this->assertEquals(
            $this->_quote->getBaseSubtotalWithDiscount(),
            $quoteData["total_with_tax_without_shipping_with_discount"]
        );
        $this->assertEquals(
            $this->_quote->getBaseSubtotalWithDiscount() - $this->_quote->getBaseSubtotal(),
            $quoteData["total_discount_with_tax"]
        );
        $this->assertEquals(
            $this->_quote->getBaseGrandTotal(),
            $quoteData["total_products_with_tax"]
        );

        $quoteItem = $this->_quote->getAllVisibleItems()[0];
        $productData = $quoteData["products"][0];
        $this->assertEquals(
            $quoteItem->getProduct()->getId(),
            $productData["id_product"]
        );
        $this->assertEquals(
            $quoteItem->getProduct()->getAttributeSetId(),
            $productData["id_product_attribute"]
        );
        $this->assertEquals(
            $quoteItem->getQty(),
            $productData["cart_quantity"]
        );
        $this->assertEquals(
            $quoteItem->getStoreId(),
            $productData["id_shop"]
        );
        $this->assertEquals(
            $quoteItem->getSku(),
            $productData["reference"]
        );
        $this->assertEquals(
            $quoteItem->getDiscountAmount(),
            $productData["reduction"]
        );
        $this->assertEmpty(
            $productData["reduction_type"]
        );
        $this->assertEquals(
            $quoteItem->getBaseRowTotalInclTax(),
            $productData["total_wt"]
        );
        $this->assertEquals(
            $priceTtc,
            $productData["total_unit_wt"]
        );
        $this->assertEmpty(
            $productData["id_category_default"]
        );
    }
}
