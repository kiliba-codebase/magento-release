<?php
/**
 * Copyright © Kiliba. All rights reserved.
 */
namespace Kiliba\Connector\Observer\Quote;

use Kiliba\Connector\Helper\KilibaLogger;
use Kiliba\Connector\Helper\Webhook\CartFormatter;
use Kiliba\Connector\Helper\WebhookSender;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\ConfigHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SendCartWebhookObserver implements ObserverInterface
{
    /**
     * @var CartFormatter
     */
    protected $cartFormatter;

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
        CartFormatter $cartFormatter,
        WebhookSender $webhookSender,
        KilibaLogger $kilibaLogger,
        FormatterHelper $formatterHelper,
        ConfigHelper $configHelper
    ) {
        $this->cartFormatter = $cartFormatter;
        $this->webhookSender = $webhookSender;
        $this->kilibaLogger = $kilibaLogger;
        $this->formatterHelper = $formatterHelper;
        $this->configHelper = $configHelper;
    }

    /**
     * Send cart update webhook when quote is saved
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $observer->getEvent()->getQuote();
            
            // Skip if quote is not valid
            if (!$quote || !$quote->getId()) {
                return;
            }

            // Get website ID
            $storeId = $quote->getStoreId();
            $websiteId = $this->formatterHelper->getWebsiteIdFromStore($storeId);

            // Check if cart webhook is enabled
            if (!$this->configHelper->isCartWebhookEnabled($websiteId)) {
                return;
            }

            // Format cart data
            $cartData = $this->cartFormatter->format($quote);
            
            if (empty($cartData)) {
                return;
            }

            // Send webhook
            $this->webhookSender->sendCartUpdate($cartData, $websiteId);

        } catch (\Exception $e) {
            // Use websiteId if available, fallback to 1
            $websiteId = 1;
            try {
                if (isset($quote) && $quote && $quote->getStoreId()) {
                    $websiteId = $this->formatterHelper->getWebsiteIdFromStore($quote->getStoreId());
                }
            } catch (\Exception $ex) {
                // Keep default websiteId
            }
            
            $this->kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "SendCartWebhookObserver::execute failed",
                $e->getMessage(),
                $websiteId
            );
        }
    }
}
