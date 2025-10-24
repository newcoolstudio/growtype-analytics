window.growtypeAnalyticsPushToDataLayer = function (event, data = {}) {
    try {
        if (Array.isArray(window.dataLayer)) {
            window.dataLayer.push({
                event,
                ...data
            });
        } else {
            console.warn('Analytics not available: dataLayer missing');
        }
    } catch (err) {
        console.error('Failed to push to analytics:', err);
    }
}
