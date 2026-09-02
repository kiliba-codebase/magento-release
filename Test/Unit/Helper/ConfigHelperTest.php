<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Test\Unit\Helper;

use Kiliba\Connector\Helper\ConfigHelper;
use PHPUnit\Framework\TestCase;

class ConfigHelperTest extends TestCase
{
    public function testFluxTokenUsesWebsiteScopeWhenConfigured()
    {
        $helper = new TestableConfigHelper([
            'default' => 'legacy-token',
            'website:8' => 'website-token',
        ]);

        $this->assertSame('website-token', $helper->getFluxToken(8));
        $this->assertSame([8], $helper->getRequestedScopes());
    }

    public function testFluxTokenFallsBackToDefaultScope()
    {
        $helper = new TestableConfigHelper([
            'default' => 'legacy-token',
        ]);

        $this->assertSame('legacy-token', $helper->getFluxToken(8));
        $this->assertSame([8, null], $helper->getRequestedScopes());
    }

    public function testFluxTokenReadsDefaultScopeDirectlyWithoutWebsite()
    {
        $helper = new TestableConfigHelper([
            'default' => 'legacy-token',
        ]);

        $this->assertSame('legacy-token', $helper->getFluxToken());
        $this->assertSame([null], $helper->getRequestedScopes());
    }
}

class TestableConfigHelper extends ConfigHelper
{
    private $values;
    private $requestedScopes = [];

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function getConfigWithoutCache($path, $websiteId = null)
    {
        $this->requestedScopes[] = $websiteId;
        $key = $websiteId === null ? 'default' : 'website:' . $websiteId;

        return isset($this->values[$key]) ? $this->values[$key] : false;
    }

    public function getRequestedScopes()
    {
        return $this->requestedScopes;
    }
}
