<?php

class Growtype_Analytics_Admin_Settings_Tab_Strategy extends Growtype_Analytics_Admin_Settings_Tab_Base
{
    const OPTION_KEY      = 'growtype_analytics_strategy_steps';
    const SYNC_OPTION_KEY = 'growtype_analytics_strategy_sync';

    public function get_id() { return 'strategy'; }
    public function get_label() { return __('Strategy', 'growtype-analytics'); }
    public function get_description() { return __('Define your execution strategy. Each step has a title and a value you fill in.', 'growtype-analytics'); }

    public function handle_actions()
    {
        if (!current_user_can('manage_options') || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (empty($_POST['growtype_analytics_strategy_nonce'])) {
            return;
        }

        $action = sanitize_key($_POST['strategy_action'] ?? 'save');

        // ── Save sync settings ────────────────────────────────────────────
        if ($action === 'save_sync') {
            if (!wp_verify_nonce(sanitize_key(wp_unslash($_POST['growtype_analytics_strategy_nonce'])), 'growtype_analytics_strategy_sync')) {
                return;
            }

            $secret = sanitize_text_field(wp_unslash($_POST['strategy_sync_secret'] ?? ''));

            if (strlen($secret) < 20) {
                add_settings_error('growtype_analytics_strategy', 'sync_err', __('Sync secret must be at least 20 characters. Use the Generate button.', 'growtype-analytics'), 'error');
                return;
            }

            update_option(self::SYNC_OPTION_KEY, [
                'base_url' => esc_url_raw(trim(wp_unslash($_POST['strategy_sync_base_url'] ?? ''))),
                'secret'   => $secret,
            ], false);

            add_settings_error('growtype_analytics_strategy', 'sync_saved', __('Sync settings saved.', 'growtype-analytics'), 'updated');
            return;
        }

        // ── Pull from production ──────────────────────────────────────────
        if ($action === 'pull') {
            if (!wp_verify_nonce(sanitize_key(wp_unslash($_POST['growtype_analytics_strategy_nonce'])), 'growtype_analytics_strategy_sync')) {
                return;
            }

            $sync    = get_option(self::SYNC_OPTION_KEY, []);
            $base    = rtrim($sync['base_url'] ?? '', '/');
            $secret  = $sync['secret'] ?? '';

            if (empty($base) || empty($secret)) {
                add_settings_error('growtype_analytics_strategy', 'sync_err', __('Production URL and secret are required.', 'growtype-analytics'), 'error');
                return;
            }

            $url      = $base . '/growtype-analytics/v1/strategy/sync';
            $response = wp_remote_get($url, [
                'timeout'   => 15,
                'sslverify' => true,
                'headers'   => ['X-Sync-Secret' => $secret],
            ]);

            if (is_wp_error($response)) {
                add_settings_error('growtype_analytics_strategy', 'pull_err', sprintf(__('Pull failed: %s', 'growtype-analytics'), $response->get_error_message()), 'error');
                return;
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code !== 200 || empty($body['steps'])) {
                add_settings_error('growtype_analytics_strategy', 'pull_err', sprintf(__('Pull failed (HTTP %d). Check your URL and secret.', 'growtype-analytics'), $code), 'error');
                return;
            }

            $clean = array_map(function ($s) {
                return [
                    'title'       => sanitize_text_field($s['title'] ?? ''),
                    'description' => sanitize_textarea_field($s['description'] ?? ''),
                    'value'       => sanitize_textarea_field($s['value'] ?? ''),
                ];
            }, $body['steps']);

            update_option(self::OPTION_KEY, $clean, false);
            add_settings_error('growtype_analytics_strategy', 'pulled', __('Strategy pulled from production successfully.', 'growtype-analytics'), 'updated');
            return;
        }

        // ── Push to production ────────────────────────────────────────────
        if ($action === 'push') {
            if (!wp_verify_nonce(sanitize_key(wp_unslash($_POST['growtype_analytics_strategy_nonce'])), 'growtype_analytics_strategy_sync')) {
                return;
            }

            $sync   = get_option(self::SYNC_OPTION_KEY, []);
            $base   = rtrim($sync['base_url'] ?? '', '/');
            $secret = $sync['secret'] ?? '';

            if (empty($base) || empty($secret)) {
                add_settings_error('growtype_analytics_strategy', 'sync_err', __('Production URL and secret are required.', 'growtype-analytics'), 'error');
                return;
            }

            $steps    = get_option(self::OPTION_KEY, []);
            $url      = $base . '/growtype-analytics/v1/strategy/sync';
            $response = wp_remote_post($url, [
                'timeout'   => 15,
                'sslverify' => true,
                'headers'   => ['Content-Type' => 'application/json', 'X-Sync-Secret' => $secret],
                'body'      => wp_json_encode(['steps' => $steps]),
            ]);

            if (is_wp_error($response)) {
                add_settings_error('growtype_analytics_strategy', 'push_err', sprintf(__('Push failed: %s', 'growtype-analytics'), $response->get_error_message()), 'error');
                return;
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                $msg = json_decode(wp_remote_retrieve_body($response), true);
                add_settings_error('growtype_analytics_strategy', 'push_err', sprintf(__('Push failed (HTTP %d): %s', 'growtype-analytics'), $code, $msg['message'] ?? ''), 'error');
                return;
            }

            add_settings_error('growtype_analytics_strategy', 'pushed', __('Strategy pushed to production successfully.', 'growtype-analytics'), 'updated');
            return;
        }

        // ── Default: save steps form ──────────────────────────────────────
        if (!wp_verify_nonce(sanitize_key(wp_unslash($_POST['growtype_analytics_strategy_nonce'])), 'growtype_analytics_strategy_save')) {
            return;
        }

        $raw_steps = isset($_POST['strategy_steps']) ? (array) wp_unslash($_POST['strategy_steps']) : [];
        $clean_steps = [];

        foreach ($raw_steps as $step) {
            $title       = sanitize_text_field($step['title'] ?? '');
            $description = sanitize_textarea_field($step['description'] ?? '');
            $value       = sanitize_textarea_field($step['value'] ?? '');

            if ($title === '' && $value === '' && $description === '') {
                continue;
            }

            $clean_steps[] = ['title' => $title, 'description' => $description, 'value' => $value];
        }

        update_option(self::OPTION_KEY, $clean_steps, false);

        add_settings_error('growtype_analytics_strategy', 'saved', __('Strategy saved.', 'growtype-analytics'), 'updated');
    }

    public function render()
    {
        $steps = $this->get_steps();
        settings_errors('growtype_analytics_strategy');
        ?>
        <div class="analytics-strategy" style="max-width: 900px; margin-top: 20px;">
            <style>
                .strategy-step {
                    background: #fff;
                    border: 1px solid #c3c4c7;
                    border-left: 4px solid #2271b1;
                    padding: 16px 20px;
                    margin-bottom: 12px;
                    border-radius: 2px;
                    position: relative;
                }
                .strategy-step .step-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 10px;
                }
                .strategy-step .step-title {
                    flex: 1;
                    font-size: 14px;
                    font-weight: 600;
                    border: 1px solid #ddd;
                    padding: 6px 10px;
                    border-radius: 3px;
                    width: 100%;
                }
                .strategy-step textarea {
                    width: 100%;
                    min-height: 80px;
                    font-family: monospace;
                    font-size: 13px;
                    resize: vertical;
                }
                .strategy-step .remove-step {
                    background: none;
                    border: none;
                    color: #d63638;
                    cursor: pointer;
                    font-size: 18px;
                    line-height: 1;
                    padding: 2px 6px;
                    border-radius: 3px;
                    flex-shrink: 0;
                }
                .strategy-step .remove-step:hover {
                    background: #ffeaea;
                }
                #strategy-steps-wrap .drag-handle {
                    cursor: grab;
                    color: #aaa;
                    font-size: 18px;
                    flex-shrink: 0;
                    user-select: none;
                }
                .strategy-step.is-dragging {
                    opacity: 0.4;
                }
            </style>

            <form method="post" action="">
                <?php wp_nonce_field('growtype_analytics_strategy_save', 'growtype_analytics_strategy_nonce'); ?>

                <div id="strategy-steps-wrap">
                    <?php foreach ($steps as $i => $step): ?>
                        <div class="strategy-step">
                            <div class="step-header">
                                <span class="drag-handle" title="<?php esc_attr_e('Drag to reorder', 'growtype-analytics'); ?>">⠿</span>
                                <input
                                    type="text"
                                    class="step-title"
                                    name="strategy_steps[<?php echo $i; ?>][title]"
                                    value="<?php echo esc_attr($step['title']); ?>"
                                    placeholder="<?php esc_attr_e('Step title', 'growtype-analytics'); ?>"
                                />
                                <button type="button" class="remove-step" title="<?php esc_attr_e('Remove step', 'growtype-analytics'); ?>">✕</button>
                            </div>
                            <?php if (!empty($step['description'])): ?>
                                <p class="description" style="font-family: monospace; font-size: 12px; white-space: pre-wrap; background: #f6f7f7; border: 1px solid #ddd; padding: 8px 10px; margin: 0 0 8px; border-radius: 2px;"><?php echo esc_html($step['description']); ?></p>
                                <input type="hidden" name="strategy_steps[<?php echo $i; ?>][description]" value="<?php echo esc_attr($step['description']); ?>" />
                            <?php else: ?>
                                <textarea
                                    name="strategy_steps[<?php echo $i; ?>][description]"
                                    placeholder="<?php esc_attr_e('Optional description / hint for this step...', 'growtype-analytics'); ?>"
                                    rows="2"
                                    style="font-size: 12px; margin-bottom: 6px; background: #f6f7f7;"
                                ><?php echo esc_textarea($step['description'] ?? ''); ?></textarea>
                            <?php endif; ?>
                            <textarea
                                name="strategy_steps[<?php echo $i; ?>][value]"
                                placeholder="<?php esc_attr_e('Your answer...', 'growtype-analytics'); ?>"
                                rows="4"
                            ><?php echo esc_textarea($step['value']); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p style="margin-top: 16px;">
                    <button type="button" id="add-strategy-step" class="button button-secondary">
                        + <?php _e('Add Step', 'growtype-analytics'); ?>
                    </button>
                </p>

                <?php submit_button(__('Save Strategy', 'growtype-analytics'), 'primary', 'submit', false); ?>
            </form>
        </div>

        <?php
        $answered_steps = array_filter($steps, function ($s) { return !empty($s['value']); });
        if (!empty($answered_steps)):
            $prompt = $this->build_ai_prompt($steps);
        ?>
        <div class="analytics-strategy-report" style="max-width: 900px; margin-top: 32px;">
            <hr />
            <h2 style="margin-top: 24px;"><?php _e('AI Prompt — Prioritized Todo List', 'growtype-analytics'); ?></h2>
            <p><?php _e('Copy this prompt and paste it into Claude, ChatGPT, or any AI. It will generate a prioritized action todo list based on your answers.', 'growtype-analytics'); ?></p>
            <div style="position: relative;">
                <textarea
                    id="strategy-ai-prompt"
                    readonly
                    style="width: 100%; min-height: 400px; font-family: monospace; font-size: 12px; line-height: 1.6; background: #f0f6fc; border: 1px solid #b3c9e0; padding: 16px; resize: vertical;"
                ><?php echo esc_textarea($prompt); ?></textarea>
                <button
                    type="button"
                    id="copy-ai-prompt"
                    class="button button-secondary"
                    style="position: absolute; top: 8px; right: 8px;"
                ><?php _e('Copy', 'growtype-analytics'); ?></button>
            </div>
            <p style="margin-top: 10px;">
                <button type="button" id="copy-ai-prompt-main" class="button button-primary" style="font-size: 14px; padding: 6px 18px; height: auto;">
                    📋 <?php _e('Copy AI Prompt', 'growtype-analytics'); ?>
                </button>
            </p>
        </div>
        <script>
        (function () {
            function copyPrompt() {
                var ta = document.getElementById('strategy-ai-prompt');
                ta.select();
                ta.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(ta.value).then(function () {
                    ['copy-ai-prompt', 'copy-ai-prompt-main'].forEach(function (id) {
                        var btn = document.getElementById(id);
                        if (btn) {
                            var orig = btn.textContent;
                            btn.textContent = '✓ Copied!';
                            setTimeout(function () { btn.textContent = orig; }, 2000);
                        }
                    });
                });
            }
            document.getElementById('copy-ai-prompt').addEventListener('click', copyPrompt);
            document.getElementById('copy-ai-prompt-main').addEventListener('click', copyPrompt);
        })();
        </script>
        <?php endif; ?>

        <script>
        (function () {
            var wrap = document.getElementById('strategy-steps-wrap');
            var addBtn = document.getElementById('add-strategy-step');

            function reindex() {
                wrap.querySelectorAll('.strategy-step').forEach(function (step, i) {
                    step.querySelectorAll('[name]').forEach(function (el) {
                        el.name = el.name.replace(/strategy_steps\[\d+\]/, 'strategy_steps[' + i + ']');
                    });
                });
            }

            function makeStep(title, description, value) {
                var i = wrap.querySelectorAll('.strategy-step').length;
                var div = document.createElement('div');
                div.className = 'strategy-step';
                div.innerHTML =
                    '<div class="step-header">' +
                        '<span class="drag-handle" title="Drag to reorder">⠿</span>' +
                        '<input type="text" class="step-title" name="strategy_steps[' + i + '][title]" value="' + (title || '').replace(/"/g, '&quot;') + '" placeholder="Step title" />' +
                        '<button type="button" class="remove-step" title="Remove step">✕</button>' +
                    '</div>' +
                    '<textarea name="strategy_steps[' + i + '][description]" placeholder="Optional description / hint for this step..." rows="2" style="font-size:12px;margin-bottom:6px;background:#f6f7f7;">' + (description || '') + '</textarea>' +
                    '<textarea name="strategy_steps[' + i + '][value]" placeholder="Your answer..." rows="4">' + (value || '') + '</textarea>';
                return div;
            }

            addBtn.addEventListener('click', function () {
                wrap.appendChild(makeStep('', '', ''));
                reindex();
                wrap.lastElementChild.querySelector('.step-title').focus();
            });

            wrap.addEventListener('click', function (e) {
                if (e.target.classList.contains('remove-step')) {
                    e.target.closest('.strategy-step').remove();
                    reindex();
                }
            });
        })();
        </script>
        <?php
        // ── Pull / Push sync section ──────────────────────────────────────
        $sync = get_option(self::SYNC_OPTION_KEY, []);
        ?>
        <div style="max-width: 900px; margin-top: 36px;">
            <hr />
            <h2 style="margin-top: 24px;"><?php _e('Sync with Production', 'growtype-analytics'); ?></h2>
            <p><?php _e('Pull overwrites local data with production. Push overwrites production with local data. Both directions require the shared secret to be set on both environments.', 'growtype-analytics'); ?></p>

            <?php /* Sync settings */ ?>
            <form method="post" action="" style="margin-bottom: 20px;">
                <?php wp_nonce_field('growtype_analytics_strategy_sync', 'growtype_analytics_strategy_nonce'); ?>
                <input type="hidden" name="strategy_action" value="save_sync" />
                <table class="form-table" role="presentation" style="max-width: 700px;">
                    <tr>
                        <th scope="row"><label for="strategy_sync_base_url"><?php _e('Production WP-JSON base URL', 'growtype-analytics'); ?></label></th>
                        <td>
                            <input type="url" id="strategy_sync_base_url" name="strategy_sync_base_url" class="regular-text" value="<?php echo esc_attr($sync['base_url'] ?? ''); ?>" placeholder="https://talkiemate.com/wp-json" style="width: 400px;" />
                            <p class="description"><?php _e('Example: https://yoursite.com/wp-json', 'growtype-analytics'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="strategy_sync_secret"><?php _e('Sync secret key', 'growtype-analytics'); ?></label></th>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" id="strategy_sync_secret" name="strategy_sync_secret" class="regular-text" value="<?php echo esc_attr($sync['secret'] ?? ''); ?>" placeholder="<?php esc_attr_e('Click Generate or paste your own', 'growtype-analytics'); ?>" style="width: 340px; font-family: monospace;" />
                                <button type="button" id="generate-sync-secret" class="button button-secondary"><?php _e('Generate', 'growtype-analytics'); ?></button>
                            </div>
                            <p class="description" style="margin-top: 6px;">
                                <?php _e('Set the <strong>same secret</strong> on both local and production, then save on both.', 'growtype-analytics'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save sync settings', 'growtype-analytics'), 'secondary', 'submit', false); ?>
            </form>
            <script>
            document.getElementById('generate-sync-secret').addEventListener('click', function () {
                var chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                var arr    = new Uint8Array(40);
                crypto.getRandomValues(arr);
                var secret = Array.from(arr).map(function (b) { return chars[b % chars.length]; }).join('');
                document.getElementById('strategy_sync_secret').value = secret;
                document.getElementById('strategy_sync_secret').type  = 'text';
                var btn = this;
                btn.textContent = '✓ Generated — save & copy to production';
                setTimeout(function () { btn.textContent = 'Generate new'; }, 3000);
            });
            </script>

            <?php /* Pull / Push buttons */ ?>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('Pull from production? This will OVERWRITE your local strategy data.', 'growtype-analytics')); ?>');">
                    <?php wp_nonce_field('growtype_analytics_strategy_sync', 'growtype_analytics_strategy_nonce'); ?>
                    <input type="hidden" name="strategy_action" value="pull" />
                    <?php submit_button(__('⬇ Pull from production', 'growtype-analytics'), 'secondary', 'submit', false); ?>
                </form>

                <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('Push to production? This will OVERWRITE production strategy data with your local data.', 'growtype-analytics')); ?>');">
                    <?php wp_nonce_field('growtype_analytics_strategy_sync', 'growtype_analytics_strategy_nonce'); ?>
                    <input type="hidden" name="strategy_action" value="push" />
                    <?php submit_button(__('⬆ Push to production', 'growtype-analytics'), 'primary', 'submit', false, ['style' => 'background:#d63638;border-color:#d63638;']); ?>
                </form>
            </div>
        </div>
        <?php
    }

    private function get_steps()
    {
        $saved = get_option(self::OPTION_KEY, null);

        if (is_array($saved) && !empty($saved)) {
            return $saved;
        }

        return [
            ['title' => '0. CONTEXT LOCK',                    'description' => "Date of data: \nData reliability: real / estimated / mixed\nBusiness type: AI SaaS / marketplace / content / etc.\nMonetization maturity: none / early / stable / optimized\nTime this data covers: ",                                                                                                                                                                                                                           'value' => ''],
            ['title' => '1. BUSINESS IDENTITY',               'description' => "What is it: \nWho is it for: \nCore use case: \nMain competitors: \nYour unfair advantage: \nWhy users choose you over competitors: \nWhy users leave for competitors: \nGeography: \nLanguage: ",                                                                                                                                                                       'value' => ''],
            ['title' => '2. OUTCOME SYSTEM',                  'description' => "Primary goal: €10M ARR / €1M MRR / etc.\nTime constraint: \nSecondary goal: \nNon-goals: ",                                                                                                                                                                                                                                                                       'value' => ''],
            ['title' => '3. ECONOMIC CORE MODEL',             'description' => "Revenue per paying user/month (ARPU): €\nGross margin %: \nContribution margin per user: €\nCAC by channel: \nPayback period: \nLTV: \nLTV:CAC ratio: \nBiggest cost driver: ",                                                                                                                                                                                   'value' => ''],
            ['title' => '4. GROWTH EQUATION',                 'description' => "Monthly traffic: \nVisitor → signup %: \nSignup → activation %: \nActivation → paid %: \nPaid → retained % (monthly): \nAvg revenue per paying user/month: €\nCurrent MRR result: €\nGap to €1M MRR: ",                                                                                                                                                          'value' => ''],
            ['title' => '5. FULL FUNNEL EVENT LOG',           'description' => "First touch: \nSignup event: \nActivation event: \nFirst value moment: \nFirst payment trigger: \nRepeat value trigger: \nChurn trigger: ",                                                                                                                                                                                                                        'value' => ''],
            ['title' => '6. SEGMENT VALUE MAP',               'description' => "Power / Casual / Churn users:\n% of total users: \n% of total revenue: \nLTV: \nCAC: \nRetention strength: \nBehavioral pattern: \nAcquisition source: \nWhy they stay/leave: ",                                                                                                                                                                                  'value' => ''],
            ['title' => '7. ACQUISITION SYSTEMS',             'description' => "SEO / Paid / Social / Referral / Partnerships:\nDaily input: \nOutput: \nScalability (1-10): \nSaturation risk: \nDependency risk: \nMonthly spend: ",                                                                                                                                                                                                            'value' => ''],
            ['title' => '8. PRODUCT VALUE LOOP',              'description' => "Trigger: \nUser action: \nReward: \nReinforcement: \nCurrently working? Y/N: ",                                                                                                                                                                                                                                                                                   'value' => ''],
            ['title' => '9. CONVERSION BREAKPOINTS',          'description' => "Landing → Signup: \nSignup → Activation: \nActivation → Paid: \nPaid → Retained: \nRetained → Upsell: ",                                                                                                                                                                                                                                                           'value' => ''],
            ['title' => '10. MONETIZATION STRUCTURE',         'description' => "Pricing logic: \nCurrent price points / tiers: \nWhat each tier unlocks: \nUpsell paths: \nExpansion revenue potential: \nUnderpriced areas: \nOver-friction payment steps: \nPayment methods available: ",                                                                                                                                                       'value' => ''],
            ['title' => "11. WHAT HAS WORKED & WHAT HASN'T", 'description' => "Wins: \nFailures: \nCurrently running experiments: ",                                                                                                                                                                                                                                                                                                              'value' => ''],
            ['title' => '12. COMPETITOR GAP ANALYSIS',        'description' => "Competitor strength: \nCompetitor weakness: \nUsers who left them for you: \nUsers who left you for them: ",                                                                                                                                                                                                                                                        'value' => ''],
            ['title' => '13. BOTTLENECK MATRIX (1-10)',        'description' => "Traffic constraint: \nConversion constraint: \nActivation constraint: \nRetention constraint: \nMonetization constraint: \nPricing constraint: \nProduct-market fit strength: \nDistribution constraint: \n→ Single biggest bottleneck: ",                                                                                                                       'value' => ''],
            ['title' => '14. LEVER IMPACT MATRIX',            'description' => "Lever: \nEffort (1-10): \nImpact (1-10): \nTime to Result: \nConfidence: \nDependency Risk: ",                                                                                                                                                                                                                                                                   'value' => ''],
            ['title' => '15. RESOURCE REALITY MODEL',         'description' => "Time available/day: \nBudget/month for growth: €\nTeam size + roles: \nYour role: \nTechnical constraints: \nDistribution constraints: \nBiggest internal bottleneck: ",                                                                                                                                                                                     'value' => ''],
            ['title' => '16. DATA LAYER',                     'description' => "Cohort retention table: \nTop 10 landing pages by traffic: \nTop converting funnels: \nGeographic split: \nDevice split: \nRevenue by segment: \nAvg session length / sessions per user per week: \nDAU / MAU ratio: ",                                                                                                                                         'value' => '']
        ];
    }

    private function build_ai_prompt(array $steps)
    {
        $lines = [];
        $lines[] = 'You are a growth strategy expert and execution advisor.';
        $lines[] = '';
        $lines[] = 'Below is a structured business context I have filled out. Based ONLY on the information provided:';
        $lines[] = '';
        $lines[] = '1. Generate a PRIORITIZED TODO LIST (max 20 items) ordered by:';
        $lines[] = '   - Highest revenue impact';
        $lines[] = '   - Lowest effort / fastest to execute';
        $lines[] = '   - Removing the biggest bottleneck first';
        $lines[] = '';
        $lines[] = '2. For each todo item include:';
        $lines[] = '   - Action (specific, not vague)';
        $lines[] = '   - Why it matters (1 sentence)';
        $lines[] = '   - Estimated effort: LOW / MEDIUM / HIGH';
        $lines[] = '   - Expected impact: LOW / MEDIUM / HIGH';
        $lines[] = '   - Time to result: days / weeks / months';
        $lines[] = '';
        $lines[] = '3. At the end, highlight the SINGLE most critical thing to do this week.';
        $lines[] = '';
        $lines[] = str_repeat('=', 60);
        $lines[] = 'BUSINESS CONTEXT';
        $lines[] = str_repeat('=', 60);
        $lines[] = '';

        foreach ($steps as $step) {
            $title = trim($step['title'] ?? '');
            $value = trim($step['value'] ?? '');

            if ($title === '') {
                continue;
            }

            $lines[] = '[ ' . $title . ' ]';

            if ($value !== '') {
                $lines[] = $value;
            } else {
                $lines[] = '(not answered)';
            }

            $lines[] = '';
        }

        $lines[] = str_repeat('=', 60);
        $lines[] = 'END OF CONTEXT';
        $lines[] = str_repeat('=', 60);
        $lines[] = '';
        $lines[] = 'Now generate the prioritized TODO list.';

        return implode("\n", $lines);
    }
}
