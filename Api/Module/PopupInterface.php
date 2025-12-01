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
     * Register a customer subscription to the popup
     * @param string $popupType Popup type (e.g., "promoCodeFirstPurchase")
     * @return bool
     */
    public function registerSubscription($popupType);

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
