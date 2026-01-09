// Optional debug
const GTAC_DEBUG = false;

// Initialize global queue
window._gtacQueue = window._gtacQueue || [];

// Unified analytics function
window.growtypeAnalyticsCapture = function(event, data = {}) {
    if (!event) return;
    if (!data) data = {};

    // Push event to queue
    window._gtacQueue.push({ event, data });

    if (GTAC_DEBUG) console.log('Event queued:', event, data);
};

// Flush queued events continuously until GTM and PostHog are ready
(function flushGtacQueue() {
    if (!window._gtacQueue || window._gtacQueue.length === 0) {
        setTimeout(flushGtacQueue, 100);
        return;
    }

    const gtmReady = Array.isArray(window.dataLayer);
    const posthogReady = typeof window.posthog !== 'undefined';

    if (gtmReady || posthogReady) {
        // Flush all queued events
        while (window._gtacQueue.length > 0) {
            const item = window._gtacQueue.shift();

            if (gtmReady) {
                try { window.dataLayer.push({ event: item.event, ...item.data }); }
                catch (e) { if (GTAC_DEBUG) console.error(e); }
            }

            if (posthogReady) {
                try { window.posthog.capture(item.event, item.data); }
                catch (e) { if (GTAC_DEBUG) console.error(e); }
            }
        }
    }

    // Retry in 100ms
    setTimeout(flushGtacQueue, 100);
})();
