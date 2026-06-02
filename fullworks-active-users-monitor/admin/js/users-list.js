jQuery(document).ready(function($) {
	if (typeof fwaum_users_list === 'undefined') {
		return;
	}

	const enableTrueActivity = fwaum_users_list.enable_true_activity;
	const strings = fwaum_users_list.strings;

	/**
	 * Updates the status indicator for a single user.
	 *
	 * @param {object} userData User data including status information.
	 */
	function updateStatusIndicator(userData) {
		const $statusIndicator = $(`.fwaum-status-indicator[data-user-id="${userData.user_id}"]`);
		if ($statusIndicator.length === 0) {
			return;
		}

		let statusClass = 'offline';
		let statusIcon = '&#x25CB;'; // Hollow circle.
		let statusText = strings.offline;
		let displayTime = userData.formatted_last_seen;

		if (enableTrueActivity && userData.is_truly_active) {
			statusClass = 'active';
			statusIcon = '&#x25CF;'; // Solid circle.
			statusText = strings.active;
			displayTime = strings.active_now;
		} else if (userData.is_online) {
			statusClass = 'online';
			statusIcon = '&#x25CF;'; // Solid circle.
			statusText = strings.online;
			displayTime = strings.online_now;
		}

		$statusIndicator.removeClass('fwaum-status-active fwaum-status-online fwaum-status-offline')
			.addClass(`fwaum-status-${statusClass}`);
		$statusIndicator.find('.fwaum-status-dot').html(statusIcon);
		$statusIndicator.find('.fwaum-status-text').text(statusText);

		const $lastSeenSpan = $statusIndicator.find('.fwaum-last-seen');
		if ($lastSeenSpan.length > 0) {
			if ('active' === statusClass || 'online' === statusClass) {
				$lastSeenSpan.hide();
			} else {
				$lastSeenSpan.attr('title', displayTime).text(displayTime).show();
			}
		}
	}

	// Initial update of all user status indicators on page load.
	if (fwaum_users_list.all_users_status) {
		fwaum_users_list.all_users_status.forEach(function(userData) {
			updateStatusIndicator(userData);
		});
	}
});