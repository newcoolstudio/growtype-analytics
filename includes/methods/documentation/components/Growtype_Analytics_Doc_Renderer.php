<?php

/**
 * Documentation site renderer
 *
 * Owns all rendering responsibilities: page shell, page dispatch,
 * URL helpers, shared-links section, and the copy-to-clipboard script.
 *
 * Consumed by:
 *   - Growtype_Analytics_Doc_Request_Handler  → render()
 *   - Growtype_Analytics_Doc_Layout           → render_page_content(), page_url()
 *   - Growtype_Analytics_Frontend_Page_*      → render_shared_links_section(), print_copy_script()
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/documentation/components
 */
class Growtype_Analytics_Doc_Renderer
{
    // ── Entry point ────────────────────────────────────────────────────────

    /**
     * Render the full page shell for the given page slug.
     * Called by Growtype_Analytics_Doc_Request_Handler.
     */
    public function render(string $active_page): void
    {
        (new Growtype_Analytics_Doc_Layout($this))->render($active_page);
    }

    // ── Layout callbacks ───────────────────────────────────────────────────

    /**
     * Dispatch to the correct page-content renderer.
     * Called by Growtype_Analytics_Doc_Layout.
     */
    public function render_page_content(string $active_page): void
    {
        $method = 'render_' . $active_page;
        if (method_exists($this, $method)) {
            $this->$method();
        }
    }

    /**
     * Build the URL for a documentation sub-page.
     * Called by Growtype_Analytics_Doc_Layout.
     */
    public function page_url(string $page): string
    {
        return home_url('/' . Growtype_Analytics_Frontend_Page::SLUG . '/' . $page . '/');
    }

    // ── Page class helpers ─────────────────────────────────────────────────

    /**
     * Render the "Shared Report URLs" doc-section for a given report type.
     * Called by Metrics and Strategy page classes.
     *
     * @param string $report_type  e.g. 'metrics' or 'strategy'
     * @param string $id_prefix    unique prefix for copyable input IDs
     */
    public function render_shared_links_section(string $report_type, string $id_prefix): void
    {
        Growtype_Analytics_Doc_Shared_Links::render($report_type, $id_prefix);
    }

    /**
     * Print the gaCopy() JS helper (idempotent — only emitted once per page).
     * Called by Metrics and Strategy page classes.
     */
    public function print_copy_script(): void
    {
        static $printed = false;
        if ($printed) return;
        $printed = true;
        ?>
        <script>
        function gaCopy(inputId, btn) {
            var el = document.getElementById(inputId);
            if (!el) return;
            navigator.clipboard ? navigator.clipboard.writeText(el.value) : (el.select(), document.execCommand('copy'));
            var orig = btn.textContent;
            btn.textContent = '\u2713';
            btn.style.background = '#16a34a';
            setTimeout(function() { btn.textContent = orig; btn.style.background = ''; }, 2000);
        }
        </script>
        <?php
    }

    // ── Page dispatchers ───────────────────────────────────────────────────

    private function render_metrics(): void
    {
        (new Growtype_Analytics_Frontend_Page_Metrics($this))->render();
    }

    private function render_strategy(): void
    {
        (new Growtype_Analytics_Frontend_Page_Strategy($this))->render();
    }
}
