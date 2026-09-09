<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Api\Module;

interface PopupInterface
{
    /**
     * Get popup configuration by type
     * @param string $popupType Popup type (e.g., "promoCodeFirstPurchase")
     * @return string[]
     */
    public function getPopupConfiguration($popupType);

    /**
     * Resolve a popup preview token to a transient configuration payload.
     * @param string $popupType Popup type (e.g., "promoCodeFirstPurchase")
     * @return string[]
     */
    public function getPopupPreview($popupType);

    /**
     * Register a customer subscription to the popup.
     * All sensitive fields are read from the POST body, never from URL parameters.
     * @param string $popupType Popup type (e.g., "promoCodeFirstPurchase")
     * @param string|null $email Customer email address
     * @param bool $subscribe Whether the customer subscribed to the newsletter
     * @param string|null $phone Customer phone number
     * @param string|null $birthday Customer birthday (YYYY-MM-DD, DD/MM/YYYY or MM/DD/YYYY)
     * @param bool $optin_sms Whether the customer opted in to SMS
     * @param string|null $captcha reCAPTCHA token
     * @param string|null $campaignId Campaign ID
     * @param string|null $quizAnswers JSON-encoded array of quiz answers
     * @return bool
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
    );

    /**
     * Forward a popup display hit to Kiliba without local persistence.
     * @param string $popupType Popup type (e.g., "promoCodeFirstPurchase")
     * @param string|null $displayData JSON-encoded display data
     * @return bool
     */
    public function registerDisplay($popupType, $displayData = null);

    /**
     * Update popup configuration (via private API)
     * @param string $popupType Popup type (e.g., "promoCodeFirstPurchase")
     * @return string[]
     */
    public function updatePopupConfiguration($popupType);

    /**
     * Update popup configuration (via private API)
     * @param string $popupType Popup type (e.g., "promoCodeFirstPurchase")
     * @return string[]
     */
    public function updatePopupActivation($popupType);
}
