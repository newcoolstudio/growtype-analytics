<?php

/**
 * Documentation site request handler
 *
 * Handles all access-control logic for /growtype-analytics/ requests:
 * token-based guest access, admin login gate, post-login redirect, and page dispatch.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/documentation/components
 */
class Growtype_Analytics_Doc_Request_Handler
{
    /** @var Growtype_Analytics_Doc_Renderer */
    private $renderer;

    public function __construct(Growtype_Analytics_Doc_Renderer $renderer)
    {
        $this->renderer = $renderer;

        add_action('template_redirect', [$this, 'handle_request']);

        // Ensure WordPress honours redirect_to for our URL even for admins
        // (WP defaults to sending admins to the dashboard after login).
        add_filter('login_redirect', [$this, 'preserve_redirect_after_login'], 10, 3);
    }

    public function handle_request(): void
    {
        if (!get_query_var(Growtype_Analytics_Frontend_Page::QUERY_VAR)) {
            return;
        }

        // ── Token-based guest access ────────────────────────────────────────
        // If a valid ?ga_token is present for a 'home' type link, bypass login.
        $ga_token = sanitize_text_field(wp_unslash($_GET[Growtype_Analytics_Frontend_Page::TOKEN_PARAM] ?? ''));
        if (!empty($ga_token) && $this->validate_home_token($ga_token)) {
            $this->dispatch_page();
            return;
        }

        // ── Admin login gate ────────────────────────────────────────────────
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            $return_url = home_url('/' . Growtype_Analytics_Frontend_Page::SLUG . '/');

            $login_url = function_exists('growtype_form_login_page_url')
                ? growtype_form_login_page_url(['redirect_after' => $return_url])
                : wp_login_url($return_url);

            wp_safe_redirect($login_url);
            exit;
        }

        $this->dispatch_page();
    }

    /**
     * Resolve the active page slug and delegate to the page renderer.
     */
    private function dispatch_page(): void
    {
        $page = sanitize_key(get_query_var(Growtype_Analytics_Frontend_Page::PAGE_VAR) ?: 'metrics');
        if (!in_array($page, Growtype_Analytics_Frontend_Page::PAGES, true)) {
            $page = 'metrics';
        }

        $this->renderer->render($page);
        exit;
    }

    /**
     * Validate a ga_token against stored 'home' type shared links.
     * Updates last_used_at on success.
     */
    private function validate_home_token(string $token): bool
    {
        $all_links = get_option('growtype_analytics_share_access_links', []);
        if (!is_array($all_links)) {
            return false;
        }

        foreach ($all_links as &$link) {
            if (
                ($link['report_type'] ?? '') === 'home' &&
                hash_equals($link['token'] ?? '', $token)
            ) {
                $link['last_used_at'] = current_time('mysql');
                update_option('growtype_analytics_share_access_links', $all_links, false);
                return true;
            }
        }

        return false;
    }

    /**
     * Honour redirect_to=/growtype-analytics/ after login for admin users.
     * WordPress normally overrides redirect_to with the dashboard for admins.
     *
     * @param string  $redirect_to           URL WP wants to redirect to
     * @param string  $requested_redirect_to The original redirect_to parameter
     * @param WP_User $user                  The user who just logged in
     */
    public function preserve_redirect_after_login(string $redirect_to, string $requested_redirect_to, $user): string
    {
        $our_base = home_url('/' . Growtype_Analytics_Frontend_Page::SLUG . '/');

        // Check both standard redirect_to and the custom login page's redirect_after param.
        $redirect_after = sanitize_url(wp_unslash($_GET['redirect_after'] ?? ''));

        if (str_starts_with($requested_redirect_to, $our_base)) {
            return $requested_redirect_to;
        }

        if ($redirect_after && str_starts_with($redirect_after, $our_base)) {
            return $redirect_after;
        }

        return $redirect_to;
    }
}
