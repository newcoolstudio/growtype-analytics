<?php

/**
 * Chat Analytics Handler
 * Handles fetching and rendering chat session data for analytics
 */
class Growtype_Analytics_User_Chat
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks()
    {
        // Register analytics section renderer
        add_action('growtype_analytics_user_analytics_sections', array($this, 'render_analytics_section'), 20);

        // Funnel integration
        add_filter('growtype_analytics_funnel_steps', array($this, 'add_chat_funnel_steps'), 10, 2);
        add_filter('growtype_analytics_funnel_step_completed', array($this, 'check_chat_funnel_completion'), 10, 3);
    }

    /**
     * Add chat-related steps to the conversion funnel
     */
    public function add_chat_funnel_steps($steps, $events)
    {
        // Add "Chat" step between Registration (20) and Checkout (30)
        $steps[] = array(
            'id' => 'chat',
            'name' => 'Chat',
            'icon' => '💬',
            'completed' => false,
            'url' => '',
            'priority' => 25
        );

        return $steps;
    }

    /**
     * Determine if chat-related funnel steps are completed
     */
    public function check_chat_funnel_completion($completed, $step, $event)
    {
        $event_name = $event['event'] ?? '';
        $props = $event['properties'] ?? array();

        if ($step['id'] === 'chat') {
            // Check if user visited a chat page or triggered a chat event
            if (strpos($props['$pathname'] ?? '', '/chat/') !== false || 
                $event_name === 'growtype_chat_session_started' || 
                $event_name === 'growtype_chat_message_sent') {
                return true;
            }
        }

        return $completed;
    }

    /**
     * Get internal chat user ID from WP user ID
     */
    private function get_chat_user_id($wp_user_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}growtype_chat_users WHERE external_id = %d",
            $wp_user_id
        ));
    }

    /**
     * Render chat sessions for a user
     */
    public function render_user_sessions($user_id)
    {
        global $wpdb;

        $chat_user_id = $this->get_chat_user_id($user_id);

        if (!$chat_user_id) {
            return '<div class="empty-state">No chat user found for this account</div>';
        }
        
        // Get chat sessions for this user using pivot table
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.* 
            FROM {$wpdb->prefix}growtype_chat_sessions s
            INNER JOIN {$wpdb->prefix}growtype_chat_user_session us ON s.id = us.session_id
            WHERE us.user_id = %d 
            ORDER BY s.created_at DESC 
            LIMIT 20",
            $chat_user_id
        ));

        if (empty($sessions)) {
            return '<div class="empty-state">No chat sessions found</div>';
        }

        $export_data = []; // Will collect structured data for JSON export

        $html = '<div class="chat-sessions-list">';

        // Batch fetching for optimization
        $session_ids = wp_list_pluck($sessions, 'id');
        $all_messages = [];
        if (!empty($session_ids)) {
            $ids_in = implode(',', array_map('intval', $session_ids));
            $all_messages_results = $wpdb->get_results(
                "SELECT m.*, sm.session_id
                 FROM {$wpdb->prefix}growtype_chat_messages m
                 INNER JOIN {$wpdb->prefix}growtype_chat_session_message sm ON m.id = sm.message_id
                 WHERE sm.session_id IN ($ids_in)
                 ORDER BY m.created_at ASC"
            );
            foreach ($all_messages_results as $msg) {
                $all_messages[$msg->session_id][] = $msg;
            }

            // Also warm up the metadata cache for these sessions in one query
            if (class_exists('Growtype_Chat_Session')) {
                Growtype_Chat_Session::get_settings_batch($session_ids);
            }
        }

        foreach ($sessions as $session) {
            $session_export = []; // Export data for this session

            // Use batched messages
            $messages = $all_messages[$session->id] ?? [];

            $message_count = count($messages);
            $user_messages = array_filter($messages, function ($msg) use ($chat_user_id) {
                if (isset($msg->author_type)) {
                    return $msg->author_type === 'user';
                }
                return (int)$msg->user_id === (int)$chat_user_id;
            });
            $bot_messages = array_filter($messages, function ($msg) use ($chat_user_id) {
                if (isset($msg->author_type)) {
                    return $msg->author_type === 'bot';
                }
                return (int)$msg->user_id !== (int)$chat_user_id;
            });

            $created_time = strtotime($session->created_at);
            $time_ago = human_time_diff($created_time, current_time('timestamp')) . ' ago';

            // Extract character settings and thumbnail early
            $settings = [];
            $character_slug = '';
            $bot_image_html = '<div class="session-icon">💬</div>';

            if (class_exists('Growtype_Chat_Session')) {
                $chat_session = Growtype_Chat_Session::get($session->id);
                $settings = $chat_session['settings'] ?? [];
                $character_slug = $settings['slug'] ?? '';

                    if (!empty($chat_session['featured_images'])) {
                        $bot_image_html = '<div class="session-icon g-chatsessions-single-image" style="padding:0; background:transparent; display:flex; gap:4px; max-width:80px; overflow:hidden;">';
                        foreach ($chat_session['featured_images'] as $featured_image) {
                            if (isset($featured_image['url'])) {
                                $bot_image_html .= '<div class="g-chatsessions-single-image-user" style="min-width:36px;width:56px;height:56px;border-radius:50%;background: url(\'' . esc_url($featured_image['url']) . '\');background-size: cover;background-position: center;"></div>';
                            }
                        }
                        $bot_image_html .= '</div>';
                    }
                }

            // Build export data for this session
            $session_export = [
                'session_id'      => $session->id,
                'created_at'      => $session->created_at,
                'user_messages'   => count($user_messages),
                'bot_responses'   => count($bot_messages),
                'total_messages'  => $message_count,
                'settings'        => $settings,
                'messages'        => [],
            ];

            $html .= '<div class="chat-session-item">';
            $html .= '<div class="session-header">';
            $html .= $bot_image_html;
            $html .= '<div class="session-info" style="width:60%;">';
            $html .= '<div class="session-title">';
            $html .= '<strong>Session #' . esc_html($session->id) . '</strong>';
            if (!empty($session->bot_profile)) {
                $html .= ' <span class="bot-badge">' . esc_html($session->bot_profile) . '</span>';
            }
            $html .= '</div>';
            $roleplay_scenario = $settings['roleplay_scenario'] ?? 'none';
            $is_roleplay       = !empty($roleplay_scenario) && $roleplay_scenario !== 'none';
            $chat_type_label   = $is_roleplay
                ? '<span style="display:inline-block; background:#f0e6ff; color:#6a0dad; border-radius:4px; padding:1px 7px; font-size:0.85em; font-weight:600;">🎭 Roleplay</span>'
                : '<span style="display:inline-block; background:#e6f4ff; color:#0066cc; border-radius:4px; padding:1px 7px; font-size:0.85em; font-weight:600;">💬 Regular Chat</span>';

            $html .= '<div class="session-meta">';
            $html .= esc_html(date('M j, Y g:i A', $created_time)) . ' (' . esc_html($time_ago) . ')';
            $html .= ' • ' . count($user_messages) . ' user messages • ' . count($bot_messages) . ' bot responses';
            $html .= ' • ' . $chat_type_label;
            $html .= '</div>';

            // Display Session Meta/Settings
            if (class_exists('Growtype_Chat_Session')) {
                if (!empty($settings)) {
                    $html .= '<div class="session-settings" style="font-size: 0.85em; color: #666; margin-top: 4px; border-top: 1px solid #eee; padding-top: 4px;">';
                    $html .= '<strong>Settings:</strong> ';
                    $settings_list = [];
                    foreach ($settings as $key => $val) {
                        // Skip internal/system meta if needed, but user asked for "show all"
                        $display_val = is_string($val) ? $val : json_encode($val);
                        $settings_list[] = '<span class="setting-item" title="' . esc_attr($key) . '">' . esc_html($key) . ': ' . esc_html($display_val) . '</span>';
                    }
                    $html .= implode(' • ', $settings_list);
                    $html .= '</div>';
                }
            }

            // Display Session URLs
            $urls = [];

            if (!empty($settings) && !empty($character_slug)) {
                // 1. Character profile link
                $character_url = home_url('/chat/' . $character_slug);
                $urls[] = '<a href="' . esc_url($character_url) . '" target="_blank">'
                    . sprintf(__('Character Link (%s)', 'growtype-analytics'), $character_slug)
                    . '</a>';

                // 2. Direct chat session link (opens the exact session)
                if (class_exists('Growtype_Chat_Session')) {
                    $session_token = Growtype_Chat_Session::get_token($session->id);
                    if ($session_token) {
                        $session_chat_url = home_url('/chat/' . $session_token);
                        $urls[] = '<a href="' . esc_url($session_chat_url) . '" target="_blank">'
                            . __('Chat Session Link', 'growtype-analytics')
                            . '</a>';

                        // 3. Admin impersonation link — opens session as the viewed user
                        if (current_user_can('manage_options')) {
                            $impersonate_url = add_query_arg(
                                array('as_user_id' => $user_id),
                                home_url('/chat/' . $session_token)
                            );
                            $urls[] = '<a href="' . esc_url($impersonate_url) . '" target="_blank" '
                                . 'style="color:#d63638; font-weight:600;" '
                                . 'title="' . esc_attr__('Opens this session as the user (admin only)', 'growtype-analytics') . '">'
                                . '🔑 ' . __('View as User', 'growtype-analytics')
                                . '</a>';
                        }
                    }
                }
            }

            if (!empty($urls)) {
                 $html .= '<div class="session-urls" style="font-size: 0.85em; margin-top: 4px; color: #0073aa;">' . implode(' • ', $urls) . '</div>';
            }

            $html .= '</div>';
            $html .= '<div class="session-actions" style="display:flex; gap:6px; margin-top:8px;">';
            $html .= '<button class="toggle-messages button" data-session="' . esc_attr($session->id) . '">Show Messages (' . $message_count . ')</button>';
            $html .= '<button class="export-session button button-secondary" data-session="' . esc_attr($session->id) . '" title="Export this session as JSON">⬇ Export</button>';
            $html .= '<button class="copy-session button button-secondary" data-session="' . esc_attr($session->id) . '" title="Copy session JSON to clipboard">📋 Copy</button>';
            $html .= '</div>';
            $html .= '</div>';

            // Messages container (hidden by default)
            $html .= '<div class="session-messages" id="messages-' . esc_attr($session->id) . '" style="display: none;">';
            
            if (!empty($messages)) {
                foreach ($messages as $message) {
                    // Check logic for author type
                    $is_user = isset($message->author_type) && $message->author_type === 'user';
                    // Fallback if user_id matches provided user_id
                    if (!isset($message->author_type)) {
                        // For fallback, we compare with the chat_user_id
                        $is_user = (int)$message->user_id === (int)$chat_user_id;
                    }

                    $html .= '<div class="chat-message ' . ($is_user ? 'user-message' : 'bot-message') . '">';
                    $html .= '<div class="message-author">' . ($is_user ? '👤 User' : '🤖 Bot') . '</div>';
                    
                    // Decode/Decrypt message content
                    $content = $message->content;
                    $rendered_message = '';
                    $decoded_for_export = null;
                    
                    if (class_exists('Growtype_Chat_Message')) {
                        $decoded = Growtype_Chat_Message::decode_content($content);
                        $decoded_for_export = $decoded;
                        
                        if (is_array($decoded)) {
                            // Render Main Text
                            if (!empty($decoded['main_text'])) {
                                $rendered_message .= wp_kses_post($decoded['main_text']);
                            }
                            
                            // Render Images
                            if (!empty($decoded['images'])) {
                                $rendered_message .= '<div class="g-chatform-message-assets-wrapper" data-amount="' . count($decoded['images']) . '">';
                                foreach ($decoded['images'] as $image) {
                                    if (isset($image['url'])) {
                                        $url = esc_url($image['url']);
                                        $rendered_message .= '<div class="g-chatform-message-img-wrapper">';
                                        $rendered_message .= '<a href="' . $url . '" class="growtype-theme-fancybox image-wrapper" data-fancybox="gallery">';
                                        $rendered_message .= '<img src="' . $url . '" class="img-fluid" style="max-width: 200px; height: auto; border-radius: 8px;">';
                                        $rendered_message .= '</a>';
                                        $rendered_message .= '</div>';
                                    }
                                }
                                $rendered_message .= '</div>';
                            }
                            
                            // Render Videos
                            if (!empty($decoded['videos'])) {
                                $rendered_message .= '<div class="g-chatform-message-assets-wrapper">';
                                foreach ($decoded['videos'] as $video) {
                                    if (isset($video['url'])) {
                                        $url = esc_url($video['url']);
                                        $rendered_message .= '<div class="g-chatform-message-img-wrapper">';
                                        $rendered_message .= '<video src="' . $url . '" controls style="max-width: 100%; border-radius: 8px;"></video>';
                                        $rendered_message .= '</div>';
                                    }
                                }
                                $rendered_message .= '</div>';
                            }

                            // Render Audio
                            if (!empty($decoded['audio_url'])) {
                                $rendered_message .= '<div class="audio-message" data-url="' . esc_url($decoded['audio_url']) . '">';
                                $rendered_message .= '<div class="audio-message-play">';
                                $rendered_message .= '<span class="dashicons dashicons-controls-pause" data-action="pause"></span>';
                                $rendered_message .= '<span class="dashicons dashicons-controls-play" data-action="play"></span><span class="e-label">Play</span>';
                                $rendered_message .= '</div>';
                                $rendered_message .= '<div class="audio-message-content"></div>';
                                $rendered_message .= '</div>';
                            }
                            
                            // Fallback if empty
                            if (empty($rendered_message)) {
                                $rendered_message = json_encode($decoded);
                            }
                        } else {
                            $rendered_message = wp_kses_post($decoded);
                            $decoded_for_export = $decoded;
                        }
                    } else {
                        $rendered_message = wp_kses_post($content);
                        $decoded_for_export = $content;
                    }

                    // Collect message for export
                    $session_export['messages'][] = [
                        'role'       => $is_user ? 'user' : 'bot',
                        'content'    => $decoded_for_export,
                        'created_at' => $message->created_at,
                    ];
                    
                    $html .= '<div class="message-content">' . $rendered_message . '</div>';
                    $html .= '<div class="message-time">' . esc_html(date('g:i A', strtotime($message->created_at))) . '</div>';
                    $html .= '</div>';
                }
            }
            
            $html .= '</div>';
            $html .= '</div>';

            $export_data[] = $session_export;
        }

        $html .= '</div>';

        // Embed export data and add JavaScript for toggle + export
        $export_json = wp_json_encode($export_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $html .= '<script>
        var growtypeSessionsExportData = ' . $export_json . ';

        jQuery(document).ready(function($) {
            $(".toggle-messages").on("click", function() {
                var sessionId = $(this).data("session");
                var messagesDiv = $("#messages-" + sessionId);
                
                if (messagesDiv.is(":visible")) {
                    messagesDiv.slideUp();
                    $(this).text("Show Messages (" + messagesDiv.find(".chat-message").length + ")");
                } else {
                    messagesDiv.slideDown();
                    $(this).text("Hide Messages");
                }
            });

            $("#growtype-export-sessions-btn").on("click", function() {
                var json = JSON.stringify(growtypeSessionsExportData, null, 2);
                var blob = new Blob([json], {type: "application/json"});
                var url  = URL.createObjectURL(blob);
                var a    = document.createElement("a");
                a.href     = url;
                a.download = "chat-sessions-export.json";
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            $(".export-session").on("click", function() {
                var sessionId = parseInt($(this).data("session"), 10);
                var sessionData = growtypeSessionsExportData.find(function(s) { return parseInt(s.session_id, 10) === sessionId; });
                if (!sessionData) return;
                var json = JSON.stringify(sessionData, null, 2);
                var blob = new Blob([json], {type: "application/json"});
                var url  = URL.createObjectURL(blob);
                var a    = document.createElement("a");
                a.href     = url;
                a.download = "session-" + sessionId + "-export.json";
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            $(".copy-session").on("click", function() {
                var $btn = $(this);
                var sessionId = parseInt($btn.data("session"), 10);
                var sessionData = growtypeSessionsExportData.find(function(s) { return parseInt(s.session_id, 10) === sessionId; });
                if (!sessionData) return;
                var json = JSON.stringify(sessionData, null, 2);
                navigator.clipboard.writeText(json).then(function() {
                    var original = $btn.text();
                    $btn.text("\u2713 Copied!");
                    setTimeout(function() { $btn.text(original); }, 2000);
                }).catch(function() {
                    // Fallback for older browsers
                    var ta = document.createElement("textarea");
                    ta.value = json;
                    ta.style.position = "fixed";
                    ta.style.opacity  = "0";
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand("copy");
                    document.body.removeChild(ta);
                    var original = $btn.text();
                    $btn.text("\u2713 Copied!");
                    setTimeout(function() { $btn.text(original); }, 2000);
                });
            });
        });
        </script>';

        return $html;
    }

    /**
     * Get chat session count for a user
     */
    public function get_session_count($user_id)
    {
        global $wpdb;
        
        $chat_user_id = $this->get_chat_user_id($user_id);

        if (!$chat_user_id) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(s.id) 
            FROM {$wpdb->prefix}growtype_chat_sessions s
            INNER JOIN {$wpdb->prefix}growtype_chat_user_session us ON s.id = us.session_id
            WHERE us.user_id = %d",
            $chat_user_id
        ));
    }

    /**
     * Get total message count for a user
     */
    public function get_message_count($user_id)
    {
        global $wpdb;
        
        $chat_user_id = $this->get_chat_user_id($user_id);

        if (!$chat_user_id) {
            return 0;
        }
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(m.id) 
            FROM {$wpdb->prefix}growtype_chat_messages m
            INNER JOIN {$wpdb->prefix}growtype_chat_session_message sm ON m.id = sm.message_id
            INNER JOIN {$wpdb->prefix}growtype_chat_user_session us ON sm.session_id = us.session_id
            WHERE us.user_id = %d",
            $chat_user_id
        ));
    }

    /**
     * Render the complete Chat analytics section
     */
    public function render_analytics_section($user_id)
    {
        // Check if current user has admin capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="analytics-section">
            <h2>Growtype Chat Analytics</h2>

            <div class="analytics-grid">
                <!-- Chat Credits Usage -->
                <div class="analytics-card credits" style="grid-column: 1 / -1;">
                    <h3><?php _e('Chat Credits Usage', 'growtype-analytics'); ?></h3>
                    <div id="growtype-chat-credits">
                        <?php
                        if (function_exists('growtype_chat_user_credits')) {
                            $credits = growtype_chat_user_credits($user_id);
                            echo '<div class="credits-display">';
                            echo '<span class="credits-amount">' . number_format($credits) . '</span>';
                            echo ' <span class="credits-label">' . __('Credits Available', 'growtype-analytics') . '</span>';
                            echo '</div>';
                        } else {
                            echo '<div class="empty-state">' . __('Credits function not available', 'growtype-analytics') . '</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="analytics-card chat-sessions" style="grid-column: 1 / -1;">
                    <h3 style="display:flex; align-items:center; gap:10px;">
                        <?php _e('Chat Sessions', 'growtype-analytics'); ?>
                        <button id="growtype-export-sessions-btn" class="button button-secondary" style="font-size:0.85em; padding:2px 10px; margin-left:auto;">
                            ⬇ <?php _e('Export JSON', 'growtype-analytics'); ?>
                        </button>
                    </h3>
                    <div id="chat-sessions-data">
                        <?php echo $this->render_user_sessions($user_id); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
