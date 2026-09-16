<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Model\Api;

use Kiliba\Connector\Model\Api\EmailOpenTracking;
use PHPUnit\Framework\TestCase;

class EmailOpenTrackingTest extends TestCase
{
    /**
     * @param mixed[] $params
     * @return mixed[]
     */
    protected function normalizeUpdates(array $params)
    {
        $subject = (new \ReflectionClass(EmailOpenTracking::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(EmailOpenTracking::class, "normalizeUpdates");
        $method->setAccessible(true);

        return $method->invoke($subject, $params);
    }

    public function testNormalizesTrackingValues()
    {
        $result = $this->normalizeUpdates([
            "status" => " CONSENTED ",
            "updated_at" => "2026-09-15T12:30:00+02:00",
            "source" => " checkout ",
            "policy_version" => " 2026-04 ",
        ]);

        $this->assertSame("consented", $result["updates"]["pixel_tracking_status"]);
        $this->assertSame("2026-09-15T10:30:00Z", $result["updates"]["pixel_tracking_updated_at"]);
        $this->assertSame("checkout", $result["updates"]["pixel_tracking_source"]);
        $this->assertSame("2026-04", $result["updates"]["pixel_tracking_policy_version"]);
    }

    public function testRejectsBooleanStyleStatus()
    {
        $this->assertSame(
            ["error" => "invalid_status"],
            $this->normalizeUpdates(["status" => "1"])
        );
    }

    public function testRejectsInvalidTimestamp()
    {
        $this->assertSame(
            ["error" => "invalid_updated_at"],
            $this->normalizeUpdates(["updated_at" => "not-a-date"])
        );
    }

    public function testAllowsExplicitlyClearingOptionalValues()
    {
        $result = $this->normalizeUpdates([
            "status" => "",
            "updated_at" => "",
            "source" => "",
            "policy_version" => "",
        ]);

        $this->assertSame([
            "pixel_tracking_status" => null,
            "pixel_tracking_updated_at" => null,
            "pixel_tracking_source" => null,
            "pixel_tracking_policy_version" => null,
        ], $result["updates"]);
    }
}
