/*
 * Copyright © Kiliba. All rights reserved.
 */


define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/cookies'
], function ($, modal) {
    'use strict';

    return {
        modalWindow: null,

        /**
         * Create popUp window for provided element
         *
         * @param {HTMLElement} element
         */
        createPopUp: function (element) {
            var options = {
                'type': 'popup',
                'modalClass': 'popup-authentication',
                'focus': '[name=username]',
                'responsive': true,
                'innerScroll': true,
                'trigger': '.proceed-to-checkout',
                'buttons': []
            };

            this.modalWindow = element;
            modal(options, $(this.modalWindow));
        },

        /** Show login popup window */
        showModal: function () {
            // Kiliba Custom dev BEGIN
            var recentlyViewedProducts = localStorage.getItem("recently_viewed_product");
            var date = new Date();
            date.setTime(date.getTime()+(10*60*1000)); // cookie lifetime 10 minutes
            $.mage.cookies.set("kiliba_recent", recentlyViewedProducts, {
                expires: date,
                path: "/"
            });
            // END
            $(this.modalWindow).modal('openModal').trigger('contentUpdated');
        }
    };
});
