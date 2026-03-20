jQuery(document).ready(function($) {
    $('.analytics-ajax-section').each(function() {
        var $section = $(this);
        var sectionName = $section.data('section');
        var dateFrom = $section.data('date-from');
        var dateTo = $section.data('date-to');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'growtype_analytics_load_section',
                section: sectionName,
                date_from: dateFrom,
                date_to: dateTo,
                nonce: growtype_analytics_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    $section.html(response.data.html);
                    // Trigger a re-init for any JS components in the newly loaded content
                    $(document).trigger('growtype_analytics_section_loaded', [sectionName, $section]);
                } else {
                    $section.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $section.html('<div class="notice notice-error"><p>Error loading section.</p></div>');
            }
        });
    });
});
