<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Api;

use Kiliba\Connector\Api\Module\PopupInterface;
use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Helper\KilibaCaller;
use Kiliba\Connector\Helper\CustomerHelper;
use Kiliba\Connector\Model\PopupCustomerFactory;
use Kiliba\Connector\Model\ResourceModel\PopupCustomer as PopupCustomerResource;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Kiliba\Connector\Model\Import\DeletedItem;
use Kiliba\Connector\Model\Import\Visit;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Customer\Helper\Session\CurrentCustomer;

class Popup extends AbstractApiAction implements PopupInterface
{
    /**
     * @var KilibaCaller
     */
    protected $kilibaCaller;

    /**
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var PopupCustomerFactory
     */
    protected $popupCustomerFactory;

    /**
     * @var PopupCustomerResource
     */
    protected $popupCustomerResource;

    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Resolver
     */
    protected $localeResolver;

    /**
     * @var CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @param RequestInterface $request
     * @param ResourceConnection $resourceConnection
     * @param ConfigHelper $configHelper
     * @param KilibaLogger $kilibaLogger
     * @param Visit $visitManager
     * @param DeletedItem $deletedItemManager
     * @param KilibaCaller $kilibaCaller
     * @param CustomerHelper $customerHelper
     * @param PopupCustomerFactory $popupCustomerFactory
     * @param PopupCustomerResource $popupCustomerResource
     * @param CurlFactory $curlFactory
     * @param SerializerInterface $serializer
     * @param StoreManagerInterface $storeManager
     * @param Resolver $localeResolver
     * @param CurrentCustomer $currentCustomer
     */
    public function __construct(
        RequestInterface $request,
        ResourceConnection $resourceConnection,
        ConfigHelper $configHelper,
        KilibaLogger $kilibaLogger,
        Visit $visitManager,
        DeletedItem $deletedItemManager,
        KilibaCaller $kilibaCaller,
        CustomerHelper $customerHelper,
        PopupCustomerFactory $popupCustomerFactory,
        PopupCustomerResource $popupCustomerResource,
        CurlFactory $curlFactory,
        SerializerInterface $serializer,
        StoreManagerInterface $storeManager,
        Resolver $localeResolver,
        CurrentCustomer $currentCustomer
    ) {
        parent::__construct($request, $resourceConnection, $configHelper, $kilibaLogger, $visitManager, $deletedItemManager);
        $this->kilibaCaller = $kilibaCaller;
        $this->customerHelper = $customerHelper;
        $this->popupCustomerFactory = $popupCustomerFactory;
        $this->popupCustomerResource = $popupCustomerResource;
        $this->curlFactory = $curlFactory;
        $this->serializer = $serializer;
        $this->storeManager = $storeManager;
        $this->localeResolver = $localeResolver;
        $this->currentCustomer = $currentCustomer;
    }

    /**
     * {@inheritdoc}
     */
    public function getPopupConfiguration($popupType)
    {
        try {
            // Get website ID from current store context
            $websiteId = $this->storeManager->getStore()->getWebsiteId();

            // Map popup type to configuration keys
            if ($popupType === "promoCodeFirstPurchase") {
                $configurationConfigKey = ConfigHelper::XML_PATH_POPUP_PROMOCODEFIRSTPURCHASE_CONFIGURATION;
                $activationConfigKey = ConfigHelper::XML_PATH_POPUP_PROMOCODEFIRSTPURCHASE_ACTIVATION;
            } else {
                return [[
                    "success" => false,
                    "message" => "Invalid popup type"
                ]];
            }

            // Get popup activation date
            $popupActivation = $this->_configHelper->getConfigWithoutCache(
                $activationConfigKey,
                $websiteId
            );

            if ($popupActivation) {
                $popupActivation = intval($popupActivation);
            } else {
                $popupActivation = 0;
            }

            // Validate activation: must be > 0 and less than current time
            $currentTime = time();
            if ($popupActivation <= 0 || $popupActivation > $currentTime) {
                return [[
                    "success" => false,
                    "message" => "Popup is not active"
                ]];
            }

            // Get popup configuration
            $popupConfig = $this->_configHelper->getConfigWithoutCache(
                $configurationConfigKey,
                $websiteId
            );

            if ($popupConfig) {
                $popupConfig = json_decode($popupConfig, true);

                if (isset($popupConfig["version"]) && intval($popupConfig["version"]) === 2) {
                    $popupConfig = $this->filterCampaignsByResolvedAudience($popupConfig, $websiteId);
                    if (!$popupConfig) {
                        return [[
                            "success" => false,
                            "message" => "Popup is not active"
                        ]];
                    }
                    return [[
                        "success" => true,
                        "configuration" => $this->hidePopupPrivateConfiguration($popupConfig)
                    ]];
                }

                // Hide captcha secret key from frontend
                $popupConfig = $this->hidePopupPrivateConfiguration($popupConfig);

                // Check eligibility based on popup configuration
                $eligibilityMode = isset($popupConfig['eligibilityMode']) ? $popupConfig['eligibilityMode'] : null;
                
                // Get customer email from session or cookie
                $customerEmail = null;
                
                // Try to get from current customer session first
                try {
                    $customerId = $this->currentCustomer->getCustomerId();
                    if ($customerId) {
                        $customer = $this->currentCustomer->getCustomer();
                        $customerEmail = $customer->getEmail();
                    }
                } catch (\Exception $e) {
                    // Customer session not available in API context
                }

                // Check eligibility
                if ($eligibilityMode === 'first-purchase') {
                    // For first-purchase mode: allow if user is not connected OR hasn't made any order
                    if ($customerEmail) {
                        // User is connected, check if they have orders
                        $hasOrdered = $this->customerHelper->hasEmailOrdered($customerEmail);
                        if ($hasOrdered) {
                            return [[
                                "success" => false,
                                "message" => "Customer not eligible"
                            ]];
                        }
                    }
                    // User not connected or hasn't made orders - eligible
                } else if ($eligibilityMode !== 'all') {
                    // For other modes: only allow if user is not connected
                    if ($customerEmail) {
                        return [[
                            "success" => false,
                            "message" => "Customer not eligible"
                        ]];
                    }
                }
            } else {
                $popupConfig = null;
            }

            return [[
                "success" => true,
                "configuration" => $popupConfig
            ]];

        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                'Get Popup Configuration Error',
                $e->getMessage(),
                0
            );
            return [[
                "success" => false,
                "message" => "Internal error"
            ]];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPopupPreview($popupType)
    {
        try {
            if ($popupType !== "promoCodeFirstPurchase") {
                return [[
                    "success" => false,
                    "message" => "Invalid popup type"
                ]];
            }

            $token = trim((string)$this->_request->getParam('token'));
            if ($token === '') {
                return [[
                    "success" => false,
                    "message" => "Missing token"
                ]];
            }

            $previewPayload = $this->kilibaCaller->resolvePopupPreview($token);
            if (!is_array($previewPayload) || !isset($previewPayload['type']) || !isset($previewPayload['data'])) {
                return [[
                    "success" => false,
                    "message" => "Preview not found"
                ]];
            }

            return [[
                "success" => true,
                "payload" => [
                    "type" => $previewPayload['type'],
                    "lang" => isset($previewPayload['lang']) ? $previewPayload['lang'] : '',
                    "data" => $previewPayload['data'],
                ]
            ]];
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                'Get Popup Preview Error',
                $e->getMessage(),
                0
            );
            return [[
                "success" => false,
                "message" => "Internal error"
            ]];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerSubscription(
        $popupType,
        $email = null,
        $subscribe = false,
        $phone = null,
        $birthday = null,
        $optin_sms = false,
        $captcha = null,
        $campaignId = null,
        $quizAnswers = null
    ) {
        try {
            if (!$email) {
                $this->logRegistrationFailure($email, "Missing email");
                sleep(2);
                return $this->jsonResponse(400, "MISSING_EMAIL");
            }

            // Map popup type to configuration keys
            if ($popupType === "promoCodeFirstPurchase") {
                $configurationConfigKey = ConfigHelper::XML_PATH_POPUP_PROMOCODEFIRSTPURCHASE_CONFIGURATION;
                $activationConfigKey = ConfigHelper::XML_PATH_POPUP_PROMOCODEFIRSTPURCHASE_ACTIVATION;
                $popupIdentifier = "promo-code-first-purchase";
            } else {
                $this->logRegistrationFailure($email, "Invalid popup type: " . $popupType);
                sleep(2);
                return $this->jsonResponse(400, "POPUP_UNAVAILABLE");
            }

            // Get store and website ID from current store context (not from user input for security)
            $store = $this->storeManager->getStore();
            $storeId = $store->getId();
            $websiteId = $store->getWebsiteId();

            // Check if popup is activated
            $popupActivationDate = $this->_configHelper->getConfigWithoutCache(
                $activationConfigKey,
                $websiteId
            );

            if (!$popupActivationDate || intval($popupActivationDate) > time()) {
                $this->logRegistrationFailure($email, "Popup disabled");
                sleep(2);
                return $this->jsonResponse(400, "POPUP_UNAVAILABLE");
            }

            // Check email format
            if (strpos($email, "@") === false) {
                $this->logRegistrationFailure($email, "Invalid email format");
                sleep(2);
                return $this->jsonResponse(400, "INVALID_EMAIL");
            }

            // Get subscription status
            $hasSubscribed = $subscribe === true || $subscribe === 'true';
            $phone = trim((string)$phone);
            $rawBirthday = trim((string)$birthday);
            $optinSms = $optin_sms === true || $optin_sms === 'true';

            // Check lang (store locale)
            $idLang = $this->_configHelper->getStoreLocale($storeId);
            if (!$idLang) {
                $this->logRegistrationFailure($email, "Missing lang");
                sleep(2);
                return $this->jsonResponse(400, "MISSING_LANG");
            }

            // Get popup configuration
            $popupConfigJson = $this->_configHelper->getConfigWithoutCache(
                $configurationConfigKey,
                $websiteId
            );
            $popupConfiguration = json_decode($popupConfigJson, true);
            $campaignId = (string)($campaignId ?: '');
            $popupEffectiveConfiguration = $this->getCampaignSubmissionConfiguration($popupConfiguration, $campaignId);
            if (!$popupEffectiveConfiguration) {
                $this->logRegistrationFailure($email, "Popup campaign unavailable");
                sleep(2);
                return $this->jsonResponse(400, "POPUP_UNAVAILABLE");
            }
            $isAllEligibility = isset($popupEffectiveConfiguration['eligibilityMode'])
                && $popupEffectiveConfiguration['eligibilityMode'] === 'all';
            $trustedCustomerId = null;
            $trustedCustomerEmail = null;
            $fallbackCountryCode = $this->getPopupGeoFallbackCountryCode();
            $fallbackRegionCode = $this->getPopupGeoFallbackRegionCode();
            try {
                $trustedCustomerId = $this->currentCustomer->getCustomerId();
                if ($trustedCustomerId) {
                    $customer = $this->currentCustomer->getCustomer();
                    $trustedCustomerEmail = $customer->getEmail();
                }
            } catch (\Exception $e) {
                $trustedCustomerId = null;
                $trustedCustomerEmail = null;
            }

            if ($this->campaignRequiresAdvancedAudience(["audienceRules" => $popupEffectiveConfiguration])) {
                $submitAudience = $this->kilibaCaller->resolvePopupAudience(
                    $trustedCustomerId ? (string) $trustedCustomerId : "",
                    $email ? (string) $email : "",
                    isset($popupEffectiveConfiguration["newsletterState"]) ? $popupEffectiveConfiguration["newsletterState"] : "any",
                    array_values(array_unique(array_map("strval", array_merge(
                        isset($popupEffectiveConfiguration["customerGroupIds"]) && is_array($popupEffectiveConfiguration["customerGroupIds"]) ? $popupEffectiveConfiguration["customerGroupIds"] : [],
                        isset($popupEffectiveConfiguration["excludedCustomerGroupIds"]) && is_array($popupEffectiveConfiguration["excludedCustomerGroupIds"]) ? $popupEffectiveConfiguration["excludedCustomerGroupIds"] : []
                    )))),
                    array_values(array_unique(array_map("strval", array_merge(
                        isset($popupEffectiveConfiguration["dynamicSegmentIds"]) && is_array($popupEffectiveConfiguration["dynamicSegmentIds"]) ? $popupEffectiveConfiguration["dynamicSegmentIds"] : [],
                        isset($popupEffectiveConfiguration["excludedDynamicSegmentIds"]) && is_array($popupEffectiveConfiguration["excludedDynamicSegmentIds"]) ? $popupEffectiveConfiguration["excludedDynamicSegmentIds"] : []
                    )))),
                    $websiteId
                );
                if (!$this->campaignMatchesResolvedAudience(["audienceRules" => $popupEffectiveConfiguration], $submitAudience)) {
                    $this->logRegistrationFailure($email, "Customer not eligible for advanced audience rules");
                    sleep(2);
                    return $this->jsonResponse(400, "CUSTOMER_NOT_ELIGIBLE");
                }
            }

            if ($this->campaignRequiresServerSideGeoValidation(["geoRules" => isset($popupEffectiveConfiguration["geoRules"]) ? $popupEffectiveConfiguration["geoRules"] : []])) {
                $submitGeo = $this->kilibaCaller->resolvePopupGeo(
                    $trustedCustomerId ? (string) $trustedCustomerId : "",
                    $trustedCustomerEmail ? (string) $trustedCustomerEmail : "",
                    array_values(array_unique(array_map("strval", isset($popupEffectiveConfiguration["geoRules"]["geographicalSegmentIds"]) && is_array($popupEffectiveConfiguration["geoRules"]["geographicalSegmentIds"]) ? $popupEffectiveConfiguration["geoRules"]["geographicalSegmentIds"] : []))),
                    $websiteId,
                    $fallbackCountryCode,
                    $fallbackRegionCode
                );
                if (!$this->campaignMatchesResolvedGeo(["geoRules" => isset($popupEffectiveConfiguration["geoRules"]) ? $popupEffectiveConfiguration["geoRules"] : []], $submitGeo)) {
                    $this->logRegistrationFailure($email, "Customer not eligible for popup geo rules");
                    sleep(2);
                    return $this->jsonResponse(400, "CUSTOMER_NOT_ELIGIBLE");
                }
            }

            // Check required subscription
            if (isset($popupEffectiveConfiguration['subscriptionCheckboxRequired']) 
                && $popupEffectiveConfiguration['subscriptionCheckboxRequired'] 
                && !$hasSubscribed
            ) {
                $this->logRegistrationFailure($email, "Subscription required");
                sleep(2);
                return $this->jsonResponse(400, "SUBSCRIPTION_REQUIRED");
            }
            if (!empty($popupEffectiveConfiguration['allowSmsOptin'])
                && !empty($popupEffectiveConfiguration['smsOptinRequiredWhenPhoneProvided'])
                && !empty($phone)
                && !$optinSms
            ) {
                $this->logRegistrationFailure($email, "SMS opt-in required when phone is provided");
                sleep(2);
                return $this->jsonResponse(400, "SMS_OPTIN_REQUIRED");
            }

            $birthday = null;
            if ($rawBirthday !== '' && !empty($popupEffectiveConfiguration['allowBirthday'])) {
                $birthday = $this->normalizeBirthday($rawBirthday);
                if ($birthday === null) {
                    $this->logRegistrationFailure($email, "Invalid birthday");
                    sleep(2);
                    return $this->jsonResponse(400, "INVALID_BIRTHDAY");
                }
            }

            // Check CAPTCHA
            if (isset($popupEffectiveConfiguration['captcha']) 
                && isset($popupEffectiveConfiguration['captcha']['type']) 
                && $popupEffectiveConfiguration['captcha']['type'] === "reCAPTCHA v2"
            ) {
                if (!isset($popupEffectiveConfiguration['captcha']['secretkey'])) {
                    $this->logRegistrationFailure($email, "Missing captcha secret key");
                    sleep(2);
                    return $this->jsonResponse(400, "INVALID_CAPTCHA_SETUP");
                }
                $captchaVerification = $this->verifyCaptcha($popupEffectiveConfiguration['captcha']['secretkey'], $captcha);
                if (!$captchaVerification || !$captchaVerification->success) {
                    $this->logRegistrationFailure($email, "Invalid captcha token");
                    sleep(2);
                    return $this->jsonResponse(400, "INVALID_CAPTCHA_TOKEN");
                }
            }

            // Check if customer is eligible to popup
            if(!$isAllEligibility) {
                if (isset($popupEffectiveConfiguration['eligibilityMode']) 
                    && $popupEffectiveConfiguration['eligibilityMode'] === 'first-purchase'
                ) {
                    $hasEmailOrdered = $this->customerHelper->hasEmailOrdered($email);
                    if ($hasEmailOrdered) {
                        $this->logRegistrationFailure($email, "Customer not eligible");
                        sleep(2);
                        return $this->jsonResponse(400, "CUSTOMER_NOT_ELIGIBLE");
                    }
                } else {
                    $hasAccount = $this->customerHelper->hasAccount($email);
                    if ($hasAccount) {
                        $this->logRegistrationFailure($email, "Customer exists");
                        sleep(2);
                        return $this->jsonResponse(400, "CUSTOMER_EXISTS");
                    }
                }
            }

            // Check if customer email has already received an email for that popup
            if($this->popupCustomerResource->isEmailRegistered($email, $popupType, $campaignId)) {
                $this->logRegistrationFailure($email, "Customer already registered in popup");
                sleep(2);
                return $this->jsonResponse(400, "CUSTOMER_ALREADY_REGISTERED");
            }

            // Get client IP
            $ip = $_SERVER['REMOTE_ADDR'];
            $ipProxyHeader = $this->_configHelper->getConfigWithoutCache('kiliba/connector/proxy_header', $websiteId);
            if ($ipProxyHeader && isset($_SERVER[$ipProxyHeader])) {
                $proxyIp = $_SERVER[$ipProxyHeader];
                if ($proxyIp) {
                    $ip = $proxyIp;
                }
            }

            // If everything is OK, register the customer email for this popup
            $popupCustomer = $this->popupCustomerFactory->create();
            $popupCustomer->setPopupType($popupType);
            $popupCustomer->setData('campaign_id', $campaignId);
            $popupCustomer->setEmail($email);
            $popupCustomer->setData('phone', $phone);
            $popupCustomer->setData('birthday', $birthday);
            $quizAnswers = json_decode((string)$quizAnswers, true);
            $popupCustomer->setData('quiz_answers', is_array($quizAnswers) && count($quizAnswers) > 0 ? json_encode($quizAnswers) : null);
            $quizAttributes = $this->buildPopupQuizAttributes($campaignId, is_array($quizAnswers) ? $quizAnswers : [], $popupEffectiveConfiguration);
            $popupCustomer->setData('quiz_attributes', is_array($quizAttributes) && count($quizAttributes) > 0 ? json_encode($quizAttributes) : null);
            $popupCustomer->setSubscribe($hasSubscribed);
            $popupCustomer->setData('optin_sms', $optinSms ? 1 : 0);
            $popupCustomer->setSubscribeIp($hasSubscribed ? $ip : '');
            $popupCustomer->setWebsiteId($websiteId);

            if($isAllEligibility && $this->popupCustomerResource->isEmailRegistered($email, $popupType, $campaignId)) {
                $existing = $this->popupCustomerResource->getRegistrationsByEmailAndType($email, $popupType, $campaignId);
                if(is_array($existing) && count($existing) > 0) {
                    $popupCustomer->setData('popup_customer_id', $existing[0]['popup_customer_id']);
                }
            }

            $this->popupCustomerResource->save($popupCustomer);

            // Ping Kiliba
            $this->kilibaCaller->registerPopupSubscription(
                $popupIdentifier,
                ['email' => $email, 'subscribe' => $hasSubscribed, 'phone' => $phone, 'birthday' => $birthday, 'optin_sms' => $optinSms, 'campaign_id' => $campaignId, 'quiz_answers' => is_array($quizAnswers) ? $quizAnswers : []],
                $storeId,
                $websiteId
            );

            // Done
            return $this->jsonResponse(200, null);

        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                'Popup Registration Error',
                $e->getMessage(),
                0
            );
            return $this->jsonResponse(500, "INTERNAL_ERROR");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerDisplay($popupType, $displayData = null)
    {
        try {
            if ($popupType === "promoCodeFirstPurchase") {
                $popupIdentifier = "promo-code-first-purchase";
            } else {
                return $this->jsonResponse(400, "POPUP_UNAVAILABLE");
            }

            $store = $this->storeManager->getStore();
            $storeId = $store->getId();
            $websiteId = $store->getWebsiteId();

            if (is_string($displayData)) {
                $displayData = json_decode($displayData, true);
            }

            if (!is_array($displayData)) {
                return $this->jsonResponse(400, "INVALID_PAYLOAD");
            }

            $this->kilibaCaller->registerPopupDisplay($popupIdentifier, $displayData, $storeId, $websiteId);
            return $this->jsonResponse(200, null);
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                'Popup Display Error',
                $e->getMessage(),
                0
            );
            return $this->jsonResponse(500, "INTERNAL_ERROR");
        }
    }

    /**
     * Verify Google reCAPTCHA v2 token
     *
     * @param string $secret
     * @param string $token
     * @return mixed
     */
    private function verifyCaptcha($secret, $token)
    {
        try {
            $curl = $this->curlFactory->create();
            
            $curl->setOption(CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
            $curl->setOption(CURLOPT_ENCODING, '');
            $curl->setOption(CURLOPT_MAXREDIRS, 10);
            $curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $curl->setOption(CURLOPT_TIMEOUT, 10);
            $curl->setOption(CURLOPT_POST, 1);
            $curl->setOption(CURLOPT_POSTFIELDS, http_build_query([
                'secret' => $secret,
                'response' => $token
            ]));

            $curl->post('', '');
            $resp = $curl->getBody();

            return json_decode($resp);
        } catch (\Exception $e) {
            $this->_kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                'CAPTCHA Verification Error',
                $e->getMessage(),
                0
            );
            return null;
        }
    }

    /**
     * Log registration failure
     *
     * @param string $email
     * @param string $reason
     * @return void
     */
    private function logRegistrationFailure($email, $reason)
    {
        $this->_kilibaLogger->addLog(
            KilibaLogger::LOG_TYPE_WARNING,
            'Popup Registration Failed',
            "Email: {$email}, Reason: {$reason}",
            0
        );
    }

    /**
     * Return JSON response
     *
     * @param int $statusCode
     * @param string|null $error
     * @return array
     */
    private function jsonResponse($statusCode = 200, $error = null)
    {
        $response = ["success" => $statusCode === 200];
        
        if ($error !== null) {
            $response["error"] = $error;
        }

        $response["code"] = $statusCode;

        return [$response];
    }

    private function hidePopupPrivateConfiguration($configuration)
    {
        if (!is_array($configuration)) {
            return $configuration;
        }

        if (isset($configuration["version"]) && intval($configuration["version"]) === 2 && isset($configuration["campaigns"]) && is_array($configuration["campaigns"])) {
            foreach ($configuration["campaigns"] as &$campaign) {
                if (isset($campaign["captcha"]["secretkey"])) {
                    unset($campaign["captcha"]["secretkey"]);
                }
            }
            unset($campaign);
            return $configuration;
        }

        if (isset($configuration["captcha"]["secretkey"])) {
            unset($configuration["captcha"]["secretkey"]);
        }

        return $configuration;
    }

    private function getCampaignSubmissionConfiguration($configuration, $campaignId)
    {
        if (!is_array($configuration) || !isset($configuration["version"]) || intval($configuration["version"]) !== 2 || !isset($configuration["campaigns"]) || !is_array($configuration["campaigns"])) {
            return $configuration;
        }

        $normalizedCampaignId = trim((string) $campaignId);
        if (empty($normalizedCampaignId)) {
            return null;
        }

        $selectedCampaign = null;
        foreach ($configuration["campaigns"] as $campaign) {
            if (isset($campaign["status"]) && $campaign["status"] !== "active") {
                continue;
            }

            if (isset($campaign["id"]) && $campaign["id"] === $normalizedCampaignId) {
                $selectedCampaign = $campaign;
                break;
            }
        }

        if (!$selectedCampaign) {
            return null;
        }

        $campaignForm = isset($selectedCampaign["form"]) && is_array($selectedCampaign["form"])
            ? $selectedCampaign["form"]
            : [];

        $customerState = isset($selectedCampaign["audienceRules"]["customerState"]) ? $selectedCampaign["audienceRules"]["customerState"] : "new-customer";
        if ($customerState === "any" || (isset($selectedCampaign["audienceRules"]["identifiedState"]) && $selectedCampaign["audienceRules"]["identifiedState"] === "any")) {
            $eligibilityMode = "all";
        } elseif ($customerState === "first-purchase-eligible") {
            $eligibilityMode = "first-purchase";
        } else {
            $eligibilityMode = "new-customer";
        }

        return [
            "campaignId" => isset($selectedCampaign["id"]) ? (string) $selectedCampaign["id"] : "",
            "eligibilityMode" => $eligibilityMode,
            "allowBirthday" => !empty($campaignForm["allowBirthday"]),
            "subscriptionCheckboxRequired" => !empty($campaignForm["subscriptionCheckboxRequired"]),
            "captcha" => isset($selectedCampaign["captcha"]) ? $selectedCampaign["captcha"] : null,
            "allowSubscription" => !empty($campaignForm["allowSubscription"]),
            "allowSmsOptin" => !empty($campaignForm["allowSmsOptin"]),
            "sendEmailAfterSubmit" => !array_key_exists("sendEmailAfterSubmit", $campaignForm)
                || !empty($campaignForm["sendEmailAfterSubmit"]),
            "smsOptinRequiredWhenPhoneProvided" => !empty($campaignForm["smsOptinRequiredWhenPhoneProvided"]),
            "newsletterState" => isset($selectedCampaign["audienceRules"]["newsletterState"]) ? $selectedCampaign["audienceRules"]["newsletterState"] : "any",
            "excludedNewsletterState" => isset($selectedCampaign["audienceRules"]["excludedNewsletterState"]) ? $selectedCampaign["audienceRules"]["excludedNewsletterState"] : null,
            "customerGroupIds" => isset($selectedCampaign["audienceRules"]["customerGroupIds"]) && is_array($selectedCampaign["audienceRules"]["customerGroupIds"]) ? $selectedCampaign["audienceRules"]["customerGroupIds"] : [],
            "excludedCustomerGroupIds" => isset($selectedCampaign["audienceRules"]["excludedCustomerGroupIds"]) && is_array($selectedCampaign["audienceRules"]["excludedCustomerGroupIds"]) ? $selectedCampaign["audienceRules"]["excludedCustomerGroupIds"] : [],
            "dynamicSegmentIds" => isset($selectedCampaign["audienceRules"]["dynamicSegmentIds"]) && is_array($selectedCampaign["audienceRules"]["dynamicSegmentIds"]) ? $selectedCampaign["audienceRules"]["dynamicSegmentIds"] : [],
            "excludedDynamicSegmentIds" => isset($selectedCampaign["audienceRules"]["excludedDynamicSegmentIds"]) && is_array($selectedCampaign["audienceRules"]["excludedDynamicSegmentIds"]) ? $selectedCampaign["audienceRules"]["excludedDynamicSegmentIds"] : [],
            "geoRules" => isset($selectedCampaign["geoRules"]) && is_array($selectedCampaign["geoRules"]) ? $selectedCampaign["geoRules"] : [],
            "quizQuestions" => isset($campaignForm["quizQuestions"]) && is_array($campaignForm["quizQuestions"]) ? $campaignForm["quizQuestions"] : [],
        ];
    }

    /**
     * Normalize popup birthday values before local persistence and Kiliba forwarding.
     */
    private function normalizeBirthday($rawBirthday)
    {
        $normalizedBirthday = trim((string)$rawBirthday);
        if ($normalizedBirthday === '') {
            return null;
        }

        $supportedFormats = ['Y-m-d', 'd/m/Y', 'm/d/Y'];
        foreach ($supportedFormats as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $normalizedBirthday);
            $errors = \DateTime::getLastErrors();
            if (
                $date instanceof \DateTime
                && (
                    $errors === false
                    || (
                        is_array($errors)
                        && $errors['warning_count'] === 0
                        && $errors['error_count'] === 0
                    )
                )
            ) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($normalizedBirthday);
        if ($timestamp === false) {
            return null;
        }

        $date = new \DateTime('@' . $timestamp);
        $date->setTimezone(new \DateTimeZone('UTC'));
        return $date->format('Y-m-d');
    }

    private function slugifyPopupQuizSegmentKey($value)
    {
        $value = trim((string)$value);
        $transliteratedValue = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $value);
        if ($transliteratedValue !== false) {
            $value = $transliteratedValue;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);
        $value = trim((string)$value, '_');

        return $value !== '' ? $value : 'question';
    }

    private function buildUniquePopupQuizSlugMap(array $entries)
    {
        $slugCounts = [];
        foreach ($entries as $entry) {
            $baseSlug = $this->slugifyPopupQuizSegmentKey(isset($entry['label']) ? $entry['label'] : '');
            $slugCounts[$baseSlug] = isset($slugCounts[$baseSlug]) ? $slugCounts[$baseSlug] + 1 : 1;
        }

        $slugMap = [];
        foreach ($entries as $entry) {
            $entryId = isset($entry['id']) ? (string)$entry['id'] : '';
            $baseSlug = $this->slugifyPopupQuizSegmentKey(isset($entry['label']) ? $entry['label'] : '');
            $stableSuffix = strtolower(substr(preg_replace('/[^a-zA-Z0-9]/', '', $entryId), 0, 8));
            $slugMap[$entryId] = !empty($stableSuffix) && isset($slugCounts[$baseSlug]) && $slugCounts[$baseSlug] > 1
                ? $baseSlug . '_' . $stableSuffix
                : $baseSlug;
        }

        return $slugMap;
    }

    private function buildPopupQuizAttributes($campaignId, $quizAnswers, $popupEffectiveConfiguration)
    {
        $normalizedCampaignId = trim((string)$campaignId);
        if ($normalizedCampaignId === '' || !is_array($quizAnswers) || count($quizAnswers) === 0) {
            return null;
        }

        $quizQuestions = isset($popupEffectiveConfiguration['quizQuestions']) && is_array($popupEffectiveConfiguration['quizQuestions'])
            ? $popupEffectiveConfiguration['quizQuestions']
            : [];
        $normalizedQuestions = [];

        foreach ($quizQuestions as $question) {
            $questionId = isset($question['id']) ? trim((string)$question['id']) : '';
            $questionLabel = isset($question['label']) ? trim((string)$question['label']) : '';
            if ($questionId === '' || $questionLabel === '') {
                continue;
            }

            $normalizedChoices = [];
            if (isset($question['choices']) && is_array($question['choices'])) {
                foreach ($question['choices'] as $choice) {
                    $choiceId = isset($choice['id']) ? trim((string)$choice['id']) : '';
                    $choiceLabel = isset($choice['label']) ? trim((string)$choice['label']) : '';
                    if ($choiceId === '' || $choiceLabel === '') {
                        continue;
                    }

                    $normalizedChoices[] = [
                        'id' => $choiceId,
                        'label' => $choiceLabel,
                    ];
                }
            }

            $normalizedQuestions[] = [
                'id' => $questionId,
                'label' => $questionLabel,
                'type' => isset($question['type']) ? (string)$question['type'] : 'single-choice',
                'choices' => $normalizedChoices,
            ];
        }

        if (count($normalizedQuestions) === 0) {
            return null;
        }

        $questionSlugMap = $this->buildUniquePopupQuizSlugMap($normalizedQuestions);
        $answersByQuestionId = [];
        foreach ($quizAnswers as $answer) {
            $questionId = isset($answer['questionId']) ? trim((string)$answer['questionId']) : '';
            if ($questionId === '') {
                continue;
            }

            $values = [];
            if (isset($answer['values']) && is_array($answer['values'])) {
                foreach ($answer['values'] as $value) {
                    $normalizedValue = trim((string)$value);
                    if ($normalizedValue !== '') {
                        $values[] = $normalizedValue;
                    }
                }
            }

            $answersByQuestionId[$questionId] = $values;
        }

        $attributes = [];
        foreach ($normalizedQuestions as $question) {
            $questionId = $question['id'];
            $questionSlug = isset($questionSlugMap[$questionId]) ? $questionSlugMap[$questionId] : '';
            if ($questionSlug === '') {
                continue;
            }

            $baseAttributePath = 'popup_quiz.' . $normalizedCampaignId . '.' . $questionSlug;
            $selectedValues = isset($answersByQuestionId[$questionId]) ? $answersByQuestionId[$questionId] : [];
            if (!empty($question['choices']) && is_array($question['choices'])) {
                $normalizedSelectedValues = [];
                foreach ($selectedValues as $selectedValue) {
                    $selectedValue = trim((string)$selectedValue);
                    if ($selectedValue === '') {
                        continue;
                    }

                    $resolvedLabel = $selectedValue;
                    foreach ($question['choices'] as $choice) {
                        $choiceId = isset($choice['id']) ? trim((string)$choice['id']) : '';
                        $choiceLabel = isset($choice['label']) ? trim((string)$choice['label']) : '';
                        if ($selectedValue === $choiceId || $selectedValue === $choiceLabel) {
                            $resolvedLabel = $choiceLabel;
                            break;
                        }
                    }

                    $normalizedSelectedValues[] = $resolvedLabel;
                }
                $selectedValues = array_values(array_unique($normalizedSelectedValues));
            }

            if ($question['type'] === 'multi-choice') {
                $selectedValuesMap = array_fill_keys($selectedValues, true);
                $optionSlugMap = $this->buildUniquePopupQuizSlugMap(isset($question['choices']) ? $question['choices'] : []);

                foreach ($question['choices'] as $choice) {
                    $choiceId = $choice['id'];
                    $optionSlug = isset($optionSlugMap[$choiceId]) ? $optionSlugMap[$choiceId] : '';
                    if ($optionSlug === '') {
                        continue;
                    }

                    $attributes[$baseAttributePath . '.' . $optionSlug] = isset($selectedValuesMap[$choice['label']]);
                }
                continue;
            }

            $firstValue = isset($selectedValues[0]) ? trim((string)$selectedValues[0]) : '';
            if ($firstValue === '') {
                continue;
            }

            $attributes[$baseAttributePath] = $firstValue;
        }

        return count($attributes) > 0 ? $attributes : null;
    }

    private function campaignRequiresAdvancedAudience($campaign)
    {
        $newsletterState = isset($campaign["audienceRules"]["newsletterState"]) ? $campaign["audienceRules"]["newsletterState"] : "any";
        $excludedNewsletterState = isset($campaign["audienceRules"]["excludedNewsletterState"]) ? $campaign["audienceRules"]["excludedNewsletterState"] : null;
        $customerGroupIds = isset($campaign["audienceRules"]["customerGroupIds"]) && is_array($campaign["audienceRules"]["customerGroupIds"])
            ? $campaign["audienceRules"]["customerGroupIds"]
            : [];
        $excludedCustomerGroupIds = isset($campaign["audienceRules"]["excludedCustomerGroupIds"]) && is_array($campaign["audienceRules"]["excludedCustomerGroupIds"])
            ? $campaign["audienceRules"]["excludedCustomerGroupIds"]
            : [];
        $dynamicSegmentIds = isset($campaign["audienceRules"]["dynamicSegmentIds"]) && is_array($campaign["audienceRules"]["dynamicSegmentIds"])
            ? $campaign["audienceRules"]["dynamicSegmentIds"]
            : [];
        $excludedDynamicSegmentIds = isset($campaign["audienceRules"]["excludedDynamicSegmentIds"]) && is_array($campaign["audienceRules"]["excludedDynamicSegmentIds"])
            ? $campaign["audienceRules"]["excludedDynamicSegmentIds"]
            : [];

        return $newsletterState !== "any"
            || !empty($excludedNewsletterState)
            || count($customerGroupIds) > 0
            || count($excludedCustomerGroupIds) > 0
            || count($dynamicSegmentIds) > 0
            || count($excludedDynamicSegmentIds) > 0;
    }

    private function campaignMatchesResolvedAudience($campaign, $audience)
    {
        if (!$this->campaignRequiresAdvancedAudience($campaign)) {
            return true;
        }
        if (!is_array($audience)) {
            return false;
        }

        $newsletterState = isset($campaign["audienceRules"]["newsletterState"]) ? $campaign["audienceRules"]["newsletterState"] : "any";
        if ($newsletterState !== "any" && (!isset($audience["newsletterStatus"]) || $audience["newsletterStatus"] !== $newsletterState)) {
            return false;
        }
        $excludedNewsletterState = isset($campaign["audienceRules"]["excludedNewsletterState"]) ? $campaign["audienceRules"]["excludedNewsletterState"] : null;
        if (!empty($excludedNewsletterState) && isset($audience["newsletterStatus"]) && $audience["newsletterStatus"] === $excludedNewsletterState) {
            return false;
        }

        $customerGroupIds = isset($campaign["audienceRules"]["customerGroupIds"]) && is_array($campaign["audienceRules"]["customerGroupIds"])
            ? $campaign["audienceRules"]["customerGroupIds"]
            : [];
        if (count($customerGroupIds) > 0) {
            $resolvedGroups = isset($audience["customerGroupIds"]) && is_array($audience["customerGroupIds"]) ? $audience["customerGroupIds"] : [];
            if (count(array_intersect($customerGroupIds, $resolvedGroups)) === 0) {
                return false;
            }
        }
        $excludedCustomerGroupIds = isset($campaign["audienceRules"]["excludedCustomerGroupIds"]) && is_array($campaign["audienceRules"]["excludedCustomerGroupIds"])
            ? $campaign["audienceRules"]["excludedCustomerGroupIds"]
            : [];
        if (count($excludedCustomerGroupIds) > 0) {
            $resolvedGroups = isset($audience["customerGroupIds"]) && is_array($audience["customerGroupIds"]) ? $audience["customerGroupIds"] : [];
            if (count(array_intersect($excludedCustomerGroupIds, $resolvedGroups)) > 0) {
                return false;
            }
        }

        $dynamicSegmentIds = isset($campaign["audienceRules"]["dynamicSegmentIds"]) && is_array($campaign["audienceRules"]["dynamicSegmentIds"])
            ? $campaign["audienceRules"]["dynamicSegmentIds"]
            : [];
        if (count($dynamicSegmentIds) > 0) {
            $resolvedSegments = isset($audience["dynamicSegmentIds"]) && is_array($audience["dynamicSegmentIds"]) ? $audience["dynamicSegmentIds"] : [];
            if (count(array_intersect($dynamicSegmentIds, $resolvedSegments)) === 0) {
                return false;
            }
        }
        $excludedDynamicSegmentIds = isset($campaign["audienceRules"]["excludedDynamicSegmentIds"]) && is_array($campaign["audienceRules"]["excludedDynamicSegmentIds"])
            ? $campaign["audienceRules"]["excludedDynamicSegmentIds"]
            : [];
        if (count($excludedDynamicSegmentIds) > 0) {
            $resolvedSegments = isset($audience["dynamicSegmentIds"]) && is_array($audience["dynamicSegmentIds"]) ? $audience["dynamicSegmentIds"] : [];
            if (count(array_intersect($excludedDynamicSegmentIds, $resolvedSegments)) > 0) {
                return false;
            }
        }

        return true;
    }

    private function campaignRequiresServerSideGeoValidation($campaign)
    {
        $nearestStoreEnabled = !empty($campaign["geoRules"]["nearestStoreEnabled"]);
        if ($nearestStoreEnabled) {
            return false;
        }

        $countryCodes = isset($campaign["geoRules"]["countryCodes"]) && is_array($campaign["geoRules"]["countryCodes"]) ? $campaign["geoRules"]["countryCodes"] : [];
        $regionCodes = isset($campaign["geoRules"]["regionCodes"]) && is_array($campaign["geoRules"]["regionCodes"]) ? $campaign["geoRules"]["regionCodes"] : [];
        $geographicalSegmentIds = isset($campaign["geoRules"]["geographicalSegmentIds"]) && is_array($campaign["geoRules"]["geographicalSegmentIds"]) ? $campaign["geoRules"]["geographicalSegmentIds"] : [];

        return count($countryCodes) > 0 || count($regionCodes) > 0 || count($geographicalSegmentIds) > 0;
    }

    /**
     * Avoid backend audience resolution when no trusted identifier is available.
     */
    private function shouldResolvePopupAudience($customerId, $email)
    {
        return !empty(trim((string) $customerId)) || !empty(trim((string) $email));
    }

    /**
     * Avoid backend geo resolution when no trusted identifier is available.
     */
    private function shouldResolvePopupGeo($customerId, $email)
    {
        return !empty(trim((string) $customerId)) || !empty(trim((string) $email));
    }

    /**
     * Resolve the storefront country code when it is already exposed by the infrastructure.
     */
    private function getPopupGeoFallbackCountryCode()
    {
        if (isset($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'])) {
            $countryCode = strtoupper(trim((string) $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY']));
            if (!empty($countryCode)) {
                return $countryCode;
            }
        }
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $countryCode = strtoupper(trim((string) $_SERVER['HTTP_CF_IPCOUNTRY']));
            if (!empty($countryCode)) {
                return $countryCode;
            }
        }

        return null;
    }

    /**
     * Resolve the storefront region code when it is already exposed by the infrastructure.
     */
    private function getPopupGeoFallbackRegionCode()
    {
        if (isset($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION'])) {
            $regionCode = strtoupper(trim((string) $_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION']));
            if (!empty($regionCode)) {
                return $regionCode;
            }
        }

        return null;
    }

    private function campaignMatchesResolvedGeo($campaign, $geo)
    {
        if (!$this->campaignRequiresServerSideGeoValidation($campaign)) {
            return true;
        }
        if (!is_array($geo)) {
            return false;
        }

        $countryCodes = isset($campaign["geoRules"]["countryCodes"]) && is_array($campaign["geoRules"]["countryCodes"]) ? array_map("strtoupper", array_map("strval", $campaign["geoRules"]["countryCodes"])) : [];
        if (count($countryCodes) > 0) {
            $resolvedCountry = isset($geo["countryCode"]) ? strtoupper((string) $geo["countryCode"]) : "";
            if (!$resolvedCountry || !in_array($resolvedCountry, $countryCodes, true)) {
                return false;
            }
        }

        $regionCodes = isset($campaign["geoRules"]["regionCodes"]) && is_array($campaign["geoRules"]["regionCodes"]) ? array_map("strtoupper", array_map("strval", $campaign["geoRules"]["regionCodes"])) : [];
        if (count($regionCodes) > 0) {
            $resolvedRegion = isset($geo["regionCode"]) ? strtoupper((string) $geo["regionCode"]) : "";
            if (!$resolvedRegion || !in_array($resolvedRegion, $regionCodes, true)) {
                return false;
            }
        }

        $geographicalSegmentIds = isset($campaign["geoRules"]["geographicalSegmentIds"]) && is_array($campaign["geoRules"]["geographicalSegmentIds"]) ? array_map("strval", $campaign["geoRules"]["geographicalSegmentIds"]) : [];
        if (count($geographicalSegmentIds) > 0) {
            $resolvedSegments = isset($geo["geographicalSegmentIds"]) && is_array($geo["geographicalSegmentIds"]) ? array_map("strval", $geo["geographicalSegmentIds"]) : [];
            if (count(array_intersect($geographicalSegmentIds, $resolvedSegments)) === 0) {
                return false;
            }
        }

        return true;
    }

    private function filterCampaignsByResolvedAudience($configuration, $websiteId)
    {
        if (!is_array($configuration) || !isset($configuration["version"]) || intval($configuration["version"]) !== 2 || !isset($configuration["campaigns"]) || !is_array($configuration["campaigns"])) {
            return $configuration;
        }

        $activeAdvancedCampaigns = [];
        foreach ($configuration["campaigns"] as $campaign) {
            if (
                isset($campaign["status"])
                && $campaign["status"] === "active"
                && ($this->campaignRequiresAdvancedAudience($campaign) || $this->campaignRequiresServerSideGeoValidation($campaign))
            ) {
                $activeAdvancedCampaigns[] = $campaign;
            }
        }

        if (empty($activeAdvancedCampaigns)) {
            return $configuration;
        }

        $requestedNewsletterState = "any";
        $requestedCustomerGroupIds = [];
        $requestedDynamicSegmentIds = [];
        $requestedGeographicalSegmentIds = [];
        foreach ($activeAdvancedCampaigns as $campaign) {
            if ($requestedNewsletterState === "any" && isset($campaign["audienceRules"]["newsletterState"]) && $campaign["audienceRules"]["newsletterState"] !== "any") {
                $requestedNewsletterState = $campaign["audienceRules"]["newsletterState"];
            }
            if (isset($campaign["audienceRules"]["customerGroupIds"]) && is_array($campaign["audienceRules"]["customerGroupIds"])) {
                $requestedCustomerGroupIds = array_merge($requestedCustomerGroupIds, $campaign["audienceRules"]["customerGroupIds"]);
            }
            if (isset($campaign["audienceRules"]["excludedCustomerGroupIds"]) && is_array($campaign["audienceRules"]["excludedCustomerGroupIds"])) {
                $requestedCustomerGroupIds = array_merge($requestedCustomerGroupIds, $campaign["audienceRules"]["excludedCustomerGroupIds"]);
            }
            if (isset($campaign["audienceRules"]["dynamicSegmentIds"]) && is_array($campaign["audienceRules"]["dynamicSegmentIds"])) {
                $requestedDynamicSegmentIds = array_merge($requestedDynamicSegmentIds, $campaign["audienceRules"]["dynamicSegmentIds"]);
            }
            if (isset($campaign["audienceRules"]["excludedDynamicSegmentIds"]) && is_array($campaign["audienceRules"]["excludedDynamicSegmentIds"])) {
                $requestedDynamicSegmentIds = array_merge($requestedDynamicSegmentIds, $campaign["audienceRules"]["excludedDynamicSegmentIds"]);
            }
            if ($this->campaignRequiresServerSideGeoValidation($campaign) && isset($campaign["geoRules"]["geographicalSegmentIds"]) && is_array($campaign["geoRules"]["geographicalSegmentIds"])) {
                $requestedGeographicalSegmentIds = array_merge($requestedGeographicalSegmentIds, $campaign["geoRules"]["geographicalSegmentIds"]);
            }
        }

        $customerId = null;
        $customerEmail = null;
        try {
            $customerId = $this->currentCustomer->getCustomerId();
            if ($customerId) {
                $customer = $this->currentCustomer->getCustomer();
                $customerEmail = $customer->getEmail();
            }
        } catch (\Exception $e) {
            $customerId = null;
            $customerEmail = null;
        }
        $fallbackCountryCode = $this->getPopupGeoFallbackCountryCode();
        $fallbackRegionCode = $this->getPopupGeoFallbackRegionCode();

        $audience = null;
        if ($this->shouldResolvePopupAudience($customerId, $customerEmail)) {
            $audience = $this->kilibaCaller->resolvePopupAudience(
                $customerId ? (string) $customerId : "",
                $customerEmail ? (string) $customerEmail : "",
                $requestedNewsletterState,
                array_values(array_unique(array_map("strval", $requestedCustomerGroupIds))),
                array_values(array_unique(array_map("strval", $requestedDynamicSegmentIds))),
                $websiteId
            );
        }
        $geo = null;
        if ($this->shouldResolvePopupGeo($customerId, $customerEmail) || $fallbackCountryCode || $fallbackRegionCode) {
            $geo = $this->kilibaCaller->resolvePopupGeo(
                $customerId ? (string) $customerId : "",
                $customerEmail ? (string) $customerEmail : "",
                array_values(array_unique(array_map("strval", $requestedGeographicalSegmentIds))),
                $websiteId,
                $fallbackCountryCode,
                $fallbackRegionCode
            );
        }

        $filteredCampaigns = [];
        $hasVisibleActiveCampaign = false;
        foreach ($configuration["campaigns"] as $campaign) {
            if (isset($campaign["status"]) && $campaign["status"] === "active") {
                if ($this->campaignMatchesResolvedAudience($campaign, $audience) && $this->campaignMatchesResolvedGeo($campaign, $geo)) {
                    $filteredCampaigns[] = $campaign;
                    $hasVisibleActiveCampaign = true;
                }
                continue;
            }

            $filteredCampaigns[] = $campaign;
        }

        if (!$hasVisibleActiveCampaign) {
            return false;
        }

        $configuration["campaigns"] = $filteredCampaigns;
        $configuration["_kilibaRuntimeContext"] = [
            "customerId" => $customerId ? (string) $customerId : null,
            "customerIdentity" => [
                "identified" => (bool) $customerId,
                "hasAccount" => (bool) $customerId,
                "hasOrders" => $this->customerHelper->hasCustomerOrdered($customerId),
                "isExistingCustomer" => (bool) $customerId,
            ],
            "newsletterStatus" => is_array($audience) && isset($audience["newsletterStatus"]) ? $audience["newsletterStatus"] : "unknown",
            "customerGroups" => is_array($audience) && isset($audience["customerGroupIds"]) && is_array($audience["customerGroupIds"]) ? array_values($audience["customerGroupIds"]) : [],
            "dynamicSegments" => is_array($audience) && isset($audience["dynamicSegmentIds"]) && is_array($audience["dynamicSegmentIds"]) ? array_values($audience["dynamicSegmentIds"]) : [],
            "geoHints" => [
                "countryCode" => is_array($geo) && isset($geo["countryCode"]) ? $geo["countryCode"] : null,
                "regionCode" => is_array($geo) && isset($geo["regionCode"]) ? $geo["regionCode"] : null,
                "geographicalSegmentIds" => is_array($geo) && isset($geo["geographicalSegmentIds"]) && is_array($geo["geographicalSegmentIds"]) ? array_values($geo["geographicalSegmentIds"]) : [],
            ],
        ];
        return $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function updatePopupConfiguration($popupType)
    {
        $requestCheck = $this->_checkRequest();
        if(!$requestCheck["success"]) {
            return array($requestCheck);
        }

        $websiteId = $requestCheck["websiteId"];
        $popupConfig = $this->extractPopupConfigurationPayload();

        if($popupType === "promoCodeFirstPurchase") {
            $configKey = ConfigHelper::XML_PATH_POPUP_PROMOCODEFIRSTPURCHASE_CONFIGURATION;
        } else {
            return array(
                array(
                    "success" => false,
                    "message" => "Invalid popup type"
                )
            );
        }

        $decodedPopupConfig = json_decode($popupConfig, true);
        if($decodedPopupConfig === null) {
            return array(
                array(
                    "success" => false,
                    "message" => "Invalid popup configuration JSON"
                )
            );
        }

        $this->_configHelper->saveDataToCoreConfig(
            $configKey,
            $popupConfig,
            $websiteId
        );

        return array(
            array( "success" => true )
        );
    }

    /**
     * Keep backward compatibility with the historical query-string config while
     * accepting large popup payloads in the JSON request body.
     *
     * @return string|null
     */
    protected function extractPopupConfigurationPayload()
    {
        $popupConfig = $this->_request->getParam('config', null);
        if (is_string($popupConfig) && $popupConfig !== '') {
            return $popupConfig;
        }

        $rawRequestBody = trim((string) file_get_contents('php://input'));
        if ($rawRequestBody === '') {
            return null;
        }

        $decodedRequestBody = json_decode($rawRequestBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (is_array($decodedRequestBody) && array_key_exists('config', $decodedRequestBody)) {
            return is_string($decodedRequestBody['config'])
                ? $decodedRequestBody['config']
                : $this->serializer->serialize($decodedRequestBody['config']);
        }

        return $this->serializer->serialize($decodedRequestBody);
    }

    /**
     * {@inheritdoc}
     */
    public function updatePopupActivation($popupType)
    {
        $requestCheck = $this->_checkRequest();
        if(!$requestCheck["success"]) {
            return array($requestCheck);
        }

        $websiteId = $requestCheck["websiteId"];
        $popupActivation = $this->_request->getParam('activation', null);

        if($popupType === "promoCodeFirstPurchase") {
            $configKey = ConfigHelper::XML_PATH_POPUP_PROMOCODEFIRSTPURCHASE_ACTIVATION;
        } else {
            return array(
                array(
                    "success" => false,
                    "message" => "Invalid popup type"
                )
            );
        }

        $decodedPopupActivation = intval($popupActivation);
        if($popupActivation && $decodedPopupActivation <= 0) {
            return array(
                array(
                    "success" => false,
                    "message" => "Invalid popup activation value"
                )
            );
        }

        $this->_configHelper->saveDataToCoreConfig(
            $configKey,
            $popupActivation ? strval($decodedPopupActivation) : "",
            $websiteId
        );

        return array(
            array( "success" => true )
        );
    }
}
