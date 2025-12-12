<?php
/**
 * Copyright © Kiliba. All rights reserved.
 */
namespace Kiliba\Connector\Observer\Order;

use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Helper\Webhook\OrderFormatter;
use Kiliba\Connector\Helper\WebhookSender;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\ConfigHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SendOrderWebhookObserver implements ObserverInterface
{
    /**
     * @var OrderFormatter
     */
    protected $orderFormatter;

    /**
     * @var WebhookSender
     */
    protected $webhookSender;

    /**
     * @var KilibaLogger
     */
    protected $kilibaLogger;

    /**
     * @var FormatterHelper
     */
    protected $formatterHelper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    public function __construct(
        OrderFormatter $orderFormatter,
        WebhookSender $webhookSender,
        KilibaLogger $kilibaLogger,
        FormatterHelper $formatterHelper,
        ConfigHelper $configHelper
    ) {
        $this->orderFormatter = $orderFormatter;
        $this->webhookSender = $webhookSender;
        $this->kilibaLogger = $kilibaLogger;
        $this->formatterHelper = $formatterHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * Send order update webhook when order is saved
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getEvent()->getOrder();
            
            // Skip if order is not valid
            if (!$order || !$order->getId()) {
                return;
            }

            // Get website ID
            $storeId = $order->getStoreId();
            $websiteId = $this->formatterHelper->getWebsiteIdFromStore($storeId);

            // Check if order webhook is enabled
            if (!$this->configHelper->isOrderWebhookEnabled($websiteId)) {
                return;
            }

            // Format order data
            $orderData = $this->orderFormatter->format($order);
            
            if (empty($orderData)) {
                return;
            }

            // Send webhook
            $this->webhookSender->sendOrderUpdate($orderData, $websiteId);

        } catch (\Exception $e) {
            // Use websiteId if available, fallback to 1
            $websiteId = 1;
            try {
                if (isset($order) && $order && $order->getStoreId()) {
                    $websiteId = $this->formatterHelper->getWebsiteIdFromStore($order->getStoreId());
                }
            } catch (\Exception $ex) {
                // Keep default websiteId
            }
            
            $this->kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "SendOrderWebhookObserver::execute failed",
                $e->getMessage(),
                $websiteId
            );
        }
    }
}
