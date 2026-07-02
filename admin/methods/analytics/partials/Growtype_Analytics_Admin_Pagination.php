<?php

/**
 * Shared Pagination Component for Analytics Admin Tables
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/partials
 */

class Growtype_Analytics_Admin_Pagination
{
    /**
     * Render pagination with a page number selector at the end.
     *
     * @param int    $total_items  Total count of all items
     * @param int    $per_page     Items per page
     * @param int    $current_page The active page number
     * @param string $base_url     Optional custom base URL
     */
    public static function render($total_items, $per_page, $current_page, $base_url = '')
    {
        $total_pages = ceil($total_items / $per_page);
        if ($total_pages <= 1) {
            return;
        }

        if (empty($base_url)) {
            if (wp_doing_ajax() && !empty($_SERVER['HTTP_REFERER'])) {
                $base_url = $_SERVER['HTTP_REFERER'];
            } else {
                $base_url = $_SERVER['REQUEST_URI'] ?? admin_url('admin.php');
            }
        }

        // Clean query parameters and prepare template base
        $clean_base_url = remove_query_arg(array('paged', 'refresh'), $base_url);
        $base = add_query_arg('paged', '%#%', $clean_base_url);

        // Fetch links as array, strip out the current-page static span
        $links = paginate_links(array(
            'base'      => $base,
            'format'    => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total'     => $total_pages,
            'current'   => $current_page,
            'type'      => 'array',
        ));

        // Remove the current-page item — it will be rendered separately at the end
        $nav_links = array_filter($links ?? [], function ($link) {
            return strpos($link, 'current') === false;
        });

        $paging_input = sprintf(
            '<span class="paging-input">' .
            '<input class="current-page" type="number" min="1" max="%d" value="%d"' .
            ' onkeydown="if(event.key===\'Enter\'){var v=parseInt(this.value);if(v>=1&&v<=%d){window.location.href=\'%s\'.replace(\'_PAGE_PLACEHOLDER_\',v);}}">' .
            '<span class="tablenav-paging-text"> ' . __('of', 'growtype-analytics') . ' <span class="total-pages">%d</span></span>' .
            '</span>',
            $total_pages,
            $current_page,
            $total_pages,
            esc_js(add_query_arg('paged', '_PAGE_PLACEHOLDER_', $clean_base_url)),
            $total_pages
        );
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'growtype-analytics'), number_format_i18n($total_items)); ?></span>
                <span class="pagination-links">
                    <?php
                    if (!empty($nav_links)) {
                        echo implode("\n", $nav_links);
                    }
                    echo "\n" . $paging_input;
                    ?>
                </span>
            </div>
        </div>
        <?php
    }
}
