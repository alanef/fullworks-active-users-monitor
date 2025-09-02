jQuery(document).ready(function($) {
	// Auto-refresh dashboard widget.
	if (typeof fwaum_dashboard !== 'undefined') {
		var refreshInterval = fwaum_dashboard.refresh_interval * 1000;
		
		function refreshDashboardWidget() {
			$.post(ajaxurl, {
				action: 'fwaum_get_online_users',
				nonce: fwaum_dashboard.nonce
			}, function(response) {
				if (response.success) {
					// Update timestamp.
					var now = new Date();
					$('.fwaum-timestamp').text(now.toLocaleTimeString());
					
					// Update count.
					$('.fwaum-big-number').text(response.data.total);
					
					// Could also update the user list here if needed.
				}
			});
		}
		
		// Set up auto-refresh.
		if (refreshInterval > 0) {
			setInterval(refreshDashboardWidget, refreshInterval);
		}
	}
});