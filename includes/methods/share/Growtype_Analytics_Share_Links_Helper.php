<?php

/**
 * Shared links data helper
 *
 * Centralises URL resolution for shared report links.
 * Used by both the admin settings table and the frontend documentation table.
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/share
 */
class Growtype_Analytics_Share_Links_Helper
{
    private const OPTION_KEY = 'growtype_analytics_share_access_links';

    /**
     * Return enriched link records, optionally filtered by report type.
     *
     * Each record is the raw stored array plus:
     *   'url'      — the primary access URL (REST JSON or tokenised page URL)
     *   'html_url' — the HTML-rendered variant (same as url for 'home' type)
     *
     * @param  string|null $report_type  Filter to a specific type, or null for all.
     * @return array
     */
    public static function get_links(?string $report_type = null): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            return [];
        }

        $links = array_map([self::class, 'enrich'], $raw);

        if ($report_type !== null) {
            $links = array_values(array_filter(
                $links,
                fn($l) => $l['report_type'] === $report_type
            ));
        }

        return $links;
    }

    /**
     * Compute url / html_url for a single raw link record.
     *
     * @param  array $link  Raw record from the option.
     * @return array        Record with 'url' and 'html_url' added.
     */
    public static function enrich(array $link): array
    {
        // Normalise missing report_type (legacy records).
        $type  = $link['report_type'] ?? 'metrics';
        $token = $link['token'] ?? '';

        $link['report_type'] = $type;

        if ($type === 'home') {
            $url = add_query_arg(Growtype_Analytics_Frontend_Page::TOKEN_PARAM, $token, home_url('/growtype-analytics/'));
            $link['url']      = $url;
            $link['html_url'] = $url;
        } else {
            $route            = 'growtype-analytics/v1/report/' . $type . '/' . rawurlencode($token);
            $link['url']      = rest_url($route);
            $link['html_url'] = add_query_arg('content_format', 'html', rest_url($route));
        }

        return $link;
    }
}
