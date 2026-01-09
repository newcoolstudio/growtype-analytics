/**
 * PostHog Analytics JavaScript
 * Handles AJAX data fetching and rendering for PostHog analytics
 */

(function ($) {
    'use strict';

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return text;
        }

        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    /**
     * Format property values with appropriate styling
     */
    function formatPropertyValue(value) {
        if (value === null || value === undefined) {
            return '<span class="prop-null">null</span>';
        }

        const strValue = String(value);
        let className = 'prop-string';

        if (typeof value === 'boolean') {
            className = 'prop-boolean';
        } else if (typeof value === 'number') {
            className = 'prop-number';
        } else if (strValue.match(/^https?:\/\//)) {
            return '<a href="' + escapeHtml(strValue) + '" target="_blank" class="prop-url">' + escapeHtml(strValue) + '</a>';
        } else if (strValue.match(/^\d{4}-\d{2}-\d{2}/)) {
            className = 'prop-date';
        }

        if (typeof value === 'object') {
            return '<span class="' + className + '">' + escapeHtml(JSON.stringify(value, null, 2)) + '</span>';
        }

        return '<span class="' + className + '">' + escapeHtml(strValue) + '</span>';
    }

    /**
     * Render session recordings
     */
    function renderRecordings(recordings) {
        const $container = $('#posthog-recordings');

        if (!recordings || recordings.length === 0) {
            $container.html('<div class="empty-state">No session recordings available</div>');
            return;
        }

        // TODO: Implement full recordings rendering
        $container.html('<div class="empty-state">Session recordings feature coming soon</div>');
    }

    /**
     * Render conversion insights
     */
    function renderConversionInsights(insights, data) {
        const $container = $('#posthog-conversion-insights');
        const properties = data.properties || {};

        let insights_html = '<div class="insights-grid">';

        // 1. Location Insight
        if (properties.$geoip_country_name) {
            insights_html += `
                <div class="insight-box success">
                    <div class="insight-icon">🌍</div>
                    <div class="insight-content">
                        <h4>Local Engagement</h4>
                        <p>User is engaging from <strong>${escapeHtml(properties.$geoip_city_name || 'unknown city')}, ${escapeHtml(properties.$geoip_country_name)}</strong>.</p>
                    </div>
                </div>
            `;
        }

        // 2. Device Insight
        if (properties.$device) {
            insights_html += `
                <div class="insight-box info">
                    <div class="insight-icon">📱</div>
                    <div class="insight-content">
                        <h4>Platform Preference</h4>
                        <p>User prefers <strong>${escapeHtml(properties.$device)}</strong> using <strong>${escapeHtml(properties.$browser)}</strong>.</p>
                    </div>
                </div>
            `;
        }

        // 3. User Identification
        if (properties.email) {
            insights_html += `
                <div class="insight-box success">
                    <div class="insight-icon">👤</div>
                    <div class="insight-content">
                        <h4>Identified User</h4>
                        <p>This user is identified as <strong>${escapeHtml(properties.email)}</strong>.</p>
                    </div>
                </div>
            `;
        }

        insights_html += '</div>';

        if (insights_html === '<div class="insights-grid"></div>') {
            $container.html('<div class="empty-state">No specific insights available for this user yet.</div>');
        } else {
            $container.html(insights_html);
        }
    }

    /**
     * Render user journey
     */
    function renderJourney(journey) {
        const $container = $('#posthog-journey');

        if (!journey || journey.length === 0) {
            $container.html('<div class="empty-state">No journey data available</div>');
            return;
        }

        let html = '<div class="journey-header">';
        html += '<div class="journey-summary">📍 User Journey - ' + journey.length + ' events tracked</div>';
        html += '</div>';

        html += '<div class="journey-timeline">';

        journey.forEach(function (step, index) {
            const isLanding = step.is_landing;
            const stepClass = isLanding ? 'journey-step landing-page' : 'journey-step';

            html += '<div class="' + stepClass + '">';
            html += '<div class="journey-number">' + (index + 1) + '</div>';
            html += '<div class="journey-content">';

            // Event name
            html += '<div class="journey-page">';
            html += '<span class="page-icon">' + (isLanding ? '🚀' : '📄') + '</span>';
            html += '<strong>' + escapeHtml(step.event) + '</strong>';
            if (isLanding) {
                html += ' <span class="event-badge landing">Landing Page</span>';
            }
            html += '</div>';

            // URL if available
            if (step.url) {
                html += '<div class="journey-metadata">';
                html += '🔗 <strong>URL:</strong> ' + escapeHtml(step.url);
                html += '</div>';
            }

            // Pathname if available (only if different from URL)
            if (step.pathname && step.pathname !== step.url) {
                html += '<div class="journey-metadata">';
                html += '📂 <strong>Path:</strong> ' + escapeHtml(step.pathname);
                html += '</div>';
            }

            // Device & Browser info
            const deviceInfo = [];
            if (step.device) deviceInfo.push(step.device);
            if (step.browser) deviceInfo.push(step.browser);
            if (step.os) deviceInfo.push(step.os);

            if (deviceInfo.length > 0) {
                html += '<div class="journey-metadata">';
                html += '💻 <strong>Device:</strong> ' + escapeHtml(deviceInfo.join(' • '));
                html += '</div>';
            }

            // Location info
            if (step.city || step.country) {
                html += '<div class="journey-metadata">';
                html += '🌍 <strong>Location:</strong> ';
                const location = [step.city, step.country].filter(Boolean).join(', ');
                html += escapeHtml(location);
                html += '</div>';
            }

            // UTM parameters
            const utmParams = [];
            if (step.utm_source) utmParams.push('Source: ' + step.utm_source);
            if (step.utm_medium) utmParams.push('Medium: ' + step.utm_medium);
            if (step.utm_campaign) utmParams.push('Campaign: ' + step.utm_campaign);

            if (utmParams.length > 0) {
                html += '<div class="journey-metadata">';
                html += '📊 <strong>UTM:</strong> ' + escapeHtml(utmParams.join(' • '));
                html += '</div>';
            }

            // Timestamp
            if (step.timestamp) {
                html += '<div class="journey-time">';
                html += '🕐 <span class="absolute-time">' + escapeHtml(step.timestamp) + '</span>';
                html += '</div>';
            }

            html += '</div>'; // journey-content
            html += '</div>'; // journey-step
        });

        html += '</div>'; // journey-timeline
        $container.html(html);
    }

    /**
     * Render conversion funnel
     */
    function renderFunnel(funnel) {
        const $container = $('#posthog-funnel');

        if (!funnel || !funnel.steps || funnel.steps.length === 0) {
            $container.html('<div class="empty-state">No funnel data available</div>');
            return;
        }

        let html = '<div class="funnel-header">';
        html += '<div class="funnel-summary">';
        html += '📊 Conversion Progress: ' + funnel.completed_steps + '/' + funnel.total_steps + ' steps completed';
        html += '</div>';
        html += '</div>';

        html += '<div class="funnel-steps">';

        funnel.steps.forEach(function (step, index) {
            const stepClass = step.completed ? 'funnel-step completed' : 'funnel-step incomplete';

            html += '<div class="' + stepClass + '">';
            html += '<div class="funnel-icon">' + step.icon + '</div>';
            html += '<div class="funnel-name">' + escapeHtml(step.name) + '</div>';
            html += '<div class="funnel-status">' + (step.completed ? '✓' : '✗') + '</div>';

            if (step.url && step.completed) {
                html += '<div class="funnel-url">';
                html += '<small>' + escapeHtml(step.url) + '</small>';
                html += '</div>';
            }

            html += '</div>';

            // Add arrow between steps (except after last step)
            if (index < funnel.steps.length - 1) {
                html += '<div class="funnel-arrow">→</div>';
            }
        });

        html += '</div>'; // funnel-steps
        $container.html(html);
    }

    /**
     * Render chat credits usage
     */
    function renderCredits(credits) {
        const $container = $('#posthog-credits');

        if (!credits || Object.keys(credits).length === 0) {
            $container.html('<div class="empty-state">No credits data available</div>');
            return;
        }

        // TODO: Implement full credits rendering
        $container.html('<div class="empty-state">Credits usage feature coming soon</div>');
    }

    /**
     * Render high-level summary cards
     */
    function renderSummary(data) {
        const $container = $('#posthog-summary');
        const properties = data.properties || {};
        const sessions = data.sessions || {};

        let html = '<div class="summary-cards">';

        // 1. Total Activity
        html += `
            <div class="summary-card">
                <div class="summary-card-icon">📈</div>
                <div class="summary-card-info">
                    <div class="summary-card-label">Total Events</div>
                    <div class="summary-card-value">${sessions.total_events || 0}</div>
                </div>
            </div>
        `;

        // 2. Location
        const country = properties.$geoip_country_name || 'Unknown';
        const city = properties.$geoip_city_name || '';
        html += `
            <div class="summary-card">
                <div class="summary-card-icon">🌍</div>
                <div class="summary-card-info">
                    <div class="summary-card-label">Location</div>
                    <div class="summary-card-value">${escapeHtml(country)}</div>
                    ${city ? `<div class="summary-card-sub">${escapeHtml(city)}</div>` : ''}
                </div>
            </div>
        `;

        // 3. Device
        const device = properties.$device || properties.$os || 'Unknown';
        html += `
            <div class="summary-card">
                <div class="summary-card-icon">📱</div>
                <div class="summary-card-info">
                    <div class="summary-card-label">Primary Device</div>
                    <div class="summary-card-value">${escapeHtml(device)}</div>
                </div>
            </div>
        `;

        // 4. Browser/Platform
        const browser = properties.$browser || 'Unknown';
        html += `
            <div class="summary-card">
                <div class="summary-card-icon">🌐</div>
                <div class="summary-card-info">
                    <div class="summary-card-label">Browser</div>
                    <div class="summary-card-value">${escapeHtml(browser)}</div>
                </div>
            </div>
        `;

        html += '</div>';
        $container.html(html);
    }

    /**
     * Render drop-off analysis
     */
    function renderDropoff(dropoff) {
        const $container = $('#posthog-dropoff');

        if (!dropoff || Object.keys(dropoff).length === 0) {
            $container.html('<div class="empty-state">No drop-off data available</div>');
            return;
        }

        // Basic drop-off rendering
        let html = '<div class="dropoff-analysis">';

        if (dropoff.message) {
            html += '<div class="dropoff-point ' + (dropoff.severity || 'info') + '">';
            html += '<p>' + escapeHtml(dropoff.message) + '</p>';
            html += '</div>';
        } else {
            html += '<div class="empty-state">No drop-off points detected</div>';
        }

        html += '</div>';
        $container.html(html);
    }

    /**
     * Render events table
     */
    function renderEvents(events) {
        const $container = $('#posthog-events');

        if (!events || events.length === 0) {
            $container.html('<div class="empty-state">No events found</div>');
            return;
        }

        let html = '<table class="widefat events-table"><thead><tr>';
        html += '<th>Event</th>';
        html += '<th>Timestamp</th>';
        html += '<th>Properties</th>';
        html += '</tr></thead><tbody>';

        events.forEach(function (event) {
            html += '<tr>';
            html += '<td><strong>' + escapeHtml(event.event || 'Unknown') + '</strong></td>';
            html += '<td>' + escapeHtml(event.timestamp || '') + '</td>';
            html += '<td><pre>' + escapeHtml(JSON.stringify(event.properties || {}, null, 2)) + '</pre></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $container.html(html);
    }

    /**
     * Render properties table
     */
    function renderProperties(properties) {
        const $container = $('#posthog-properties');

        console.log('renderProperties called with:', properties);
        console.log('Properties type:', typeof properties);
        console.log('Properties keys:', Object.keys(properties || {}));

        if (!properties || Object.keys(properties).length === 0) {
            console.log('No properties to render');
            $container.html('<div class="empty-state">No properties found</div>');
            return;
        }

        console.log('Rendering', Object.keys(properties).length, 'properties');

        let html = '<table class="widefat properties-table"><thead><tr>';
        html += '<th>Property</th>';
        html += '<th>Value</th>';
        html += '</tr></thead><tbody>';

        Object.keys(properties).forEach(function (key) {
            html += '<tr>';
            html += '<td><strong>' + escapeHtml(key) + '</strong></td>';
            html += '<td>' + formatPropertyValue(properties[key]) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $container.html(html);
    }

    /**
     * Initialize PostHog analytics loading
     */
    window.GrowtypeAnalytics = window.GrowtypeAnalytics || {};

    window.GrowtypeAnalytics.loadPostHogData = function (userId, nonce) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_user_posthog_data',
                user_id: userId,
                nonce: nonce
            },
            success: function (response) {
                $('#posthog-loading').hide();

                if (response.success) {
                    $('#posthog-data').show();
                    const data = response.data;

                    // Render all sections
                    renderSummary(data);
                    renderRecordings(data.recordings || []);
                    renderConversionInsights(data.insights || {}, data);
                    renderJourney(data.journey || []);
                    renderFunnel(data.funnel || {});
                    renderCredits(data.credits || {});
                    renderDropoff(data.dropoff || {});
                    renderEvents(data.events || []);
                    renderProperties(data.properties || {});
                } else {
                    $('#posthog-error p').text(response.data.message || 'Failed to load analytics data.');
                    $('#posthog-error').show();
                }
            },
            error: function (xhr, status, error) {
                $('#posthog-loading').hide();
                $('#posthog-error p').text('Error loading analytics data: ' + error);
                $('#posthog-error').show();
            }
        });
    };

})(jQuery);
