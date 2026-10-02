<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Block;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\PushGateway;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class PushConfiguration extends Template
{
    /** @var ConfigHelper */
    protected $configHelper;

    /** @var PushGateway */
    protected $pushGateway;

    /** @var Resolver */
    protected $localeResolver;

    /** @var StoreManagerInterface */
    protected $storeManager;

    public function __construct(
        Template\Context $context,
        ConfigHelper $configHelper,
        PushGateway $pushGateway,
        Resolver $localeResolver,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        $this->pushGateway = $pushGateway;
        $this->localeResolver = $localeResolver;
        $this->storeManager = $storeManager;
    }

    /** Return the browser-safe settings only when this website is fully linked to Kiliba. */
    public function getPushSettings()
    {
        $store = $this->storeManager->getStore();
        $websiteId = (int) $store->getWebsiteId();
        $accountId = $this->configHelper->getClientId($websiteId);
        $token = $this->configHelper->getFluxToken($websiteId);
        if (empty($accountId) || empty($token)) {
            return null;
        }

        $baseUrl = $store->getBaseUrl();
        return [
            "endpoint" => $this->getUrl("kilibam2/push/index", ["_secure" => true]),
            "configurationUrl" => $this->pushGateway->getPublicConfigurationUrl($accountId),
            "locale" => $this->localeResolver->getLocale(),
            "namespace" => $this->pushGateway->getBrowserNamespace($accountId, $baseUrl),
            "scope" => $this->pushGateway->getServiceWorkerScope($accountId, $baseUrl),
        ];
    }
}
