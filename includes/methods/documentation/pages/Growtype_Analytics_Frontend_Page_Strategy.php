<?php

/**
 * Frontend documentation page — Strategy
 *
 * Renders the Strategy tab of the analytics docs site.
 * Receives the parent page instance so it can call shared helpers
 * (render_shared_links_section, print_copy_script).
 *
 * @package    Growtype_Analytics
 * @subpackage growtype_analytics/includes/methods/frontend/pages
 */
class Growtype_Analytics_Frontend_Page_Strategy
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
        $this->renderer->render_shared_links_section('strategy', 'ga-url-strategy');
        ?>
        <div class="doc-header">
            <h1>🎯 Strategy</h1>
            <p>Growth levers, priorities, and how to read the analytics to drive decisions.</p>
        </div>

        <div class="doc-section">
            <h2>North Star</h2>
            <p>The single metric that best captures the value users get from the product and drives sustainable revenue.</p>
            <div class="callout">
                <strong>North Star:</strong> Number of paid conversations per week — it measures both engagement and monetisation simultaneously.
            </div>
        </div>

        <div class="doc-section">
            <h2>Growth Model</h2>
            <div class="strategy-steps">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <h3>Acquire — Drive targeted traffic</h3>
                        <p>Focus paid and organic spend on channels where <code>utm_source</code> attribution shows the highest New User → Buyer conversion. Monitor <code>redirect_after</code> to identify which character pages attract buyers.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <h3>Activate — Reduce time to first value</h3>
                        <p>Shorten the path from registration to first chat. Any user who completes a conversation within 15 minutes of signing up is 3× more likely to convert to a buyer.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-body">
                        <h3>Monetise — Optimise the paywall moment</h3>
                        <p>Use Payment Failure Segmentation to identify device/gateway failure patterns. A high failure rate on mobile is often a UX or gateway config issue, not demand.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-body">
                        <h3>Retain — Increase repurchase rate</h3>
                        <p>Target users with D7 return but no second purchase with a credit bundle offer. Repurchase rate above 40% signals strong product-market fit.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">5</div>
                    <div class="step-body">
                        <h3>Refer — Creator monetisation flywheel</h3>
                        <p>Creators who earn from their characters become distribution partners. Track creator earnings in the Contributors tab and tie payouts to conversation volume.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="doc-section">
            <h2>Key Decision Rules</h2>
            <ul>
                <li>If <strong>Activation Rate &lt; 30%</strong> — fix onboarding before increasing ad spend.</li>
                <li>If <strong>Payment Success Rate &lt; 85%</strong> — audit gateway config and mobile UX before scaling.</li>
                <li>If <strong>Repurchase Rate &gt; 40%</strong> — increase CAC budget; LTV justifies it.</li>
                <li>If <strong>D7 Retention &lt; 20%</strong> — focus on notification re-engagement and new character launches.</li>
            </ul>
        </div>

        <div class="doc-section">
            <h2>Analytics Review Cadence</h2>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-name">Daily</div>
                    <div class="kpi-def">New registrations, payment success rate, active conversations.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">Weekly</div>
                    <div class="kpi-def">Activation rate, UTM attribution, D7 retention, creator earnings.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-name">Monthly</div>
                    <div class="kpi-def">ARPU, repurchase rate, D30 retention, full funnel conversion.</div>
                </div>
            </div>
        </div>
        <?php
    }
}
