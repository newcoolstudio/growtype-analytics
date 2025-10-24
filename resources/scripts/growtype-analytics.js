import "./actions/growtypeAnalyticsPushToDataLayer";

jQuery(document).ready(function ($) {
    // blade attribute -> data-growtype-analytics-gtm='@json(["event" => "favourite_assistant", "value" => $post->post_name])'
    $('a,div,span,button').click(function () {
        if (isGtmLoaded()) {
            let value = $(this).attr('data-growtype-analytics-gtm');

            if (value && value.length > 0) {
                value = value.replace(/'/g, '"');
                value = JSON.parse(value);

                window.dataLayer.push(value);
            }
        }
    });

    /**
     * Growtype quiz
     */
    document.addEventListener('growtypeQuizShowQuestion', function (params) {
        if (isGtmLoaded() && params.detail.answer_details && params.detail.answer_details.answer) {
            let answerDetails = params.detail.answer_details;
            answerDetails.event = "quiz_question_answered";

            window.dataLayer.push(answerDetails);
        }
    });

    /**
     * Growtype quiz finished
     */
    document.addEventListener('growtypeQuizSaveQuizData', function (event) {
        if (isGtmLoaded() && event.detail?.answers) {
            let answerDetails = {...event.detail.answers};
            answerDetails.event = "quiz_is_finished";

            if (window.dataLayer && Array.isArray(window.dataLayer)) {
                window.dataLayer.push(answerDetails);
            } else {
                console.warn("Growtype analytics - dataLayer is not available or not an array.");
            }
        } else {
            console.warn("Growtype analytics - GTM is not loaded or event detail is missing answers.");
        }
    });

    /**
     * Growtype wc
     */
    document.addEventListener('growtypeWcPaymentFormLoaded', function (params) {
        if (isGtmLoaded() && params.detail) {
            let answerDetails = {
                event: "add_payment_info",
                ecommerce: {
                    value: params.detail.value,
                    currency: params.detail.currency,
                    user_id: params.detail.user_id !== "0" ? params.detail.user_id : growtype_analytics_ajax.user_id,
                    email: params.detail.email,
                    items: params.detail.items
                }
            };

            window.dataLayer.push(answerDetails);
        }
    });

    jQuery('body').on('adding_to_cart', function () {
        if (isGtmLoaded()) {
            let answerDetails = {
                event: "adding_to_cart",
                value: window.growtype_wc_ajax.cart_total,
                currency: window.growtype_wc_ajax.currency,
                items: window.growtype_wc_ajax.items_gtm,
                user_id: window.growtype_wc_ajax.user_id,
                email: window.growtype_wc_ajax.email,
            };

            window.dataLayer.push(answerDetails);
        }
    });

    jQuery('form.checkout').on('submit', function (event) {
        if (isGtmLoaded()) {
            let answerDetails = {
                event: "payment_attempt",
                payment_method: $('input[name="payment_method"]:checked').val()
            };

            window.dataLayer.push(answerDetails);
        }
    });
});

function isGtmLoaded() {
    return window.dataLayer ? true : false;
}
