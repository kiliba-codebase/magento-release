<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Helper\Webhook;

use Kiliba\Connector\Helper\ConfigHelper;
use Kiliba\Connector\Helper\CookieHelper;
use Kiliba\Connector\Helper\FormatterHelper;
use Kiliba\Connector\Helper\KilibaLogger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class CartFormatter
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var CookieHelper
     */
    protected $cookieHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

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

    /**
     * @var array
     */
    protected $mediaUrlCache = [];

    public function __construct(
        ConfigHelper $configHelper,
        CookieHelper $cookieHelper,
        CustomerRepositoryInterface $customerRepository,
        FormatterHelper $formatterHelper,
        KilibaLogger $kilibaLogger,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager
    ) {
        $this->configHelper = $configHelper;
        $this->cookieHelper = $cookieHelper;
        $this->customerRepository = $customerRepository;
        $this->formatterHelper = $formatterHelper;
        $this->kilibaLogger = $kilibaLogger;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Format quote data for webhook
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array|null
     */
    public function format($quote)
    {
        try {
            $storeId = $quote->getStoreId();
            $websiteId = $this->formatterHelper->getWebsiteIdFromStore($storeId);
            
            // Format customer data
            $customer = $this->formatCustomer($quote);
            
            // Skip if no customer email (still required)
            if (empty($customer) || empty($customer['email'])) {
                return null;
            }

            // Format products (can be empty array)
            $products = [];
            $amount = 0;
            $amountWithTax = 0;
            
            foreach ($quote->getAllVisibleItems() as $item) {
                $productData = $this->formatProduct($item, $storeId);
                if ($productData) {
                    $products[] = $productData;
                    $amount += $productData['amount'];
                    $amountWithTax += $productData['amountWithTax'];
                }
            }

            // Get promo codes
            $promoCodes = [];
            $couponCode = $quote->getCouponCode();
            if (!empty($couponCode)) {
                $promoCodes[] = $couponCode;
            }

            // Format billing address
            $billingAddress = $this->formatAddress($quote->getBillingAddress());

            // Get language ISO code
            $locale = $this->configHelper->getStoreLocale($storeId);
            $langIsoCode = $this->extractLangFromLocale($locale);

            // Format dates (PHP 5.2+ compatible ISO 8601 format)
            $createdAt = gmdate('Y-m-d\TH:i:s\Z', strtotime($quote->getCreatedAt()));
            $updatedAt = gmdate('Y-m-d\TH:i:s\Z'); // $quote->getUpdatedAt() is null

            $data = [
                'id' => (string)$quote->getId(),
                'customer' => $customer,
                'shopId' => (string)$storeId,
                'langIsoCode' => $langIsoCode,
                'amount' => (float)$amount,
                'amountWithTax' => (float)$amountWithTax,
                'products' => $products,
                'promoCodes' => $promoCodes,
                'createdAt' => $createdAt,
                'updatedAt' => $updatedAt
            ];

            // Add billing address if available
            if ($billingAddress) {
                $data['billingAddress'] = $billingAddress;
            }

            return $data;
        } catch (\Exception $e) {
            $this->kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Failed to format cart for webhook",
                "Cart ID: " . $quote->getId() . ", Error: " . $e->getMessage(),
                $quote->getStoreId()
            );
            return null;
        }
    }

    /**
     * Format customer data
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array|null
     */
    protected function formatCustomer($quote)
    {
        $customer = [];
        $loadedFromKilibaKey = false;
        
        // Get customer email
        $email = $quote->getCustomerEmail();
        if (empty($email) && $quote->getBillingAddress()) {
            $email = $quote->getBillingAddress()->getEmail();
        }
        
        // For guest customers, try to get email from kilibaCustomerKey
        if (empty($email) && !$quote->getCustomerId()) {
            $kilibaCustomerKey = $quote->getKilibaCustomerKey();
            if (!empty($kilibaCustomerKey)) {
                $email = $this->cookieHelper->getCustomerEmailViaKilibaCustomerKey($kilibaCustomerKey);
                $loadedFromKilibaKey = true;
            }
        }
        
        if (empty($email)) {
            return null;
        }

        $customer['email'] = $email;

        // Add customer ID if logged in
        if ($quote->getCustomerId()) {
            $customer['id'] = (string)$quote->getCustomerId();
        } else {
            $customer['id'] = null;
        }

        // Add names
        $customer['firstName'] = $quote->getCustomerFirstname() ?: null;
        $customer['lastName'] = $quote->getCustomerLastname() ?: null;
        
        // If email was loaded from kilibaCustomerKey, try to load customer data by email
        if ($loadedFromKilibaKey && !empty($email)) {
            try {
                $customerData = $this->customerRepository->get($email);
                if ($customerData && $customerData->getId()) {
                    $customer['id'] = (string)$customerData->getId();
                    $customer['firstName'] = $customerData->getFirstname() ?: null;
                    $customer['lastName'] = $customerData->getLastname() ?: null;
                    
                    // Update customer group if available
                    if ($customerData->getGroupId()) {
                        $customer['customerGroupIds'] = [(string)$customerData->getGroupId()];
                        $customer['defaultCustomerGroupId'] = (string)$customerData->getGroupId();
                    }
                }
            } catch (NoSuchEntityException $e) {
                // Customer doesn't exist in database, keep as guest with email only
            } catch (\Exception $e) {
                // Log error but continue with guest data
                $this->kilibaLogger->addLog(
                    KilibaLogger::LOG_TYPE_ERROR,
                    "Failed to load customer by email from kilibaCustomerKey",
                    "Email: " . $email . ", Error: " . $e->getMessage(),
                    $quote->getStoreId()
                );
            }
        }

        // Add customer group
        if ($quote->getCustomerGroupId()) {
            $customer['customerGroupIds'] = [(string)$quote->getCustomerGroupId()];
            $customer['defaultCustomerGroupId'] = (string)$quote->getCustomerGroupId();
        }

        return $customer;
    }

    /**
     * Format product data
     *
     * @param \Magento\Quote\Model\Quote\Item $item
     * @param int $storeId
     * @return array|null
     */
    protected function formatProduct($item, $storeId)
    {
        try {
            $product = $item->getProduct();
            
            // Get product ID and variant ID
            $productId = (string)$product->getId();
            $variantId = null;
            
            // For display purposes (name, price, image), use child product if available
            $displayProduct = $product;
            
            // Check if configurable product with simple child
            $configurableChild = $item->getOptionByCode('simple_product');
            if ($configurableChild) {
                $childProduct = $configurableChild->getProduct();
                $variantId = (string)$childProduct->getId();
                // Use child product for display data (name, image)
                $displayProduct = $childProduct;
            }

            // Calculate amounts
            $amount = (float)$item->getBaseRowTotal();
            $amountWithTax = (float)$item->getBaseRowTotalInclTax();

            // Get product URLs (use parent product URL for configurable products)
            $absoluteUrl = $product->getProductUrl();
            $relativeUrl = parse_url($absoluteUrl, PHP_URL_PATH);
            
            // Get product image (use child product image if available, fallback to parent)
            $imageUrl = $this->getProductImageUrl($displayProduct, $storeId);
            if (!$imageUrl && $displayProduct !== $product) {
                // If child has no image, fallback to parent image
                $imageUrl = $this->getProductImageUrl($product, $storeId);
            }

            return [
                'id' => $productId,
                'variantId' => $variantId,
                'name' => $displayProduct->getName(),
                'quantity' => (float)$item->getQty(),
                'amount' => $amount,
                'amountWithTax' => $amountWithTax,
                'relativeUrl' => $relativeUrl ?: '/',
                'absoluteUrl' => $absoluteUrl,
                'imageUrl' => $imageUrl
            ];
        } catch (\Exception $e) {
            $this->kilibaLogger->addLog(
                KilibaLogger::LOG_TYPE_ERROR,
                "Failed to format cart product",
                "Product ID: " . $item->getProductId() . ", Error: " . $e->getMessage(),
                $storeId
            );
            return null;
        }
    }

    /**
     * Get product image URL
     *
     * @param \Magento\Catalog\Model\Product $product
     * @param int $storeId
     * @return string|null
     */
    protected function getProductImageUrl($product, $storeId)
    {
        try {
            if (!isset($this->mediaUrlCache[$storeId])) {
                $store = $this->storeManager->getStore($storeId);
                $this->mediaUrlCache[$storeId] = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            }

            $image = $product->getThumbnail();
            if ($image && $image !== 'no_selection') {
                return $this->mediaUrlCache[$storeId] . 'catalog/product' . $image;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format address data
     *
     * @param \Magento\Quote\Model\Quote\Address $address
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

    /**
     * Extract language ISO code from locale
     *
     * @param string $locale
     * @return string
     */
    protected function extractLangFromLocale($locale)
    {
        if (empty($locale)) {
            return 'en';
        }
        
        $parts = explode('_', $locale);
        return strtolower($parts[0]);
    }
}
