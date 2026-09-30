/**
 * Audit Trail Admin JavaScript
 *
 * @package FullworksActiveUsersMonitor
 */

(function($) {
    'use strict';

    // Initialize audit admin functionality
    $(document).ready(function() {
        initAuditDetailsModal();
        initExportForm();
        enhanceBulkActions();
        initDateFilters();
    });

    /**
     * Initialize audit entry details modal
     */
    function initAuditDetailsModal() {
        var $modal = $('#fwaum-audit-details-modal');
        var $content = $('#fwaum-audit-details-content');
        var $close = $('.fwaum-modal-close');

        // Handle view details links
        $(document).on('click', '.fwaum-view-details', function(e) {
            e.preventDefault();

            var entryId = $(this).data('id');
            if (!entryId) {
                return;
            }

            // Show loading state
            $content.html('<p>' + fwaumAuditAjax.strings.loading + '</p>');
            $modal.show();

            // Fetch entry details via AJAX
            $.ajax({
                url: fwaumAuditAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fwaum_audit_get_details',
                    id: entryId,
                    nonce: fwaumAuditAjax.nonce
                },
                success: function(response) {
                    $content.html(response);
                },
                error: function() {
                    $content.html('<p>' + fwaumAuditAjax.strings.error + '</p>');
                }
            });
        });

        // Close modal
        $close.on('click', function() {
            $modal.hide();
        });

        // Close modal on background click
        $modal.on('click', function(e) {
            if (e.target === this) {
                $modal.hide();
            }
        });

        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $modal.is(':visible')) {
                $modal.hide();
            }
        });
    }

    /**
     * Initialize export form functionality
     */
    function initExportForm() {
        var $form = $('#fwaum-export-form');
        var $button = $('#fwaum-export-button');

        if (!$form.length) {
            return;
        }

        $form.on('submit', function(e) {
            e.preventDefault();

            // Disable button and show loading state
            $button.prop('disabled', true).text(fwaumAuditAjax.strings.exportStart);

            // Create form data
            var formData = $form.serialize();

            // Create a temporary form for file download
            var $tempForm = $('<form>', {
                method: 'POST',
                action: fwaumAuditAjax.ajaxUrl,
                style: 'display: none;'
            });

            // Add form fields
            var fields = $form.serializeArray();
            $.each(fields, function(i, field) {
                $tempForm.append($('<input>', {
                    type: 'hidden',
                    name: field.name,
                    value: field.value
                }));
            });

            // Add to body and submit
            $('body').append($tempForm);
            $tempForm.submit();

            // Clean up and restore button
            setTimeout(function() {
                $tempForm.remove();
                $button.prop('disabled', false).text($button.data('original-text') || fwaumAuditAjax.strings.exportButton);
            }, 2000);
        });

        // Store original button text
        $button.data('original-text', $button.text());
    }

    /**
     * Enhance bulk actions with confirmation
     */
    function enhanceBulkActions() {
        var $bulkForm = $('.wp-list-table').closest('form');

        if (!$bulkForm.length) {
            return;
        }

        $bulkForm.on('submit', function(e) {
            var action = $bulkForm.find('select[name="action"]').val() ||
                        $bulkForm.find('select[name="action2"]').val();

            if (action === 'delete') {
                var selectedCount = $bulkForm.find('input[name="audit_entries[]"]:checked').length;

                if (selectedCount === 0) {
                    alert(fwaumAuditAjax.strings.selectEntries);
                    e.preventDefault();
                    return;
                }

                if (!confirm(fwaumAuditAjax.strings.confirmDelete)) {
                    e.preventDefault();
                }
            }
        });
    }

    /**
     * Initialize date filter functionality
     */
    function initDateFilters() {
        var $dateFrom = $('#filter-date-from');
        var $dateTo = $('#filter-date-to');

        if (!$dateFrom.length) {
            return;
        }

        // Set max date to today
        var today = new Date().toISOString().split('T')[0];
        $dateFrom.attr('max', today);
        $dateTo.attr('max', today);

        // Ensure date range is valid
        $dateFrom.on('change', function() {
            var fromDate = $(this).val();
            if (fromDate) {
                $dateTo.attr('min', fromDate);
            }
        });

        $dateTo.on('change', function() {
            var toDate = $(this).val();
            if (toDate) {
                $dateFrom.attr('max', toDate);
            }
        });

        // Quick date range buttons (optional enhancement)
        addQuickDateFilters();
    }

    /**
     * Add quick date filter buttons
     */
    function addQuickDateFilters() {
        var $filterActions = $('.alignleft.actions').first();

        if (!$filterActions.length || $filterActions.find('.fwaum-quick-dates').length) {
            return;
        }

        var $quickDates = $('<div class="fwaum-quick-dates">');
        var strings = fwaumAuditAjax.strings;

        var dateRanges = [
            { label: strings.today, days: 0 },
            { label: strings.yesterday, days: 1, single: true },
            { label: strings.last7Days, days: 7 },
            { label: strings.last30Days, days: 30 },
            { label: strings.clear, clear: true }
        ];

        $.each(dateRanges, function(i, range) {
            var $btn = $('<button type="button" class="button button-small">')
                .text(range.label);

            if (range.clear) {
                $btn.on('click', function() {
                    $('#filter-date-from, #filter-date-to').val('');
                });
            } else {
                $btn.on('click', function() {
                    var today = new Date();
                    var endDate = new Date(today);
                    var startDate = new Date(today);

                    if (range.single) {
                        // For "Yesterday", set both dates to yesterday
                        startDate.setDate(today.getDate() - range.days);
                        endDate.setDate(today.getDate() - range.days);
                    } else if (range.days === 0) {
                        // For "Today", set both dates to today
                        // startDate and endDate are already set to today
                    } else {
                        // For ranges, set start date back by the specified days
                        startDate.setDate(today.getDate() - range.days + 1);
                    }

                    $('#filter-date-from').val(formatDate(startDate));
                    $('#filter-date-to').val(formatDate(endDate));
                });
            }

            $quickDates.append($btn);
        });

        $filterActions.append($quickDates);
    }

    /**
     * Format date for input[type="date"]
     */
    function formatDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    /**
     * Enhanced table interactions
     */
    function enhanceTableInteractions() {
        // Highlight row on hover
        $('.wp-list-table tbody tr').on('mouseenter', function() {
            $(this).addClass('fwaum-row-hover');
        }).on('mouseleave', function() {
            $(this).removeClass('fwaum-row-hover');
        });

        // Click anywhere on row to view details (except on checkboxes and links)
        $('.wp-list-table tbody tr').on('click', function(e) {
            if ($(e.target).is('input, a, button') || $(e.target).closest('a, button').length) {
                return;
            }

            var $viewLink = $(this).find('.fwaum-view-details');
            if ($viewLink.length) {
                $viewLink.trigger('click');
            }
        });
    }

    // Initialize enhanced table interactions after page load
    $(window).on('load', function() {
        enhanceTableInteractions();
    });

})(jQuery);

