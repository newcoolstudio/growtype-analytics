window._growtypeFacebookEventMap = window._growtypeFacebookEventMap || {};

const generateFacebookEventId = function (prefix = 'gtac') {
    return `${prefix}_${Date.now()}_${Math.floor(Math.random() * 1000000)}`;
};

window.registerGrowtypeFacebookEventMapping = function (analyticsEvent, mappings) {
    if (!analyticsEvent || !mappings) {
        return;
    }

    const normalizedMappings = Array.isArray(mappings) ? mappings : [mappings];

    window._growtypeFacebookEventMap[analyticsEvent] = normalizedMappings;
};

window.growtypeFacebookTrack = function (eventName, data = {}, options = {}) {
    if (!eventName || typeof window.fbq !== 'function') {
        return false;
    }

    const custom = options.custom === true;
    const payload = data || {};
    const method = custom ? 'trackCustom' : 'track';
    const eventId = options.eventId || generateFacebookEventId('fb');

    try {
        window.fbq(method, eventName, payload, {eventID: eventId});
        return true;
    } catch (e) {
        return false;
    }
};

const resolveFacebookMappings = function (analyticsEvent, data, options = {}) {
    if (options.facebook === false) {
        return [];
    }

    if (options.facebook === true) {
        return [{name: analyticsEvent, custom: true, data}];
    }

    if (typeof options.facebook === 'string') {
        return [{name: options.facebook, custom: false, data}];
    }

    if (Array.isArray(options.facebook)) {
        return options.facebook;
    }

    if (options.facebook && typeof options.facebook === 'object' && options.facebook.name) {
        return [options.facebook];
    }

    return window._growtypeFacebookEventMap[analyticsEvent] || [];
};

export const pushEventToFacebook = function (analyticsEvent, data, options = {}) {
    const mappings = resolveFacebookMappings(analyticsEvent, data, options);

    if (!Array.isArray(mappings) || mappings.length === 0) {
        return;
    }

    const sharedEventId = options.facebookEventId || generateFacebookEventId('fb');

    mappings.forEach((mapping) => {
        if (!mapping || !mapping.name) {
            return;
        }

        const payload = mapping.data || data || {};
        const custom = mapping.custom === true;

        window.growtypeFacebookTrack(mapping.name, payload, {
            custom,
            eventId: mapping.eventId || sharedEventId
        });
    });
};
