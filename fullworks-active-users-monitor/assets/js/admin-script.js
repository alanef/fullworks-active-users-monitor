/**
 * Admin JavaScript for Active Users Monitor
 */

(function($) {
	'use strict';

	// Store interval ID for cleanup.
	var refreshInterval = null;
	var isUpdating = false;

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		// Initialize components.
		initAdminBar();
		initUsersList();
		initDashboard();
		
		// Start auto-refresh if configured.
		if (fwaumAjax.refreshInterval > 0) {
			startAutoRefresh();
		}
	});

	/**
	 * Initialize admin bar functionality
	 */
	function initAdminBar() {
		// Only run if admin bar exists.
		if (!$('#wpadminbar').length) {
			return;
		}

		// Update admin bar immediately on page load.
		updateAdminBar();
	}

	/**
	 * Initialize users list functionality
	 */
	function initUsersList() {
		// Only run on users.php page.
		if (!$('body.users-php').length) {
			return;
		}

		// Add refresh button to stats notice.
		var $statsNotice = $('.fwaum-stats-notice');
		if ($statsNotice.length) {
			var $refreshBtn = $('<button type="button" class="button button-small fwaum-refresh-btn">')
				.text(fwaumAjax.strings.refreshNow);
			$statsNotice.find('p').append($refreshBtn);
			
			// Handle refresh button click.
			$('.fwaum-refresh-btn').on('click', function() {
				refreshUsersList();
			});
		}
	}

	/**
	 * Initialize dashboard widget functionality
	 */
	function initDashboard() {
		// Only run on dashboard.
		if (!$('body.index-php').length) {
			return;
		}

		// Dashboard widget is already initialized with inline script.
	}

	/**
	 * Start auto-refresh timer
	 */
	function startAutoRefresh() {
		// Clear any existing interval.
		if (refreshInterval) {
			clearInterval(refreshInterval);
		}

		// Set up new interval.
		refreshInterval = setInterval(function() {
			// Update different components based on current page.
			if ($('body.users-php').length) {
				refreshUsersList();
			}
			
			// Always update admin bar if visible.
			updateAdminBar();
		}, fwaumAjax.refreshInterval);
	}

	/**
	 * Update admin bar counter
	 */
	function updateAdminBar() {
		// Check if admin bar item exists.
		var $adminBarItem = $('#wp-admin-bar-fwaum-online-users');
		if (!$adminBarItem.length) {
			return;
		}

		// Don't update if already updating.
		if ($adminBarItem.hasClass('fwaum-admin-bar-loading')) {
			return;
		}

		// Add loading class.
		$adminBarItem.addClass('fwaum-admin-bar-loading');

		// Make AJAX request.
		$.post(fwaumAjax.ajaxUrl, {
			action: 'fwaum_update_admin_bar',
			nonce: fwaumAjax.nonce
		})
		.done(function(response) {
			if (response.success) {
				updateAdminBarDisplay(response.data);
			}
		})
		.always(function() {
			$adminBarItem.removeClass('fwaum-admin-bar-loading');
		});
	}

	/**
	 * Update admin bar display with new data
	 */
	function updateAdminBarDisplay(data) {
		var $counter = $('#wp-admin-bar-fwaum-online-users .fwaum-online-count');
		if (!$counter.length) {
			return;
		}

		var oldCount = parseInt($counter.text());
		var newCount = data.total;

		// Update count with animation if changed.
		if (oldCount !== newCount) {
			$counter.addClass('fwaum-count-changed');
			$counter.text(newCount);
			
			setTimeout(function() {
				$counter.removeClass('fwaum-count-changed');
			}, 1000);
		}

		// Update role breakdown in dropdown.
		if (data.roles && data.roles.length > 0) {
			data.roles.forEach(function(role) {
				var $roleItem = $('#wp-admin-bar-fwaum-role-' + role.role);
				if ($roleItem.length) {
					$roleItem.find('.fwaum-role-count').text(role.count + ' ' + role.name);
				}
			});
		}
	}

	/**
	 * Refresh users list table
	 */
	function refreshUsersList() {
		// Don't refresh if already updating.
		if (isUpdating) {
			return;
		}

		isUpdating = true;

		// Get current page info.
		var currentPage = 1;
		var perPage = 20;
		
		// Try to get page info and filter from URL.
		var urlParams = new URLSearchParams(window.location.search);
		if (urlParams.has('paged')) {
			currentPage = parseInt(urlParams.get('paged'));
		}
		var filter = urlParams.get('fwaum_filter') || '';

		// Show loading state.
		$('.wp-list-table').addClass('fwaum-loading');
		$('.fwaum-refresh-btn').prop('disabled', true).text(fwaumAjax.strings.refreshing);

		// Make AJAX request.
		$.post(fwaumAjax.ajaxUrl, {
			action: 'fwaum_refresh_users_list',
			nonce: fwaumAjax.nonce,
			page: currentPage,
			per_page: perPage,
			filter: filter
		})
		.done(function(response) {
			if (response.success) {
				updateUsersListDisplay(response.data);
			}
		})
		.always(function() {
			$('.wp-list-table').removeClass('fwaum-loading');
			$('.fwaum-refresh-btn').prop('disabled', false).text(fwaumAjax.strings.refreshNow);
			isUpdating = false;
		});
	}

	/**
	 * Update users list display with new data
	 */
	function updateUsersListDisplay(data) {
		// Update each user row.
		if (data.users && data.users.length > 0) {
			data.users.forEach(function(user) {
				updateUserRow(user);
			});
		}

		// Update stats summary.
		updateStatsSummary(data);
		
		// Update filter link counts.
		updateFilterCounts(data);

		// Update timestamp.
		var now = new Date();
		$('.fwaum-update-time').text(now.toLocaleTimeString());
	}

	/**
	 * Update individual user row
	 */
	function updateUserRow(userData) {
		var $row = $('#user-' + userData.user_id);
		if (!$row.length) {
			return;
		}

		var $statusCell = $row.find('.fwaum-status-indicator');
		var $usernameCell = $row.find('td.username strong');

		if (userData.is_online) {
			// Update status indicator.
			$statusCell.removeClass('fwaum-status-offline').addClass('fwaum-status-online');
			$statusCell.find('.fwaum-status-dot').text('●');
			$statusCell.find('.fwaum-status-text').text(fwaumAjax.strings.online);
			$statusCell.find('.fwaum-last-seen').remove();

			// Add online styling.
			$row.addClass('fwaum-row-online');
			$usernameCell.addClass('fwaum-user-online fwaum-role-' + userData.role);

			// Add online badge if not exists.
			if (!$usernameCell.find('.fwaum-online-badge').length) {
				$usernameCell.find('a').after($('<span class="fwaum-online-badge">').text(fwaumAjax.strings.onlineBadge));
			}
		} else {
			// Update status indicator.
			$statusCell.removeClass('fwaum-status-online').addClass('fwaum-status-offline');
			$statusCell.find('.fwaum-status-dot').text('○');
			$statusCell.find('.fwaum-status-text').text(fwaumAjax.strings.offline);

			// Update or add last seen.
			var $lastSeen = $statusCell.find('.fwaum-last-seen');
			if ($lastSeen.length) {
				$lastSeen.text(userData.last_seen);
			} else {
				$statusCell.append($('<span class="fwaum-last-seen">').text(userData.last_seen));
			}

			// Remove online styling.
			$row.removeClass('fwaum-row-online');
			$usernameCell.removeClass('fwaum-user-online fwaum-role-' + userData.role);
			$usernameCell.find('.fwaum-online-badge').remove();
		}
	}

	/**
	 * Update stats summary
	 */
	function updateStatsSummary(data) {
		// The server builds the translated summary so it matches the page render.
		$('.fwaum-stats-summary').text(data.summary);
	}

	/**
	 * Update filter link counts
	 */
	function updateFilterCounts(data) {
		// Update Online filter count.
		var $onlineFilter = $('.subsubsub a[href*="fwaum_filter=online"] .count');
		if ($onlineFilter.length) {
			$onlineFilter.text('(' + data.total_online + ')');
		}

		// Update Offline filter count.
		var $offlineFilter = $('.subsubsub a[href*="fwaum_filter=offline"] .count');
		if ($offlineFilter.length) {
			$offlineFilter.text('(' + data.total_offline + ')');
		}
	}

	/**
	 * Handle visibility change to pause/resume updates
	 */
	document.addEventListener('visibilitychange', function() {
		if (document.hidden) {
			// Page is hidden, pause updates.
			if (refreshInterval) {
				clearInterval(refreshInterval);
				refreshInterval = null;
			}
		} else {
			// Page is visible again, resume updates.
			if (fwaumAjax.refreshInterval > 0 && !refreshInterval) {
				startAutoRefresh();
				// Do immediate update.
				updateAdminBar();
				if ($('body.users-php').length) {
					refreshUsersList();
				}
			}
		}
	});

	/**
	 * Clean up on page unload
	 */
	$(window).on('beforeunload', function() {
		if (refreshInterval) {
			clearInterval(refreshInterval);
		}
	});

})(jQuery);