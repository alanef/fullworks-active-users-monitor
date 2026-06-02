/**
 * Admin JavaScript for Active Users Monitor
 */

(function($) {
	'use strict';

	// Store interval ID for cleanup.
	var refreshInterval = null;
	var isUpdating = false;

	// Access localized data globally within the script's scope.
	const fwaumAjaxData = typeof fwaumAjax !== 'undefined' ? fwaumAjax : {};
	const enableTrueActivity = fwaumAjaxData.enable_true_activity || false;
	const strings = fwaumAjaxData.strings || {};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		console.debug('[FWAUM] Initializing Active Users Monitor');
		console.debug('[FWAUM] Ajax URL:', fwaumAjaxData.ajaxUrl);
		console.debug('[FWAUM] Refresh Interval:', fwaumAjaxData.refreshInterval);
		console.debug('[FWAUM] Nonce:', fwaumAjaxData.nonce);
		console.debug('[FWAUM] Enable True Activity:', enableTrueActivity);
		
		// Initialize components.
		initAdminBar();
		initUsersList();
		initDashboard();
		
		// Start auto-refresh if configured.
		if (fwaumAjaxData.refreshInterval > 0) {
			console.debug('[FWAUM] Starting auto-refresh with interval:', fwaumAjaxData.refreshInterval);
			startAutoRefresh();
		} else {
			console.debug('[FWAUM] Auto-refresh disabled (interval is 0 or not set)');
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
			var refreshBtn = '<button type="button" class="button button-small fwaum-refresh-btn" style="margin-left: 10px;">Refresh Now</button>';
			$statsNotice.find('p').append(refreshBtn);
			
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
		}, fwaumAjaxData.refreshInterval);
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
		$.post(fwaumAjaxData.ajaxUrl, {
			action: 'fwaum_update_admin_bar',
			nonce: fwaumAjaxData.nonce
		})
		.done(function(response) {
			if (response.success) {
				updateAdminBarDisplay(response.data);
			}
		})
		.fail(function() {
			console.debug('Failed to update admin bar');
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
		var $label = $('#wp-admin-bar-fwaum-online-users .fwaum-admin-bar-text'); // Get the text span.

		if (!$counter.length || !$label.length) {
			return;
		}

		var oldCount = parseInt($counter.text());
		var newCount = enableTrueActivity ? data.active_total : data.total;
		var newLabelText = enableTrueActivity ? (newCount === 1 ? strings.activeUserOnline : strings.activeUsersOnline) : (newCount === 1 ? strings.userOnline : strings.usersOnline);


		// Update count with animation if changed.
		if (oldCount !== newCount) {
			$counter.addClass('fwaum-count-changed');
			$counter.text(newCount);
			
			setTimeout(function() {
				$counter.removeClass('fwaum-count-changed');
			}, 1000);
		}
		
		// Update the entire label text, preserving icon.
		$label.html(`👥 ${newLabelText}: <span class="fwaum-online-count">${newCount}</span>`);

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
		$('.fwaum-refresh-btn').prop('disabled', true).text('Refreshing...');

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
			} else {
				console.error('Failed to refresh users list:', response.data);
			}
		})
		.fail(function() {
			console.error('AJAX request failed');
		})
		.always(function() {
			$('.wp-list-table').removeClass('fwaum-loading');
			$('.fwaum-refresh-btn').prop('disabled', false).text('Refresh Now');
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

		let statusClass = 'offline';
		let statusDot = '&#x25CB;'; // Hollow circle.
		let statusText = strings.offline;
		let displayTime = userData.last_seen;

		if (enableTrueActivity && userData.is_truly_active) {
			statusClass = 'active';
			statusDot = '&#x25CF;'; // Solid circle.
			statusText = strings.active;
			displayTime = strings.activeNow;
		} else if (userData.is_online) {
			statusClass = 'online';
			statusDot = '&#x25CF;'; // Solid circle.
			statusText = strings.online;
			displayTime = strings.onlineNow;
		}

		// Update status indicator.
		$statusCell.removeClass('fwaum-status-active fwaum-status-online fwaum-status-offline')
			.addClass(`fwaum-status-${statusClass}`);
		$statusCell.find('.fwaum-status-dot').html(statusDot);
		$statusCell.find('.fwaum-status-text').text(statusText);

		// Update or add last seen.
		var $lastSeen = $statusCell.find('.fwaum-last-seen');
		if ($lastSeen.length) {
			if ('active' === statusClass || 'online' === statusClass) {
				$lastSeen.hide(); // Hide if active or online.
			} else {
				$lastSeen.text(displayTime).show();
			}
		} else if (!('active' === statusClass || 'online' === statusClass)) {
			// Only add if not active/online and not already present.
			$statusCell.append('<span class="fwaum-last-seen">' + displayTime + '</span>');
		}

		// Update row and username styling.
		if ('active' === statusClass || 'online' === statusClass) {
			$row.addClass('fwaum-row-online');
			$usernameCell.addClass('fwaum-user-online fwaum-role-' + userData.role);
		} else {
			$row.removeClass('fwaum-row-online');
			$usernameCell.removeClass('fwaum-user-online fwaum-role-' + userData.role);
		}

		// Remove the old online badge if it exists.
		$usernameCell.find('.fwaum-online-badge').remove();
	}

	/**
	 * Update stats summary
	 */
	function updateStatsSummary(data) {
		var $summary = $('.fwaum-stats-summary');
		if (!$summary.length) {
			return;
		}

		// Build role summary text.
		var roleSummary = [];
		if (data.role_counts) {
			for (var role in data.role_counts) {
				if (data.role_counts[role] > 0) {
					// Assuming role.name is already translated for display.
					let roleName = role.charAt(0).toUpperCase() + role.slice(1); 
					// Find the matching name from localized roles if available, fallback to basic capitalization
					const foundRole = fwaum_users_list.role_strings ? fwaum_users_list.role_strings.find(r => r.role === role) : null;
					if (foundRole) {
						roleName = foundRole.name;
					}
					roleSummary.push(data.role_counts[role] + ' ' + roleName);
				}
			}
		}

		// Update summary text.
		let summaryText = '';
		if (enableTrueActivity) {
			summaryText = `${data.total_online} users online (${data.active_total} active)`;
		} else {
			summaryText = `${data.total_online} users online`;
		}
		
		if (roleSummary.length > 0) {
			summaryText += ' (' + roleSummary.join(', ') + ')';
		}
		
		$summary.text(summaryText);
	}

	/**
	 * Update filter link counts
	 */
	function updateFilterCounts(data) {
		console.debug('[FWAUM] Updating filter counts - Online:', data.total_online, 'Offline:', data.total_offline);
		
		// Update Online filter count.
		var $onlineFilter = $('.subsubsub a[href*="fwaum_filter=online"] .count');
		if ($onlineFilter.length) {
			let onlineCountToDisplay = enableTrueActivity ? data.active_total : data.total_online;
			$onlineFilter.text('(' + onlineCountToDisplay + ')');
			console.debug('[FWAUM] Updated online filter count');
		}
		
		// Update Offline filter count.
		var $offlineFilter = $('.subsubsub a[href*="fwaum_filter=offline"] .count');
		if ($offlineFilter.length) {
			$offlineFilter.text('(' + data.total_offline + ')');
			console.debug('[FWAUM] Updated offline filter count');
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