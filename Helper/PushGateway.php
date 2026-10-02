<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class PushGateway extends AbstractHelper
{
    const DEFAULT_ENDPOINT = "https://production.event-gateway.kiliba.ai/push/";
    const DEFAULT_PUBLIC_CONFIGURATION_BASE_URL = "https://app.kiliba.com/push-config/accounts/";
    const TIMEOUT = 10;
    const CONNECT_TIMEOUT = 3;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    public function __construct(Context $context, ConfigHelper $configHelper)
    {
        parent::__construct($context);
        $this->configHelper = $configHelper;
    }

    /** Resolve the authenticated Event Gateway endpoint, allowing controlled preproduction overrides. */
    protected function getEndpoint()
    {
        $override = getenv("KILIBA_PUSH_ENDPOINT");
        if ($override === false || $override === "") {
            $override = isset($_SERVER["KILIBA_PUSH_ENDPOINT"]) ? $_SERVER["KILIBA_PUSH_ENDPOINT"] : "";
        }

        return $override
            ? rtrim(trim((string) $override), "/") . "/"
            : self::DEFAULT_ENDPOINT;
    }

    public function getPublicConfigurationUrl($accountId)
    {
        $override = getenv("KILIBA_PUSH_CONFIG_BASE_URL");
        if ($override === false || $override === "") {
            $override = isset($_SERVER["KILIBA_PUSH_CONFIG_BASE_URL"])
                ? $_SERVER["KILIBA_PUSH_CONFIG_BASE_URL"]
                : "";
        }
        if (!$override && strpos($this->getEndpoint(), "preprod") !== false) {
            $override = "https://preprod.kiliba.com/push-config/accounts/";
        }
        $baseUrl = $override
            ? rtrim(trim((string) $override), "/") . "/"
            : self::DEFAULT_PUBLIC_CONFIGURATION_BASE_URL;

        return $baseUrl . hash("sha256", trim((string) $accountId)) . ".json";
    }

    public function getBrowserNamespace($accountId, $shopBaseUrl)
    {
        return substr(hash("sha256", trim((string) $accountId) . "|" . rtrim((string) $shopBaseUrl, "/")), 0, 20);
    }

    public function getServiceWorkerScope($accountId, $shopBaseUrl)
    {
        $basePath = parse_url((string) $shopBaseUrl, PHP_URL_PATH);
        $basePath = "/" . trim(is_string($basePath) ? $basePath : "", "/");
        $basePath = $basePath === "/" ? "" : $basePath;

        return $basePath . "/kiliba-push/" . $this->getBrowserNamespace($accountId, $shopBaseUrl) . "/";
    }

    /**
     * Proxy a storefront request without ever exposing the website-scoped Kiliba token in the browser.
     *
     * @param string $method
     * @param string $path
     * @param array|null $payload
     * @param int $websiteId
     * @return array
     */
    public function request($method, $path, $payload, $websiteId)
    {
        $accountId = $this->configHelper->getClientId($websiteId);
        $token = $this->configHelper->getFluxToken($websiteId);
        if (empty($accountId) || empty($token)) {
            return ["status" => 401, "payload" => ["error" => "missing_credentials"]];
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->getEndpoint() . ltrim((string) $path, "/"),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => strtoupper((string) $method),
            CURLOPT_HTTPHEADER => [
                "Cache-Control: no-cache",
                "Content-Type: application/json",
                "X-Kiliba-Token: " . base64_encode($accountId . ":" . $token),
            ],
        ]);
        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        $decoded = json_decode((string) $response, true);

        return [
            "status" => $status ?: 502,
            "payload" => is_array($decoded) ? $decoded : ["error" => $error ?: "invalid_gateway_response"],
        ];
    }
}
