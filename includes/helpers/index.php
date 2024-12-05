<?php

function growtype_analytics_get_user_email()
{
    $email = wp_get_current_user()->user_email;

    if (class_exists('Growtype_Quiz') && class_exists('Growtype_Form')) {
        $growtype_quiz_unique_hash = growtype_quiz_get_unique_hash();
        $submission = growtype_form_get_latest_submission_by_growtype_quiz_unique_hash($growtype_quiz_unique_hash);
        $submission_email = isset($submission['data']['email']) ? $submission['data']['email'] : null;

        if (!empty($submission_email)) {
            $email = $submission_email;
        }
    }

    return apply_filters('growtype_analytics_get_user_email', $email);
}

function growtype_analytics_get_client_ip()
{
    $ip = $_SERVER['REMOTE_ADDR'];

    // If you are behind a proxy or load balancer, use additional checks
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim(end($forwardedIps));
    }

    return $ip;
}

function growtype_analytics_get_client_user_agent()
{
    return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
}

function growtype_analytics_get_current_url()
{
    return (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}
