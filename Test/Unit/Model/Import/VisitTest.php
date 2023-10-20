<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Model\Import;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Serialize\SerializerInterface;

class VisitTest extends TestCase
{

    /**
     * @var string[]
     */
    private $_visitDataArray;

    /**
     * @var string
     */
    private $_visitDataJson;

    /**
     * Is called before running a test
     */
    protected function setUp() : void
    {
        $objectManager = new ObjectManager($this);
        $serialize = $objectManager->getObject(\Magento\Framework\Serialize\Serializer\Serialize::class);

        $this->_visitDataArray = [
            "url" => "test-url.com",
            "id_customer" => "1",
            "date" => date('Y-m-d H:i:s'),
            "id_product" => "1",
            "id_category" => "0"
        ];
        $this->_visitDataJson = $serialize->serialize($this->_visitDataArray);
    }

    /**
     * The test itself, every test function must start with 'test'
     */
    public function testFormatVisitData()
    {
        $helper = new ObjectManager($this);

        // Create mock for subject tested
        $constrArgs = $helper->getConstructArguments(
            \Kiliba\Connector\Model\Import\Visit::class
        );

        $serialize = $this->getMockForAbstractClass(
            SerializerInterface::class
        );
        $serialize
            ->expects($this->any())
            ->method("unserialize")
            ->with($this->_visitDataJson)
            ->willReturn($this->_visitDataArray);

        $constrArgs["serializer"] = $serialize;
        $visitFormatter = $this->getMockBuilder(
            \Kiliba\Connector\Model\Import\Visit::class
        )->setConstructorArgs(
            $constrArgs
        )->getMock();

        // Prepare and call tested function
        $testFormatter = new \ReflectionMethod(
            \Kiliba\Connector\Model\Import\Visit::class,
            '_formatVisitData'
        );
        $testFormatter->setAccessible(true);

        $formattedData = $testFormatter->invoke($visitFormatter, $this->_visitDataJson, 1);

        // Checking data returned
        $visitData = $formattedData["value"];
        $this->assertEquals(
            $this->_visitDataArray["url"],
            $visitData["url"]
        );
        $this->assertEquals(
            $this->_visitDataArray["id_customer"],
            $visitData["id_customer"]
        );
        $this->assertEquals(
            $this->_visitDataArray["date"],
            $visitData["date"]
        );
        $this->assertEquals(
            $this->_visitDataArray["id_product"],
            $visitData["id_product"]
        );
        $this->assertEquals(
            $this->_visitDataArray["id_category"],
            $visitData["id_category"]
        );
    }
}
