<?php

/**
 * Documentation site router
 *
 * Registers WordPress rewrite rules and query vars for the /growtype-analytics/ URL tree.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/documentation/components
 */
class Growtype_Analytics_Doc_Router
{
    public function __construct()
    {
        add_action('init',        [$this, 'register_rewrites']);
        add_filter('query_vars',  [$this, 'add_query_vars']);
    }

    public function register_rewrites(): void
    {
        $slug      = Growtype_Analytics_Frontend_Page::SLUG;
        $query_var = Growtype_Analytics_Frontend_Page::QUERY_VAR;
        $page_var  = Growtype_Analytics_Frontend_Page::PAGE_VAR;

        // Root: /growtype-analytics/
        add_rewrite_rule(
            '^' . $slug . '/?$',
            'index.php?' . $query_var . '=1&' . $page_var . '=metrics',
            'top'
        );

        // Sub-pages: /growtype-analytics/{page}/
        add_rewrite_rule(
            '^' . $slug . '/([a-z0-9\-]+)/?$',
            'index.php?' . $query_var . '=1&' . $page_var . '=$matches[1]',
            'top'
        );
    }

    public function add_query_vars(array $vars): array
    {
        $vars[] = Growtype_Analytics_Frontend_Page::QUERY_VAR;
        $vars[] = Growtype_Analytics_Frontend_Page::PAGE_VAR;
        return $vars;
    }
}
