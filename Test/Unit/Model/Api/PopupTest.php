<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Model\Api;

use Kiliba\Connector\Model\Api\Popup;
use PHPUnit\Framework\TestCase;

class PopupTest extends TestCase
{
    /**
     * Anonymous visitors must remain eligible when only newsletter subscribers
     * are excluded from the campaign.
     */
    public function testAnonymousAudienceMatchesSubscriberExclusion()
    {
        $popup = (new \ReflectionClass(Popup::class))->newInstanceWithoutConstructor();
        $anonymousAudience = $this->invokePrivateMethod($popup, "getAnonymousPopupAudience");
        $campaign = [
            "audienceRules" => [
                "newsletterState" => "any",
                "excludedNewsletterState" => "subscribed",
            ],
        ];

        $this->assertTrue(
            $this->invokePrivateMethod($popup, "campaignMatchesResolvedAudience", [$campaign, $anonymousAudience])
        );
    }

    /**
     * The anonymous fallback must not satisfy a positive subscriber targeting rule.
     */
    public function testAnonymousAudienceDoesNotMatchSubscriberTargeting()
    {
        $popup = (new \ReflectionClass(Popup::class))->newInstanceWithoutConstructor();
        $anonymousAudience = $this->invokePrivateMethod($popup, "getAnonymousPopupAudience");
        $campaign = [
            "audienceRules" => [
                "newsletterState" => "subscribed",
            ],
        ];

        $this->assertFalse(
            $this->invokePrivateMethod($popup, "campaignMatchesResolvedAudience", [$campaign, $anonymousAudience])
        );
    }

    /**
     * A resolved subscriber must still be rejected by the exclusion rule.
     */
    public function testSubscriberAudienceDoesNotMatchSubscriberExclusion()
    {
        $popup = (new \ReflectionClass(Popup::class))->newInstanceWithoutConstructor();
        $campaign = [
            "audienceRules" => [
                "newsletterState" => "any",
                "excludedNewsletterState" => "subscribed",
            ],
        ];
        $subscriberAudience = [
            "newsletterStatus" => "subscribed",
            "customerGroupIds" => [],
            "dynamicSegmentIds" => [],
        ];

        $this->assertFalse(
            $this->invokePrivateMethod($popup, "campaignMatchesResolvedAudience", [$campaign, $subscriberAudience])
        );
    }

    private function invokePrivateMethod($object, $methodName, array $arguments = [])
    {
        $method = new \ReflectionMethod(Popup::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}
