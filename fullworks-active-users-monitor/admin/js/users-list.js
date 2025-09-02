jQuery(document).ready(function($) {
	// Handle online badge addition for users
	if (typeof fwaum_users_list !== 'undefined' && fwaum_users_list.online_users) {
		fwaum_users_list.online_users.forEach(function(userData) {
			var row = $("#user-" + userData.user_id);
			var usernameCell = row.find("td.username");
			if (!usernameCell.find(".fwaum-online-badge").length) {
				usernameCell.find("strong").addClass("fwaum-user-online fwaum-role-" + userData.user_role);
				usernameCell.find("strong a").after('<span class="fwaum-online-badge">' + userData.badge_text + '</span>');
				row.addClass("fwaum-row-online");
			}
		});
	}
});