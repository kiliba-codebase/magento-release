<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Model\Import;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
    /**
     * @var \Magento\Customer\Api\Data\CustomerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $_customer;

    /**
     * Is called before running a test
     */
    protected function setUp() : void
    {
        $this->_customer = $this->createMock(
            \Magento\Customer\Api\Data\CustomerInterface::class
        );
    }

    /**
     * The test itself, every test function must start with 'test'
     */
    public function testFormatCustomerData()
    {
        $customerId = 1;
        $websiteId = 1;
        $isSubscriber = true;
        $helper = new ObjectManager($this);

        // Create mock for subject tested
        $constrArgs = $helper->getConstructArguments(
            \Kiliba\Connector\Model\Import\Customer::class
        );

        $subscriberFactory = $this->createPartialMock(
            \Magento\Newsletter\Model\SubscriberFactory::class,
            ['create']
        );

        $subscriber = $this->createMock(
            \Magento\Newsletter\Model\Subscriber::class
        );

        $subscriberFactory
            ->expects($this->any())
            ->method('create')
            ->willReturn($subscriber);
        $subscriber
            ->expects($this->any())
            ->method('load')
            ->willReturnSelf();
        $subscriber
            ->expects($this->any())
            ->method('isSubscribed')
            ->willReturn($isSubscriber);

        $constrArgs["subscriberFactory"] = $subscriberFactory;

        $customerFormatter = $this->getMockBuilder(
            \Kiliba\Connector\Model\Import\Customer::class
        )->setConstructorArgs(
            $constrArgs
        )->getMock();

        // Create mock that will be used
        $address = $this->createMock(
            \Magento\Customer\Api\Data\AddressInterface::class
        );

        // Set data for test
        $this->_customer
            ->expects($this->any())
            ->method('getId')
            ->willReturn($customerId);
        $this->_customer
            ->expects($this->any())
            ->method('getWebsiteId')
            ->willReturn($websiteId);
        $this->_customer
            ->expects($this->any())
            ->method('getAddresses')
            ->willReturn([$address]);

        // Prepare and call tested function
        $testFormatter = new \ReflectionMethod(
            \Kiliba\Connector\Model\Import\Customer::class,
            '_formatCustomerData'
        );
        $testFormatter->setAccessible(true);

        $formattedData = $testFormatter->invoke($customerFormatter, $this->_customer, $this->_customer->getStoreId());

        // Checking data returned
        $customerData = $formattedData["value"];
        $this->assertEquals(
            $this->_customer->getEmail(),
            $customerData["email"]
        );
        $this->assertEquals(
            $customerId,
            $customerData["id_magento"]
        );
        $this->assertEquals(
            $this->_customer->getWebsiteId(),
            $customerData["id_shop_group"]
        );
        $this->assertEquals(
            $this->_customer->getStoreId(),
            $customerData["id_shop"]
        );
        $this->assertEquals(
            $this->_customer->getFirstname(),
            $customerData["firstname"]
        );
        $this->assertEquals(
            $this->_customer->getLastname(),
            $customerData["lastname"]
        );
        $this->assertEquals(
            $this->_customer->getDob(),
            $customerData["birthday"]
        );
        $this->assertEquals(
            $isSubscriber,
            $customerData["newsletter"]
        );
        $this->assertEquals(
            $this->_customer->getGender(),
            $customerData["id_gender"]
        );
        $this->assertEquals(
            $this->_customer->getCreatedAt(),
            $customerData["date_add"]
        );
        $this->assertEquals(
            $this->_customer->getUpdatedAt(),
            $customerData["date_update"]
        );
        $this->assertEquals(
            '0',
            $customerData["deleted"]
        );
        $this->assertEquals(
            $this->_customer->getGroupId(),
            $customerData["id_default_group"]
        );
        $this->assertEquals(
            $this->_customer->getGroupId(),
            $customerData["id_groups"]
        );

        $customerAddress = $this->_customer->getAddresses()[0];
        $addressData = $customerData["addresses"][0];
        $this->assertEquals(
            $customerAddress->getId(),
            $addressData["id_address"]
        );
        $this->assertEquals(
            $customerAddress->getCountryId(),
            $addressData["id_country"]
        );
        $this->assertEquals(
            $customerAddress->getRegionId(),
            $addressData["id_state"]
        );
        $this->assertEquals(
            $customerAddress->getPostcode(),
            $addressData["postcode"]
        );
        $this->assertEquals(
            $customerAddress->getCity(),
            $addressData["city"]
        );
        $this->assertEquals(
            $customerAddress->getTelephone(),
            $addressData["phone"]
        );
        $this->assertEquals(
            '0',
            $addressData["deleted"]
        );
    }
}
