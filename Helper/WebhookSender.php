<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\SerializerInterface;

class WebhookSender extends AbstractHelper
{
    const WEBHOOK_BASE_URL = 'https://production.event-gateway.kiliba.ai/webhook';
    const WEBHOOK_CART_UPDATE = 'cart_update';
    const WEBHOOK_ORDER_UPDATE = 'order_update';
    
    const TIMEOUT = 5000;
    const CONNECT_TIMEOUT = 1000;

    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var KilibaLogger
     */
    protected $kilibaLogger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    public function __construct(
        Context $context,
        CurlFactory $curlFactory,
        SerializerInterface $serializer,
        KilibaLogger $kilibaLogger,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context);
        $this->curlFactory = $curlFactory;
        $this->serializer = $serializer;
        $this->kilibaLogger = $kilibaLogger;
        $this->configHelper = $configHelper;
    }

    /**
     * Send webhook data
     *
     * @param string $webhookName
     * @param array $data
     * @param int $websiteId
     * @return bool
     */
    public function send($webhookName, array $data, $websiteId)
    {
        try {
            // Check if module is configured
            $accountId = $this->configHelper->getClientId($websiteId);
            if (empty($accountId)) {
                $this->kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_WARNING,
                    "Webhook {$webhookName} not sent - no account ID configured",
                    '',
                    $websiteId
                );
                return false;
            }

            // Get flux token for authentication
            $fluxToken = $this->configHelper->getConfigWithoutCache(ConfigHelper::XML_PATH_FLUX_TOKEN, $websiteId);
            if (empty($fluxToken)) {
                $this->kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_WARNING,
                    "Webhook {$webhookName} not sent - no flux token configured",
                    '',
                    $websiteId
                );
                return false;
            }

            // Create authentication token: base64(client_id:flux_token)
            $authToken = base64_encode($accountId . ':' . $fluxToken);

            $url = self::WEBHOOK_BASE_URL . '/' . $webhookName;
            $jsonData = $this->serializer->serialize($data);

            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $curl->setOption(CURLOPT_TIMEOUT_MS, self::TIMEOUT);
            $curl->setOption(CURLOPT_CONNECTTIMEOUT_MS, self::CONNECT_TIMEOUT);
            $curl->addHeader('Content-Type', 'application/json');
            $curl->addHeader('Accept', 'application/json');
            $curl->addHeader('X-Kiliba-Token', $authToken);
            
            $curl->post($url, $jsonData);
            
            $statusCode = $curl->getStatus();
            $responseBody = $curl->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                return true;
            } else {
                $this->kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_ERROR,
                    "Webhook {$webhookName} failed",
                    $this->serializer->serialize([
                        'status_code' => $statusCode,
                        'response' => $responseBody,
                        'data_sent' => $data
                    ]),
                    $websiteId
                );
                return false;
            }
        } catch (\Exception $e) {
            $this->kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Webhook {$webhookName} exception",
                $this->serializer->serialize([
                    'error' => $e->getMessage(),
                    'data' => $data
                ]),
                $websiteId
            );
            return false;
        }
    }

    /**
     * Send cart update webhook
     *
     * @param array $cartData
     * @param int $websiteId
     * @return bool
     */
    public function sendCartUpdate(array $cartData, $websiteId)
    {
        return $this->send(self::WEBHOOK_CART_UPDATE, $cartData, $websiteId);
    }

    /**
     * Send order update webhook
     *
     * @param array $orderData
     * @param int $websiteId
     * @return bool
     */
    public function sendOrderUpdate(array $orderData, $websiteId)
    {
        return $this->send(self::WEBHOOK_ORDER_UPDATE, $orderData, $websiteId);
    }
}
