<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Controller\Push;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\PushGateway;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

abstract class IndexBase extends Action
{
    /** @var ConfigHelper */
    protected $configHelper;

    /** @var PushGateway */
    protected $pushGateway;

    /** @var CustomerSession */
    protected $customerSession;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /** @var ModuleListInterface */
    protected $moduleList;

    /** @var ScopeConfigInterface */
    protected $scopeConfig;

    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        PushGateway $pushGateway,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleList,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->configHelper = $configHelper;
        $this->pushGateway = $pushGateway;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->moduleList = $moduleList;
        $this->scopeConfig = $scopeConfig;
    }

    protected function respondJson(array $payload, $status = 200)
    {
        return $this->getResponse()
            ->setHttpResponseCode((int) $status)
            ->setHeader("Content-Type", "application/json; charset=utf-8", true)
            ->setHeader("Cache-Control", "no-store", true)
            ->setBody(json_encode($payload));
    }

    protected function getWebsiteId()
    {
        return (int) $this->storeManager->getStore()->getWebsiteId();
    }

    protected function getAccountId()
    {
        return $this->configHelper->getClientId($this->getWebsiteId());
    }

    protected function getModuleVersion()
    {
        $module = $this->moduleList->getOne("Kiliba_Connector");
        return !empty($module["setup_version"]) ? (string) $module["setup_version"] : "";
    }

    protected function getBasePath($baseUrl)
    {
        $basePath = parse_url((string) $baseUrl, PHP_URL_PATH);
        $basePath = "/" . trim(is_string($basePath) ? $basePath : "", "/");
        return $basePath === "/" ? "/" : $basePath . "/";
    }

    protected function normalizedOrigin($url)
    {
        $parts = parse_url((string) $url);
        if (!is_array($parts) || empty($parts["scheme"]) || empty($parts["host"])) {
            return "";
        }
        $scheme = strtolower((string) $parts["scheme"]);
        $origin = $scheme . "://" . strtolower((string) $parts["host"]);
        if (isset($parts["port"])
            && !(($scheme === "https" && (int) $parts["port"] === 443)
                || ($scheme === "http" && (int) $parts["port"] === 80))
        ) {
            $origin .= ":" . (int) $parts["port"];
        }
        return $origin;
    }

    protected function validateMutationRequest()
    {
        $contentType = strtolower(trim((string) $this->getRequest()->getHeader("Content-Type")));
        if (!preg_match("#^application/json(?:\\s*;|$)#", $contentType)) {
            return $this->respondJson(["error" => "unsupported_media_type"], 415);
        }

        $rawOrigin = trim((string) $this->getRequest()->getHeader("Origin"));
        $origin = $rawOrigin !== "" ? $this->normalizedOrigin($rawOrigin) : "";
        $expectedOrigin = $this->normalizedOrigin($this->storeManager->getStore()->getBaseUrl());
        if ($rawOrigin !== "" && ($origin === "" || $expectedOrigin === "" || !hash_equals($expectedOrigin, $origin))) {
            return $this->respondJson(["error" => "invalid_origin"], 403);
        }
        if (strtolower((string) $this->getRequest()->getHeader("Sec-Fetch-Site")) === "cross-site") {
            return $this->respondJson(["error" => "invalid_origin"], 403);
        }
        return null;
    }

    protected function addIdentityToPayload(array $payload)
    {
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $email = strtolower(trim((string) $customer->getEmail()));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $payload["email"] = $email;
                $payload["customerId"] = (string) $customer->getId();
                $payload["identitySource"] = "magento_session";
                return $payload;
            }
        }

        $email = isset($payload["email"]) ? strtolower(trim((string) $payload["email"])) : "";
        if ($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $payload["email"] = $email;
            unset($payload["customerId"]);
            $payload["identitySource"] = "visitor_email";
        } else {
            unset($payload["email"], $payload["customerId"]);
            $payload["identitySource"] = "anonymous";
        }
        return $payload;
    }

    protected function renderWorker($accountId, $baseUrl)
    {
        $trackingScript = 'function kilibaPushTrackingUrl(){var u=new URL(self.location.href);u.searchParams.delete("worker");return u.toString()}function kilibaTrackPush(t,d){if(!d||!d.token){return Promise.resolve()}return fetch(kilibaPushTrackingUrl(),{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"event",eventType:t,trackingToken:d.token})}).then(function(r){if(!r.ok){throw new Error("tracking_failed")}}).catch(function(){})}self.addEventListener("push",function(e){var p={};try{p=e.data?e.data.json():{}}catch(x){}e.waitUntil(self.registration.showNotification(p.title||"Notification",{body:p.body||"",icon:p.icon||undefined,badge:p.badge||undefined,data:{url:p.url||"/",tracking:p.tracking||null}}).then(function(){return kilibaTrackPush("displayed",p.tracking)}))});self.addEventListener("notificationclick",function(e){e.notification.close();var d=e.notification.data||{};e.waitUntil(Promise.all([kilibaTrackPush("clicked",d.tracking),clients.openWindow(d.url||"/")]))});';
        return $this->getResponse()
            ->setHeader("Content-Type", "application/javascript; charset=utf-8", true)
            ->setHeader("Cache-Control", "no-cache", true)
            ->setHeader("Service-Worker-Allowed", $this->pushGateway->getServiceWorkerScope($accountId, $baseUrl), true)
            ->setBody($trackingScript);
    }

    protected function renderManifest($accountId, $baseUrl)
    {
        $store = $this->storeManager->getStore();
        $name = trim((string) $this->scopeConfig
            ->getValue("general/store_information/name", ScopeInterface::SCOPE_STORE, $store->getId()));
        if ($name === "") {
            $name = (string) $store->getName();
        }
        $icon = trim((string) $this->getRequest()->getParam("icon", ""));
        if ($icon !== "" && (!filter_var($icon, FILTER_VALIDATE_URL) || strtolower((string) parse_url($icon, PHP_URL_SCHEME)) !== "https")) {
            $icon = "";
        }
        $language = strtolower(substr(trim((string) $this->getRequest()->getParam("lang", "")), 0, 2));
        if (!in_array($language, ["fr", "en", "es", "de", "it", "nl", "pt", "nb", "sv"], true)) {
            $language = "";
        }
        $manifest = [
            "id" => $this->pushGateway->getServiceWorkerScope($accountId, $baseUrl),
            "name" => function_exists("mb_substr") ? mb_substr($name, 0, 80) : substr($name, 0, 80),
            "short_name" => function_exists("mb_substr") ? mb_substr($name, 0, 30) : substr($name, 0, 30),
            "start_url" => $this->getBasePath($baseUrl),
            "scope" => $this->getBasePath($baseUrl),
            "display" => "standalone",
            "background_color" => "#ffffff",
            "theme_color" => "#ffffff",
        ];
        if ($language !== "") {
            $manifest["lang"] = $language;
        }
        if ($icon !== "") {
            $manifest["icons"] = [["src" => $icon, "sizes" => "any", "purpose" => "any"]];
        }

        return $this->getResponse()
            ->setHeader("Content-Type", "application/manifest+json; charset=utf-8", true)
            ->setHeader("Cache-Control", "public, max-age=86400", true)
            ->setBody(json_encode($manifest));
    }

    public function execute()
    {
        $request = $this->getRequest();
        $store = $this->storeManager->getStore();
        $baseUrl = $store->getBaseUrl();
        $accountId = $this->getAccountId();
        if (empty($accountId) || empty($this->configHelper->getFluxToken($this->getWebsiteId()))) {
            return $this->respondJson(["error" => "missing_credentials"], 401);
        }

        if ($request->getParam("manifest")) {
            return $request->isGet()
                ? $this->renderManifest($accountId, $baseUrl)
                : $this->respondJson(["error" => "method_not_allowed"], 405);
        }
        if ($request->getParam("worker")) {
            return $request->isGet()
                ? $this->renderWorker($accountId, $baseUrl)
                : $this->respondJson(["error" => "method_not_allowed"], 405);
        }
        if ($request->isGet()) {
            $result = $this->pushGateway->request(
                "GET",
                "config?platform=magento&runtimeVersion=" . rawurlencode($this->getModuleVersion()),
                null,
                $this->getWebsiteId()
            );
            return $this->respondJson($result["payload"], $result["status"]);
        }
        if (!$request->isPost()) {
            return $this->respondJson(["error" => "method_not_allowed"], 405);
        }
        $invalidResponse = $this->validateMutationRequest();
        if ($invalidResponse !== null) {
            return $invalidResponse;
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->respondJson(["error" => "invalid_json"], 400);
        }
        $action = isset($payload["action"]) ? (string) $payload["action"] : "";
        $subscriptionId = isset($payload["subscriptionId"]) ? (string) $payload["subscriptionId"] : "";
        $websiteId = $this->getWebsiteId();

        if ($action === "subscribe") {
            $payload = $this->addIdentityToPayload($payload);
            unset($payload["action"]);
            $payload["platform"] = "magento";
            $payload["runtimeVersion"] = $this->getModuleVersion();
            $payload["shop"] = $baseUrl;
            $result = $this->pushGateway->request("POST", "subscriptions", $payload, $websiteId);
        } elseif ($action === "event") {
            $eventType = isset($payload["eventType"]) ? (string) $payload["eventType"] : "";
            if (!in_array($eventType, ["displayed", "clicked"], true)) {
                return $this->respondJson(["error" => "invalid_event_type"], 400);
            }
            $result = $this->pushGateway->request("POST", "events", [
                "eventType" => $eventType,
                "trackingToken" => isset($payload["trackingToken"]) ? (string) $payload["trackingToken"] : "",
            ], $websiteId);
        } elseif ($subscriptionId !== "" && $action === "unsubscribe") {
            $result = $this->pushGateway->request("DELETE", "subscriptions/" . rawurlencode($subscriptionId), null, $websiteId);
        } elseif ($subscriptionId !== "" && $action === "identity") {
            $payload = $this->addIdentityToPayload($payload);
            $result = $this->pushGateway->request("PATCH", "subscriptions/" . rawurlencode($subscriptionId), [
                "email" => isset($payload["email"]) ? $payload["email"] : null,
                "identitySource" => $payload["identitySource"],
                "status" => "active",
            ], $websiteId);
        } elseif ($subscriptionId !== "" && in_array($action, ["heartbeat", "revoked"], true)) {
            $result = $this->pushGateway->request("PATCH", "subscriptions/" . rawurlencode($subscriptionId), [
                "status" => $action === "revoked" ? "revoked" : "active",
            ], $websiteId);
        } else {
            return $this->respondJson(["error" => "invalid_action"], 400);
        }

        return $this->respondJson($result["payload"], $result["status"]);
    }
}

/*
 * Magento 2.3 introduced global CSRF validation and CsrfAwareActionInterface.
 * Keep Magento 2.2 loadable while opting into the JSON/same-origin validation
 * implemented by IndexBase whenever the newer framework contract is present.
 */
if (interface_exists(\Magento\Framework\App\CsrfAwareActionInterface::class)) {
    class Index extends IndexBase implements \Magento\Framework\App\CsrfAwareActionInterface
    {
        public function createCsrfValidationException(
            \Magento\Framework\App\RequestInterface $request
        ): ?\Magento\Framework\App\Request\InvalidRequestException {
            return null;
        }

        public function validateForCsrf(\Magento\Framework\App\RequestInterface $request): ?bool
        {
            return true;
        }
    }
} else {
    class Index extends IndexBase
    {
    }
}
