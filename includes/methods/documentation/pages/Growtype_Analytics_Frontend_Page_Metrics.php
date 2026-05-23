<?php

/**
 * Frontend documentation page — Metrics
 *
 * Renders the Metrics tab of the analytics docs site.
 * Receives the parent page instance so it can call shared helpers
 * (render_shared_links_section, print_copy_script).
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/frontend/pages
 */
class Growtype_Analytics_Frontend_Page_Metrics
{
    /** @var Growtype_Analytics_Doc_Renderer */
    private $renderer;

    public function __construct(Growtype_Analytics_Doc_Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function render()
    {
        $this->renderer->print_copy_script();
        $this->renderer->render_shared_links_section('metrics', 'ga-url-metrics');
        ?>
        <div class="doc-header">
            <h1>📊 Metrics</h1>
            <p>All KPIs tracked in the analytics dashboard — what they measure and why they matter.</p>
        </div>

        <div class="doc-section">
            <h2>Acquisition</h2>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-name">Registered Users</div>
                    <div class="kpi-def">Total users who completed signup within the selected period.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">UTM Source</div>
                    <div class="kpi-def">Marketing channel that drove the registration (e.g. google, instagram, direct).</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">Redirect After</div>
                    <div class="kpi-def">The page the user was on when they hit the auth wall — reveals intent at signup.</div>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <h2>Activation</h2>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-name">Activation Rate</div>
                    <div class="kpi-def">% of registered users who completed a meaningful first action (e.g. first chat).</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">Time to Activate</div>
                    <div class="kpi-def">Median hours between registration and first meaningful action.</div>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <h2>Monetisation</h2>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-name">New User → Buyer</div>
                    <div class="kpi-def">% of registered users who made at least one paid order.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">Repurchase Rate</div>
                    <div class="kpi-def">% of buyers who made more than one payment — a core retention signal.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">Payment Success Rate</div>
                    <div class="kpi-def">% of payment attempts that completed successfully.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">ARPU</div>
                    <div class="kpi-def">Average Revenue Per User across all registered accounts in the period.</div>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <h2>Retention</h2>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-name">D1 / D7 / D30 Retention</div>
                    <div class="kpi-def">% of users who returned 1, 7, and 30 days after registration.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">Conversations / User</div>
                    <div class="kpi-def">Average number of chat sessions per registered user — engagement depth.</div>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <h2>Marketing Attribution</h2>
            <p>Attribution data is captured via the <code>growtype_analytics_marketing_sources</code> cookie set on first visit and saved to the lead post meta at registration. The following keys are tracked:</p>
            <ul style="margin-top:12px;">
                <li><strong>utm_source</strong> — traffic source (google, facebook, instagram…)</li>
                <li><strong>utm_medium</strong> — channel type (cpc, organic, email…)</li>
                <li><strong>utm_campaign</strong> — campaign name</li>
                <li><strong>utm_content</strong> — ad creative / variant</li>
                <li><strong>redirect_after</strong> — the page the user intended to reach before hitting the login wall</li>
            </ul>
            <div class="callout">
                System parameters (<code>redirect_after</code> is captured at registration from POST data, not from the URL, to ensure accuracy).
            </div>
        </div>
        <?php
    }
}
