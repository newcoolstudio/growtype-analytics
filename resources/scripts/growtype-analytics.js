import "./actions/growtypeAnalyticsCapture";

jQuery(document).ready(function ($) {
    // blade attribute -> data-growtype-analytics-tag='@json(["event" => "favourite_assistant", "value" => $post->post_name])'
    $('a,div,span,button').click(function () {
        let value = $(this).attr('data-growtype-analytics-tag');

        if (value && value.length > 0) {
            value = value.replace(/'/g, '"');
            value = JSON.parse(value);

            growtypeAnalyticsCapture('growtype_analytics_tag_click', {
                value: value
            });
        }
    });

    /**
     * Growtype quiz
     */
    document.addEventListener('growtypeQuizShowQuestion', function (params) {
        if (params.detail.answer_details && params.detail.answer_details.answer) {
            let answerDetails = params.detail.answer_details;
            growtypeAnalyticsCapture('growtype_analytics_growtype_quiz_question_answer', answerDetails);
        }
    });

    /**
     * Growtype quiz finished
     */
    document.addEventListener('growtypeQuizSaveQuizData', function (event) {
        if (event.detail?.answers) {
            let answerDetails = {...event.detail.answers};
            answerDetails.event = "quiz_is_finished";

            growtypeAnalyticsCapture('growtype_analytics_growtype_quiz_finished', answerDetails);
        }
    });

    /**
     * Growtype wc
     */
    document.addEventListener('growtypeWcPaymentFormLoaded', function (params) {
        if (params.detail) {
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

            growtypeAnalyticsCapture('growtype_analytics_growtype_wc_payment_form_loaded', answerDetails);
        }
    });

    jQuery('body').on('adding_to_cart', function () {
        let answerDetails = {
            event: "adding_to_cart",
            value: window.growtype_wc_ajax.cart_total,
            currency: window.growtype_wc_ajax.currency,
            items: window.growtype_wc_ajax.items_gtm,
            user_id: window.growtype_wc_ajax.user_id,
            email: window.growtype_wc_ajax.email,
        };

        growtypeAnalyticsCapture('growtype_analytics_growtype_wc_adding_to_cart', answerDetails);
    });

    jQuery('form.checkout').on('submit', function (event) {
        let answerDetails = {
            event: "payment_attempt",
            payment_method: $('input[name="payment_method"]:checked').val()
        };

        growtypeAnalyticsCapture('growtype_analytics_growtype_wc_checkout_form_submit', answerDetails);
    });
});
