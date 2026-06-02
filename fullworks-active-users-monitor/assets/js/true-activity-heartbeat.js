/**
 * JavaScript for Fullworks Active Users Monitor True Activity Heartbeat.
 *
 * @package FullworksActiveUsersMonitor
 */

(function ($) {
	if (typeof fwaumHeartbeat === 'undefined') {
		return;
	}

	let lastActivityTime = new Date().getTime(); // Timestamp of last detected activity.
	let lastHeartbeatTime = 0; // Timestamp of last sent heartbeat.
	const heartbeatFrequency = fwaumHeartbeat.heartbeatFrequency * 1000; // Convert to milliseconds.
	const ajaxUrl = fwaumHeartbeat.ajaxUrl;
	const nonce = fwaumHeartbeat.nonce;
	const action = 'fwaum_true_activity_heartbeat';

	/**
	 * Sends an AJAX heartbeat to the server.
	 */
	function sendHeartbeat() {
		const currentTime = new Date().getTime();
		// Only send if heartbeat frequency has passed since last successful heartbeat.
		if (currentTime - lastHeartbeatTime < heartbeatFrequency) {
			return;
		}

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: action,
				nonce: nonce,
			},
			success: function (response) {
				if (response.success) {
					lastHeartbeatTime = new Date().getTime(); // Update last heartbeat time on success.
				}
				// Optionally, handle errors or log for debugging.
			},
			error: function (xhr, status, error) {
				// console.error('FWAUM Heartbeat Error:', status, error);
			},
		});
	}

	/**
	 * Records user activity.
	 */
	function recordActivity() {
		lastActivityTime = new Date().getTime();
	}

	/**
	 * Checks for activity and sends heartbeat if needed.
	 */
	function checkAndSendHeartbeat() {
		const currentTime = new Date().getTime();
		// If there has been activity recently (within 2x heartbeat frequency) and it's time for a heartbeat, send it.
		// The 2x threshold ensures that even if activity is sporadic, we still try to send a heartbeat.
		if (currentTime - lastActivityTime < heartbeatFrequency * 2 && currentTime - lastHeartbeatTime >= heartbeatFrequency) {
			sendHeartbeat();
		}
	}

	// Attach activity listeners.
	$(document).on('mousemove keydown scroll click', recordActivity);

	// Start checking for activity and sending heartbeats at a regular interval.
	// This interval can be shorter than the actual heartbeatFrequency to ensure responsiveness.
	setInterval(checkAndSendHeartbeat, heartbeatFrequency / 2);

	// Send an initial heartbeat on page load to mark immediate activity.
	// This ensures the user is marked active right away if they are on a page.
	sendHeartbeat();

})(jQuery);
