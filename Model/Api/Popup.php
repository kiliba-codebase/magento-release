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

                // Hide captcha secret key from frontend
                if ($popupConfig && isset($popupConfig["captcha"]) && isset($popupConfig["captcha"]["secretkey"])) {
                    unset($popupConfig["captcha"]["secretkey"]);
                }

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
    public function registerSubscription($popupType)
    {
        try {
            // Get email from request
            $email = $this->_request->getParam('email');
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
            $hasSubscribed = $this->_request->getParam('subscribe') === 'true';
            $phone = trim((string)$this->_request->getParam('phone'));
            $optinSms = $this->_request->getParam('optin_sms') === 'true';

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
            $isAllEligibility = isset($popupConfiguration['eligibilityMode'])
                && $popupConfiguration['eligibilityMode'] === 'all';

            // Check required subscription
            if (isset($popupConfiguration['subscriptionCheckboxRequired']) 
                && $popupConfiguration['subscriptionCheckboxRequired'] 
                && !$hasSubscribed
            ) {
                $this->logRegistrationFailure($email, "Subscription required");
                sleep(2);
                return $this->jsonResponse(400, "SUBSCRIPTION_REQUIRED");
            }

            // Check CAPTCHA
            if (isset($popupConfiguration['captcha']) 
                && isset($popupConfiguration['captcha']['type']) 
                && $popupConfiguration['captcha']['type'] === "reCAPTCHA v2"
            ) {
                if (!isset($popupConfiguration['captcha']['secretkey'])) {
                    $this->logRegistrationFailure($email, "Missing captcha secret key");
                    sleep(2);
                    return $this->jsonResponse(400, "INVALID_CAPTCHA_SETUP");
                }
                $token = $this->_request->getParam('captcha');
                $captchaVerification = $this->verifyCaptcha($popupConfiguration['captcha']['secretkey'], $token);
                if (!$captchaVerification || !$captchaVerification->success) {
                    $this->logRegistrationFailure($email, "Invalid captcha token");
                    sleep(2);
                    return $this->jsonResponse(400, "INVALID_CAPTCHA_TOKEN");
                }
            }

            // Check if customer is eligible to popup
            if(!$isAllEligibility) {
                if (isset($popupConfiguration['eligibilityMode']) 
                    && $popupConfiguration['eligibilityMode'] === 'first-purchase'
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
            if($this->popupCustomerResource->isEmailRegistered($email, $popupType)) {
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
            $popupCustomer->setEmail($email);
            $popupCustomer->setData('phone', $phone);
            $popupCustomer->setSubscribe($hasSubscribed);
            $popupCustomer->setData('optin_sms', $optinSms ? 1 : 0);
            $popupCustomer->setSubscribeIp($hasSubscribed ? $ip : '');
            $popupCustomer->setWebsiteId($websiteId);

            if($isAllEligibility && $this->popupCustomerResource->isEmailRegistered($email, $popupType)) {
                $existing = $this->popupCustomerResource->getRegistrationsByEmailAndType($email, $popupType);
                if(is_array($existing) && count($existing) > 0) {
                    $popupCustomer->setData('popup_customer_id', $existing[0]['popup_customer_id']);
                }
            }

            $this->popupCustomerResource->save($popupCustomer);

            // Ping Kiliba
            $this->kilibaCaller->registerPopupSubscription(
                $popupIdentifier,
                ['email' => $email, 'subscribe' => $hasSubscribed, 'phone' => $phone, 'optin_sms' => $optinSms],
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
        $popupConfig = $this->_request->getParam('config', null);

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
