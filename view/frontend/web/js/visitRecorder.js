/*
 * Copyright © Kiliba. All rights reserved.
 */

define(
    [
        'jquery',
        'mage/translate',
        'Magento_Customer/js/customer-data',
        'mage/url',
        'domReady!'
    ],
    function (
        $, $t, customerData, url
    ) {

        $.widget('kiliba.visitRecorder', {

            options: {
                url : "",
                productId : 0,
                categoryId : 0,
                storeId: 0,
                formKey : "",
            },

            _create: function () {
                var self = this;
                this._super();
            },

            /**
             * @private
             */
            _init: function () {
                this._super();

                this._checkIfCustomerLoggedIn()
            },

            _checkIfCustomerLoggedIn : function() {
                var self = this;
                var alreadySend = false;

                customerData.get('customer').subscribe(function (customer) {
                    if((customer.id || customer.firstname) && !alreadySend) {
                        alreadySend = true;
                        self._callController();
                    }
                });
            },

            _callController: function() {
                $.ajax({
                    url: url.build('kilibam2/record/visit'),
                    type: 'POST',
                    data: {
                        url: this.options.url,
                        productId: this.options.productId,
                        categoryId: this.options.categoryId,
                        storeId : this.options.storeId,
                        form_key: this.options.formKey
                    },
                    success: function (response) {
                    }
                });
            },

        });

    return $.kiliba.visitRecorder;
});
