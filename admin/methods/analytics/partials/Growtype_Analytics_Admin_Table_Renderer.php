<?php

/**
 * Analytics paginated table renderer
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/admin/methods/analytics/partials
 */

class Growtype_Analytics_Admin_Table_Renderer
{
    const DEFAULT_PER_PAGE = 50;

    /**
     * @var Growtype_Analytics_Admin_Page
     */
    private $page;

    public function __construct($page)
    {
        $this->page = $page;
    }

    /**
     * Render a paginated table using WordPress standard styles
     *
     * @param array $headers Column labels
     * @param array $rows Data rows
     * @param int|null $total_items Total items in the entire dataset
     * @param int $per_page Items per page
     * @param int $current_page Current page number
     * @param string $base_url Optional base URL for pagination links
     */
    public function render($headers, $rows, $total_items = null, $per_page = self::DEFAULT_PER_PAGE, $current_page = 1, $base_url = '')
    {
        if ($total_items === null) {
            $total_items = count($rows);
        }

        $total_pages = ceil($total_items / $per_page);
        ?>
        <div class="analytics-recent-events">
            <table class="wp-list-table widefat striped">
                <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?php echo wp_kses_post($header); ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?php echo esc_attr(count($headers)); ?>"><?php _e('No data available for this view yet.', 'growtype-analytics'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php
                    $allowed_html = array_merge(wp_kses_allowed_html('post'), [
                        'select' => [
                            'class' => true,
                            'data-character-id' => true,
                            'data-roleplay-id' => true,
                            'data-roleplay-slug' => true,
                            'style' => true,
                            'name' => true,
                            'id' => true,
                        ],
                        'option' => [
                            'value' => true,
                            'selected' => true,
                        ]
                    ]);
                    foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $key => $cell): ?>
                                <?php if ($key === 'total_items_count') {
                                    continue;
                                } ?>
                                <td><?php echo wp_kses($cell, $allowed_html); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php
            Growtype_Analytics_Admin_Pagination::render($total_items, $per_page, $current_page, $base_url);
            ?>
        </div>
        <?php
    }
}
