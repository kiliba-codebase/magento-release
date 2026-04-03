<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */
namespace Kiliba\Connector\Test\Unit\Helper;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class QueueManagerTest extends TestCase
{

    /**
     * The test itself, every test function must start with 'test'
     */
    public function testAddToQueue()
    {
        $queueManager = $this->createMock(
            \Kiliba\Connector\Helper\QueueManager::class
        );
        $result = $queueManager->method("addToQueue")->with(1, "create", 1, "inventedType", 0);
        $this->assertEquals(true, (bool) $result);
    }
}
