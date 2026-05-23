<?php

class Growtype_Analytics_Tracking_Scripts
{
    public function __construct()
    {
        /**
         * Scripts
         */
        add_action('wp_footer', array ($this, 'wp_footer_extend'));
    }

    function wp_footer_extend()
    {
        $marketing_sources = array ();
        if (isset($_GET) && !empty($_GET) && !is_user_logged_in()) {
            // Strip internal/system GET params that are not marketing signals.
            $system_keys = ['redirect_after', 'page', 'action', 'nonce', '_wpnonce', 'paged', 'orderby', 'order'];
            $filtered_get = array_diff_key(
                array_map('sanitize_text_field', $_GET),
                array_flip($system_keys)
            );
            $marketing_sources = apply_filters('growtype_analytics_marketing_sources', $filtered_get);
        }
        ?>
        <script id="growtype-analytics-tracker">
            (function () {
                var trackedKeys = {};
                var eventQueue = [];
                var syncTimeout = null;
                var REST_URL = '<?php echo esc_url_raw(rest_url('growtype-analytics/v1/track')); ?>';
                var MARKETING_SOURCES = <?php echo wp_json_encode($marketing_sources); ?>;

                function setCookie(name, value, days) {
                    var expires = '';
                    if (days) {
                        var date = new Date();
                        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                        expires = '; expires=' + date.toUTCString();
                    }
                    document.cookie = name + '=' + (value || '') + expires + '; path=/';
                }

                function getCookie(name) {
                    var nameEQ = name + '=';
                    var ca = document.cookie.split(';');
                    for (var i = 0; i < ca.length; i++) {
                        var c = ca[i];
                        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                    }
                    return null;
                }

                function sendBatch() {
                    if (eventQueue.length === 0) return;
                    var batch = eventQueue.slice();
                    eventQueue = [];
                    if (typeof fetch === 'function') {
                        fetch(REST_URL, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({events: batch})
                        }).catch(function (err) {
                            console.debug('Growtype Analytics:', err);
                        });
                    }
                }

                function queueEvent(data) {
                    var key = data.object_id + '_' + data.event_type;
                    if (trackedKeys[key]) return;
                    trackedKeys[key] = true;
                    eventQueue.push(data);
                    clearTimeout(syncTimeout);
                    syncTimeout = setTimeout(sendBatch, 500);
                }

                function isVisible(el) {
                    // Check if the element itself is hidden
                    if (el.offsetParent === null && el.style.display !== 'fixed') return false;
                    // Check if inside a hidden modal (Bootstrap pattern)
                    var modal = el.closest('.modal');
                    if (modal && !modal.classList.contains('show')) return false;
                    return true;
                }

                function scan() {
                    // 1. Explicit markers (PHP-generated .growtype-analytics-track spans)
                    var markers = document.querySelectorAll('.growtype-analytics-track:not([data-tracked])');
                    for (var i = 0; i < markers.length; i++) {
                        var el = markers[i];
                        if (!isVisible(el)) continue;
                        queueEvent({
                            event_type: el.getAttribute('data-event-type'),
                            object_id: el.getAttribute('data-object-id'),
                            object_type: el.getAttribute('data-object-type'),
                            metadata: JSON.parse(el.getAttribute('data-metadata') || '{}')
                        });
                        el.setAttribute('data-tracked', 'true');
                    }

                    // 2. Auto-detect WooCommerce payment buttons with data-product-id
                    var buttons = document.querySelectorAll('.growtype-wc-payment-button[data-product-id]:not([data-tracked-auto])');
                    for (var j = 0; j < buttons.length; j++) {
                        var btn = buttons[j];
                        if (!isVisible(btn)) continue;
                        var productId = btn.getAttribute('data-product-id');
                        if (productId) {
                            queueEvent({
                                event_type: 'offer_shown',
                                object_id: productId,
                                object_type: 'product',
                                metadata: {}
                            });
                            btn.setAttribute('data-tracked-auto', 'true');
                        }
                    }

                    // 3. Auto-detect any element with data-growtype-analytics-track-id attribute
                    var tracked = document.querySelectorAll('[data-growtype-analytics-track-id]:not([data-tracked-auto])');
                    for (var p = 0; p < tracked.length; p++) {
                        var el2 = tracked[p];
                        if (!isVisible(el2)) continue;
                        var trackId = el2.getAttribute('data-growtype-analytics-track-id');
                        if (trackId) {
                            queueEvent({
                                event_type: 'offer_shown',
                                object_id: trackId,
                                object_type: el2.getAttribute('data-growtype-analytics-track-type') || 'product',
                                metadata: {name: el2.getAttribute('data-growtype-analytics-track-name') || ''}
                            });
                            el2.setAttribute('data-tracked-auto', 'true');
                        }
                    }
                }


                // Marketing sources
                if (MARKETING_SOURCES && Object.keys(MARKETING_SOURCES).length > 0) {
                    var existing = getCookie('growtype_analytics_marketing_sources');
                    var sources = existing ? JSON.parse(existing) : [];
                    var added = false;
                    var entries = Object.entries(MARKETING_SOURCES);
                    for (var m = 0; m < entries.length; m++) {
                        var k = entries[m][0], v = entries[m][1];
                        var found = false;
                        for (var n = 0; n < sources.length; n++) {
                            if (sources[n].key === k) {
                                found = true;
                                break;
                            }
                        }
                        if (!found) {
                            sources.push({key: k, value: v});
                            added = true;
                        }
                    }
                    if (added) {
                        setCookie('growtype_analytics_marketing_sources', JSON.stringify(sources), 30);
                    }
                }

                // Initial scan
                scan();

                // Live watching: auto-detects products in modals, AJAX content, infinite scroll
                if (typeof MutationObserver !== 'undefined') {
                    var observer = new MutationObserver(scan);
                    observer.observe(document.body, {childList: true, subtree: true});
                }

                window.addEventListener('load', scan);
            })();
        </script>
        <?php
    }
}
