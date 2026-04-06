<?php

/**
 * Centralized user filter registry.
 * SINGLE SOURCE OF TRUTH — add a filter here only; UI + SQL auto-update.
 *
 * Each entry:
 *   label  string  Pill label
 *   icon   string  Emoji
 *   color  string  Hex color
 *   having string  SQL HAVING fragment (alias must exist in SELECT)
 */
class Growtype_Analytics_Admin_User_Filters
{
    /** Pages that show the filter pill strip. Add a slug to opt that page in. */
    const FILTER_PAGES = ['growtype-analytics-users'];

    public static function registry(): array
    {
        return [
            'paid_orders_only' => [
                'label'  => 'Paid Only',
                'icon'   => '💳',
                'color'  => '#0066cc',
                'having' => 'paid_orders > 0',
            ],
            // 'has_messages' => [
            //     'label'  => 'Has Messages',
            //     'icon'   => '💬',
            //     'color'  => '#00a32a',
            //     'having' => 'message_count > 0',
            // ],
            // 'no_credits' => [
            //     'label'  => 'No Credits',
            //     'icon'   => '🪙',
            //     'color'  => '#d63638',
            //     'having' => 'chat_credits_amount = 0',
            // ],
        ];
    }

    public static function active_from_request(): array
    {
        if (empty($_GET['user_filters']) || !is_array($_GET['user_filters'])) {
            return [];
        }
        return array_values(array_intersect(
            array_map('sanitize_key', (array) $_GET['user_filters']),
            array_keys(self::registry())
        ));
    }

    public static function build_having_sql(array $active_filters): string
    {
        $registry = self::registry();
        $parts    = [];
        foreach ($active_filters as $key) {
            if (!empty($registry[$key]['having'])) {
                $parts[] = $registry[$key]['having'];
            }
        }
        return !empty($parts) ? 'HAVING ' . implode(' AND ', $parts) : '';
    }
}
