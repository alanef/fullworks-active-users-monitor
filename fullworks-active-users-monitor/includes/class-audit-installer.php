<?php
/**
 * Database installer for audit trail functionality
 *
 * @package FullworksActiveUsersMonitor\Includes
 * @since 1.0.2
 */

namespace FullworksActiveUsersMonitor\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit Installer class
 */
class Audit_Installer {

	/**
	 * Table name without prefix
	 *
	 * @var string
	 */
	const TABLE_NAME = 'fwaum_audit_log';

	/**
	 * Database version option name
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'fwaum_audit_db_version';

	/**
	 * Current database version
	 *
	 * @var string
	 */
	const CURRENT_DB_VERSION = '1.0';

	/**
	 * Install or update database tables
	 */
	public static function install() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			username varchar(60) NOT NULL,
			display_name varchar(250) NOT NULL,
			event_type enum('login', 'logout', 'failed_login', 'session_expired') NOT NULL,
			timestamp datetime NOT NULL,
			ip_address varchar(45) NOT NULL,
			user_agent text NOT NULL,
			login_method varchar(50) NOT NULL DEFAULT 'standard',
			session_duration int(11) unsigned NULL,
			additional_data longtext NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY event_type (event_type),
			KEY timestamp (timestamp),
			KEY ip_address (ip_address)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Update the database version.
		update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION );

		// Migrate existing last login data.
		self::migrate_existing_data();
	}

	/**
	 * Check if database needs update
	 *
	 * @return bool True if update needed, false otherwise.
	 */
	public static function needs_update() {
		$current_version = get_option( self::DB_VERSION_OPTION, '0' );
		return version_compare( $current_version, self::CURRENT_DB_VERSION, '<' );
	}

	/**
	 * Get table name with prefix
	 *
	 * @return string Full table name.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Migrate existing fwaum_last_login user meta to audit log
	 */
	private static function migrate_existing_data() {
		global $wpdb;

		// Check if migration already done.
		$migration_flag = get_option( 'fwaum_audit_migration_done', false );
		if ( $migration_flag ) {
			return;
		}

		$table_name = self::get_table_name();

		// Get all users with last login meta.
		$results = $wpdb->get_results(
			"SELECT user_id, meta_value as last_login
			FROM {$wpdb->usermeta}
			WHERE meta_key = 'fwaum_last_login'
			AND meta_value IS NOT NULL
			AND meta_value != ''"
		);

		foreach ( $results as $result ) {
			$user = get_userdata( $result->user_id );
			if ( ! $user ) {
				continue;
			}

			$timestamp = date( 'Y-m-d H:i:s', intval( $result->last_login ) );

			// Insert as a login event.
			$wpdb->insert(
				$table_name,
				array(
					'user_id'         => $user->ID,
					'username'        => $user->user_login,
					'display_name'    => $user->display_name,
					'event_type'      => 'login',
					'timestamp'       => $timestamp,
					'ip_address'      => '0.0.0.0',
					'user_agent'      => 'Migrated from user meta',
					'login_method'    => 'migrated',
					'additional_data' => wp_json_encode( array( 'migrated' => true ) ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		// Mark migration as done.
		update_option( 'fwaum_audit_migration_done', true );
	}

	/**
	 * Clean up old audit log entries based on retention settings
	 *
	 * @param int $retention_days Number of days to retain logs.
	 */
	public static function cleanup_old_entries( $retention_days = 90 ) {
		if ( $retention_days <= 0 ) {
			return; // Indefinite retention.
		}

		global $wpdb;
		$table_name  = self::get_table_name();
		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE timestamp < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Uninstall - remove table and options
	 */
	public static function uninstall() {
		global $wpdb;
		$table_name = self::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
		delete_option( self::DB_VERSION_OPTION );
		delete_option( 'fwaum_audit_migration_done' );
	}

	/**
	 * Get table stats
	 *
	 * @return array Table statistics.
	 */
	public static function get_table_stats() {
		global $wpdb;
		$table_name = self::get_table_name();

		$total_entries = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		$oldest_entry  = $wpdb->get_var( "SELECT timestamp FROM $table_name ORDER BY timestamp ASC LIMIT 1" );
		$newest_entry  = $wpdb->get_var( "SELECT timestamp FROM $table_name ORDER BY timestamp DESC LIMIT 1" );

		return array(
			'total_entries' => intval( $total_entries ),
			'oldest_entry'  => $oldest_entry,
			'newest_entry'  => $newest_entry,
		);
	}
}
