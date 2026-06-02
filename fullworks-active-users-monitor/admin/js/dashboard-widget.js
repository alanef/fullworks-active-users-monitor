jQuery(document).ready(function($) {
	// Auto-refresh dashboard widget.
	if (typeof fwaum_dashboard !== 'undefined') {
		var refreshInterval = fwaum_dashboard.refresh_interval * 1000;
		const enableTrueActivity = fwaum_dashboard.enable_true_activity;
		const strings = fwaum_dashboard.strings;
		
		function refreshDashboardWidget() {
			$.post(ajaxurl, {
				action: 'fwaum_get_online_users',
				nonce: fwaum_dashboard.nonce
			}, function(response) {
				if (response.success) {
					// Update timestamp.
					var now = new Date();
					$('.fwaum-timestamp').text(now.toLocaleTimeString());
					
					let displayCount = response.data.total;
					let displayLabel = strings.usersOnline;
					let usersToDisplay = response.data.users; // All online users
					
					if (enableTrueActivity) {
						displayCount = response.data.active_total;
						displayLabel = strings.activeUsersOnline;
						// Filter users to display only truly active ones if true activity is enabled.
						usersToDisplay = response.data.users.filter(user => user.is_truly_active);
					}

					// Update count.
					$('.fwaum-big-number').text(displayCount);
					// Update label, using a simple conditional for pluralization in JS.
					$('.fwaum-label').text(displayCount === 1 ? displayLabel.replace(/Users/, 'User') : displayLabel);

					// Update Recently Active users.
					const $userList = $('.fwaum-user-list');
					$userList.empty(); // Clear existing list.
					usersToDisplay.slice(0, 5).forEach(function(user) { // Show up to 5 users.
						const userHtml = `
							<li class="fwaum-user-item">
								<img alt="" src="${user.avatar_url}" srcset="${user.avatar_url} 2x" class="avatar avatar-24 photo" height="24" width="24" loading="lazy" decoding="async">
								<div class="fwaum-user-info">
									<a href="${user.profile_url}">
										${user.display_name}
									</a>
									<span class="fwaum-user-role">
										${user.roles.map(role => role.charAt(0).toUpperCase() + role.slice(1)).join(', ')}
									</span>
								</div>
								<span class="fwaum-online-indicator" title="${user.is_truly_active ? strings.activeNow : strings.onlineNow}">●</span>
							</li>`;
						$userList.append(userHtml);
					});

					// Update Role Breakdown.
					const $roleList = $('.fwaum-role-list');
					$roleList.empty();
					// Ensure consistent sorting with PHP.
					const roleOrder = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];
					
					const sortedRoleCounts = response.data.role_counts.sort((a, b) => {
						const indexA = roleOrder.indexOf(a.role);
						const indexB = roleOrder.indexOf(b.role);
						return (indexA === -1 ? 999 : indexA) - (indexB === -1 ? 999 : indexB);
					});

					sortedRoleCounts.forEach(function(roleData) {
						if (roleData.count > 0) {
							const roleHtml = `
								<li class="fwaum-role-item">
									<span class="fwaum-role-indicator fwaum-role-${roleData.role}"></span>
									<span class="fwaum-role-name">${roleData.name}:</span>
									<span class="fwaum-role-count">${roleData.count}</span>
								</li>`;
							$roleList.append(roleHtml);
						}
					});
				}
			});
		}
		
		// Set up auto-refresh.
		if (refreshInterval > 0) {
			setInterval(refreshDashboardWidget, refreshInterval);
		}
	}
});