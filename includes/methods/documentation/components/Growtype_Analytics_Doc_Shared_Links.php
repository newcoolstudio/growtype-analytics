<?php

/**
 * Reusable component: Shared Report URLs table
 *
 * Renders a "Shared Report URLs" doc-section for a given report type,
 * pulling live data from the growtype_analytics_share_access_links option.
 *
 * Usage:
 *   Growtype_Analytics_Doc_Shared_Links::render('metrics', 'ga-url-metrics');
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/documentation/components
 */
class Growtype_Analytics_Doc_Shared_Links
{
    /**
     * Render the shared links section.
     *
     * @param string $report_type  e.g. 'metrics' or 'strategy'
     * @param string $id_prefix    unique prefix for copyable input IDs on this page
     */
    public static function render(string $report_type, string $id_prefix): void
    {
        $links        = Growtype_Analytics_Share_Links_Helper::get_links($report_type);
        $settings_url = esc_url(admin_url('options-general.php?page=growtype-analytics-settings&tab=share-access'));
        ?>
        <div class="doc-section">
            <h2>Shared Report URLs</h2>
            <p>Active shared links for the <strong><?php echo esc_html(ucfirst($report_type)); ?></strong> report, managed in <a href="<?php echo $settings_url; ?>">Settings &rarr; Share Access</a>.</p>
            <?php if (empty($links)): ?>
                <p style="margin-top:14px; color:var(--muted); font-size:13.5px;">No shared <?php echo esc_html(ucfirst($report_type)); ?> links yet. Generate one in <a href="<?php echo $settings_url; ?>">Settings &rarr; Share Access</a>.</p>
            <?php else: ?>
            <div class="shared-links-scroll" style="margin-top:16px;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border); text-align:left;">
                        <th style="padding:8px 12px; font-weight:600; color:var(--muted);">Label</th>
                        <th style="padding:8px 12px; font-weight:600; color:var(--muted);">JSON URL</th>
                        <th style="padding:8px 12px; font-weight:600; color:var(--muted);">HTML URL</th>
                        <th style="padding:8px 12px; font-weight:600; color:var(--muted); white-space:nowrap;">Created</th>
                        <th style="padding:8px 12px; font-weight:600; color:var(--muted); white-space:nowrap;">Last used</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($links as $idx => $link):
                    $label     = esc_html($link['label'] ?? '&mdash;');
                    $created   = esc_html($link['created_at'] ?? '&mdash;');
                    $last_used = esc_html($link['last_used_at'] ?: '&mdash;');
                    $json_url  = esc_attr($link['url']);
                    $html_url  = esc_attr($link['html_url']);
                    $json_id   = $id_prefix . '-json-' . $idx;
                    $html_id   = $id_prefix . '-html-' . $idx;
                    ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px 12px; font-weight:500; white-space:nowrap;"><?php echo $label; ?></td>
                        <td style="padding:10px 12px; min-width:260px;">
                            <div style="display:flex; gap:6px; align-items:center;">
                                <input id="<?php echo $json_id; ?>" type="text" readonly value="<?php echo $json_url; ?>"
                                       onclick="this.select();"
                                       style="flex:1; font-size:11.5px; font-family:monospace; border:1px solid var(--border); border-radius:5px; padding:5px 8px; color:var(--text); background:var(--bg); min-width:0;">
                                <button onclick="gaCopy('<?php echo $json_id; ?>', this)" style="flex-shrink:0; padding:5px 10px; font-size:11px; font-weight:600; background:var(--accent); color:#fff; border:none; border-radius:5px; cursor:pointer; white-space:nowrap;">Copy</button>
                            </div>
                        </td>
                        <td style="padding:10px 12px; min-width:260px;">
                            <div style="display:flex; gap:6px; align-items:center;">
                                <input id="<?php echo $html_id; ?>" type="text" readonly value="<?php echo $html_url; ?>"
                                       onclick="this.select();"
                                       style="flex:1; font-size:11.5px; font-family:monospace; border:1px solid var(--border); border-radius:5px; padding:5px 8px; color:var(--text); background:var(--bg); min-width:0;">
                                <button onclick="gaCopy('<?php echo $html_id; ?>', this)" style="flex-shrink:0; padding:5px 10px; font-size:11px; font-weight:600; background:var(--accent); color:#fff; border:none; border-radius:5px; cursor:pointer; white-space:nowrap;">Copy</button>
                            </div>
                        </td>
                        <td style="padding:10px 12px; white-space:nowrap; color:var(--muted);"><?php echo $created; ?></td>
                        <td style="padding:10px 12px; white-space:nowrap; color:var(--muted);"><?php echo $last_used; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
