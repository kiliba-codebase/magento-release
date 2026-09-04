<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Model\Import;

use Kiliba\Connector\Model\Import\AbstractModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class AbstractModelTest extends TestCase
{
    public function testFormatPriceSupportsNullableHistoricalValues(): void
    {
        $model = (new ReflectionClass(AbstractModel::class))->newInstanceWithoutConstructor();
        $formatPrice = new ReflectionMethod(AbstractModel::class, "_formatPrice");
        $formatPrice->setAccessible(true);

        $this->assertSame("0.0000", $formatPrice->invoke($model, null));
        $this->assertSame("12.3456", $formatPrice->invoke($model, "12.3456"));
    }
}
