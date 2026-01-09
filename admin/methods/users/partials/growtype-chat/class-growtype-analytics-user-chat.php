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

        $html = '<div class="chat-sessions-list">';
        
        foreach ($sessions as $session) {
            // Get messages for this session using pivot table
            $messages = $wpdb->get_results($wpdb->prepare(
                "SELECT m.* 
                FROM {$wpdb->prefix}growtype_chat_messages m
                INNER JOIN {$wpdb->prefix}growtype_chat_session_message sm ON m.id = sm.message_id
                WHERE sm.session_id = %d 
                ORDER BY m.created_at ASC",
                $session->id
            ));

            $message_count = count($messages);
            $user_messages = array_filter($messages, function($msg) {
                return isset($msg->author_type) && $msg->author_type === 'user';
            });
            $bot_messages = array_filter($messages, function($msg) {
                return isset($msg->author_type) && $msg->author_type === 'bot';
            });

            $created_time = strtotime($session->created_at);
            $time_ago = human_time_diff($created_time, current_time('timestamp')) . ' ago';

            $html .= '<div class="chat-session-item">';
            $html .= '<div class="session-header">';
            $html .= '<div class="session-icon">💬</div>';
            $html .= '<div class="session-info">';
            $html .= '<div class="session-title">';
            $html .= '<strong>Session #' . esc_html($session->id) . '</strong>';
            if (!empty($session->bot_profile)) {
                $html .= ' <span class="bot-badge">' . esc_html($session->bot_profile) . '</span>';
            }
            $html .= '</div>';
            $html .= '<div class="session-meta">';
            $html .= esc_html(date('M j, Y g:i A', $created_time)) . ' (' . esc_html($time_ago) . ')';
            $html .= ' • ' . count($user_messages) . ' user messages • ' . count($bot_messages) . ' bot responses';
            $html .= '</div>';

            // Display Session Meta/Settings
            if (class_exists('Growtype_Chat_Session')) {
                $settings = Growtype_Chat_Session::get_settings($session->id);
                if (!empty($settings)) {
                    $html .= '<div class="session-settings" style="font-size: 0.85em; color: #666; margin-top: 4px; border-top: 1px solid #eee; padding-top: 4px;">';
                    $html .= '<strong>Settings:</strong> ';
                    $settings_list = [];
                    foreach ($settings as $setting) {
                        // Skip internal/system meta if needed, but user asked for "show all"
                         $val = is_string($setting['meta_value']) ? $setting['meta_value'] : json_encode($setting['meta_value']);
                         $settings_list[] = '<span class="setting-item" title="' . esc_attr($setting['meta_key']) . '">' . esc_html($setting['meta_key']) . ': ' . esc_html($val) . '</span>';
                    }
                    $html .= implode(' • ', $settings_list);
                    $html .= '</div>';
                }
            }

            // Display Session URLs
            $urls = [];
            
            // Character URL
            if (!empty($settings)) {
                $character_slug = '';
                foreach ($settings as $setting) {
                    if ($setting['meta_key'] === 'slug') {
                        $character_slug = $setting['meta_value'];
                        break;
                    }
                }
                
                if (!empty($character_slug)) {
                     $character_url = home_url('/chat/' . $character_slug);
                     $urls[] = '<a href="' . esc_url($character_url) . '" target="_blank">' . sprintf(__('Character Link (%s)', 'growtype-analytics'), $character_slug) . '</a>';
                }
            }

            if (!empty($urls)) {
                 $html .= '<div class="session-urls" style="font-size: 0.85em; margin-top: 4px; color: #0073aa;">' . implode(' • ', $urls) . '</div>';
            }

            $html .= '</div>';
            $html .= '<button class="toggle-messages button" data-session="' . esc_attr($session->id) . '">Show Messages (' . $message_count . ')</button>';
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
                    
                    if (class_exists('Growtype_Chat_Message')) {
                        $decoded = Growtype_Chat_Message::decode_content($content);
                        
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
                        }
                    } else {
                        $rendered_message = wp_kses_post($content);
                    }
                    
                    $html .= '<div class="message-content">' . $rendered_message . '</div>';
                    $html .= '<div class="message-time">' . esc_html(date('g:i A', strtotime($message->created_at))) . '</div>';
                    $html .= '</div>';
                }
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // Add JavaScript for toggle functionality
        $html .= '<script>
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
                    <h3><?php _e('Chat Sessions', 'growtype-analytics'); ?></h3>
                    <div id="chat-sessions-data">
                        <?php echo $this->render_user_sessions($user_id); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
