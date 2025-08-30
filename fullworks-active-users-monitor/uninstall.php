<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://fullworks.net/
 * @since      1.0.0
 *
 * @package    FullworksActiveUsersMonitor
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall
 */
function fwaum_uninstall_cleanup() {
	// Delete plugin options.
	delete_option( 'fwaum_settings' );

	// Delete plugin transients.
	delete_transient( 'fwaum_online_users_cache' );

	// Delete user meta.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'fwaum_last_login'" );

	// Clear any cached data.
	wp_cache_flush();
}

// Check if it's a multisite installation.
if ( is_multisite() ) {
	// Get all sites in the network.
	$fwaum_sites = get_sites();

	foreach ( $fwaum_sites as $fwaum_site ) {
		// Switch to each site.
		switch_to_blog( $fwaum_site->blog_id );

		// Run cleanup for this site.
		fwaum_uninstall_cleanup();

		// Restore original site.
		restore_current_blog();
	}

	// Also delete any network-wide options if they exist.
	delete_site_option( 'fwaum_network_settings' );
} else {
	// Single site installation.
	fwaum_uninstall_cleanup();
}
