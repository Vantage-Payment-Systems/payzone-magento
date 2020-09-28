/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
		'mage/url'
    ],
    function (Component, url) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'VPS_Payzone/payment/payzone'
            },
            redirectAfterPlaceOrder: false,
            /**
             * After place order callback
             */
            afterPlaceOrder: function () {
                window.location.replace(url.build('payzone/checkout/redirect'));
            }
        });
    }
);
