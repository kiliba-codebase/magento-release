<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper\Webhook;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class OrderFormatter
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var FormatterHelper
     */
    protected $formatterHelper;

    /**
     * @var KilibaLogger
     */
    protected $kilibaLogger;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        ConfigHelper $configHelper,
        FormatterHelper $formatterHelper,
        KilibaLogger $kilibaLogger,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->configHelper = $configHelper;
        $this->formatterHelper = $formatterHelper;
        $this->kilibaLogger = $kilibaLogger;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Format order data for webhook
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|null
     */
    public function format($order)
    {
        try {
            $storeId = $order->getStoreId();
            $websiteId = $this->formatterHelper->getWebsiteIdFromStore($storeId);
            
            // Format customer data
            $customer = $this->formatCustomer($order);

            // Format products
            $products = [];
            $amount = 0;
            $amountWithTax = 0;
            
            foreach ($order->getAllVisibleItems() as $item) {
                $productData = $this->formatProduct($item, $storeId);
                if ($productData) {
                    $products[] = $productData;
                    $amount += $productData['amount'];
                    $amountWithTax += $productData['amountWithTax'];
                }
            }

            // Get promo codes
            $promoCodes = [];
            $couponCode = $order->getCouponCode();
            if (!empty($couponCode)) {
                $promoCodes[] = $couponCode;
            }

            // Format billing address
            $billingAddress = $this->formatAddress($order->getBillingAddress());

            // Use actual amounts from order (including shipping, discounts, etc.)
            $actualAmount = (float)$order->getBaseSubtotal();
            $actualAmountWithTax = (float)$order->getBaseGrandTotal();

            // Format dates (PHP 5.2+ compatible ISO 8601 format)
            $createdAt = gmdate('Y-m-d\TH:i:s\Z', strtotime($order->getCreatedAt()));
            $updatedAt = gmdate('Y-m-d\TH:i:s\Z', strtotime($order->getUpdatedAt()));

            $data = [
                'id' => (string)$order->getId(),
                'reference' => $order->getIncrementId(),
                'status' => $order->getStatus(),
                'cartId' => $order->getQuoteId() ? (string)$order->getQuoteId() : null,
                'shopId' => (string)$storeId,
                'amount' => $actualAmount,
                'amountWithTax' => $actualAmountWithTax,
                'products' => $products,
                'promoCodes' => $promoCodes,
                'createdAt' => $createdAt,
                'updatedAt' => $updatedAt
            ];

            // Add customer if available
            if ($customer) {
                $data['customer'] = $customer;
            }

            // Add billing address if available
            if ($billingAddress) {
                $data['billingAddress'] = $billingAddress;
            }

            return $data;
        } catch (\Exception $e) {
            $this->kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Failed to format order for webhook",
                "Order ID: " . $order->getId() . ", Error: " . $e->getMessage(),
                $order->getStoreId()
            );
            return null;
        }
    }

    /**
     * Format customer data
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|null
     */
    protected function formatCustomer($order)
    {
        $email = $order->getCustomerEmail();
        
        if (empty($email)) {
            return null;
        }

        $customer = [
            'email' => $email
        ];

        // Add customer ID if not guest
        if ($order->getCustomerId()) {
            $customer['id'] = (string)$order->getCustomerId();
        } else {
            $customer['id'] = null;
        }

        // Add names
        $customer['firstName'] = $order->getCustomerFirstname() ?: null;
        $customer['lastName'] = $order->getCustomerLastname() ?: null;

        // Add customer group
        if ($order->getCustomerGroupId()) {
            $customer['customerGroupIds'] = [(string)$order->getCustomerGroupId()];
            $customer['defaultCustomerGroupId'] = (string)$order->getCustomerGroupId();
        }

        return $customer;
    }

    /**
     * Format product data
     *
     * @param \Magento\Sales\Model\Order\Item $item
     * @param int $storeId
     * @return array|null
     */
    protected function formatProduct($item, $storeId)
    {
        try {
            $productId = (string)$item->getProductId();
            $variantId = null;
            
            // Try to get variant ID for configurable products
            $productOptions = $item->getProductOptions();
            if (isset($productOptions['simple_sku'])) {
                try {
                    $simpleProduct = $this->productRepository->get($productOptions['simple_sku']);
                    $variantId = (string)$simpleProduct->getId();
                } catch (\Exception $e) {
                    // Variant ID will remain null
                }
            }

            // Calculate amounts (excluding tax for amount, including tax for amountWithTax)
            $amount = (float)$item->getBaseRowTotal();
            $amountWithTax = (float)$item->getBaseRowTotalInclTax();

            return [
                'id' => $productId,
                'variantId' => $variantId,
                'name' => $item->getName(),
                'quantity' => (float)$item->getQtyOrdered(),
                'amount' => $amount,
                'amountWithTax' => $amountWithTax
            ];
        } catch (\Exception $e) {
            $this->kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Failed to format order product",
                "Product ID: " . $item->getProductId() . ", Error: " . $e->getMessage(),
                $storeId
            );
            return null;
        }
    }

    /**
     * Format address data
     *
     * @param \Magento\Sales\Model\Order\Address $address
     * @return array|null
     */
    protected function formatAddress($address)
    {
        if (!$address) {
            return null;
        }

        $street = $address->getStreet();
        $addressLine = is_array($street) ? implode(', ', $street) : $street;

        return [
            'address' => $addressLine ?: null,
            'city' => $address->getCity() ?: null,
            'countryCode' => $address->getCountryId() ?: null,
            'country' => $address->getCountry() ?: null,
            'phone' => $address->getTelephone() ?: null,
            'zipcode' => $address->getPostcode() ?: null,
            'region' => $address->getRegion() ?: null
        ];
    }
}
