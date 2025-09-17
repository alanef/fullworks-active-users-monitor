<?php
/**
 * Core audit logger functionality
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
 * Audit Logger class
 */
class Audit_Logger {

	/**
	 * Session start time storage
	 *
	 * @var array
	 */
	private static $session_start_times = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into WordPress authentication events.
		add_action( 'wp_login', array( $this, 'log_login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'log_logout' ) );
		add_action( 'wp_login_failed', array( $this, 'log_failed_login' ) );
		add_action( 'auth_cookie_expired', array( $this, 'log_session_expired' ) );

		// Schedule cleanup cron job.
		add_action( 'fwaum_cleanup_audit_logs', array( $this, 'cleanup_old_logs' ) );
		if ( ! wp_next_scheduled( 'fwaum_cleanup_audit_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'fwaum_cleanup_audit_logs' );
		}
	}

	/**
	 * Log user login event
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user WP_User object.
	 */
	public function log_login( $user_login, $user ) {
		$this->log_event(
			$user->ID,
			$user_login,
			$user->display_name,
			'login',
			array(
				'login_method' => $this->detect_login_method(),
			)
		);

		// Store session start time for duration calculation.
		self::$session_start_times[ $user->ID ] = time();
		update_user_meta( $user->ID, 'fwaum_session_start', time() );
	}

	/**
	 * Log user logout event
	 */
	public function log_logout() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Calculate session duration.
		$session_duration = null;
		$session_start    = get_user_meta( $user_id, 'fwaum_session_start', true );
		if ( $session_start ) {
			$session_duration = time() - intval( $session_start );
			delete_user_meta( $user_id, 'fwaum_session_start' );
		} elseif ( isset( self::$session_start_times[ $user_id ] ) ) {
			$session_duration = time() - self::$session_start_times[ $user_id ];
			unset( self::$session_start_times[ $user_id ] );
		}

		$this->log_event(
			$user->ID,
			$user->user_login,
			$user->display_name,
			'logout',
			array(),
			$session_duration
		);
	}

	/**
	 * Log failed login attempt
	 *
	 * @param string $username Username that failed login.
	 */
	public function log_failed_login( $username ) {
		// Get user ID if username exists.
		$user         = get_user_by( 'login', $username );
		$user_id      = $user ? $user->ID : 0;
		$display_name = $user ? $user->display_name : $username;

		$this->log_event(
			$user_id,
			$username,
			$display_name,
			'failed_login',
			array(
				'attempted_username' => $username,
				'user_exists'        => $user ? true : false,
			)
		);
	}

	/**
	 * Log session expiration
	 *
	 * @param string $token The expired token.
	 */
	public function log_session_expired( $token ) {
		// This is harder to track to a specific user, but we'll do our best.
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->log_event(
			$user->ID,
			$user->user_login,
			$user->display_name,
			'session_expired',
			array(
				'token_hash' => substr( hash( 'sha256', $token ), 0, 8 ),
			)
		);
	}

	/**
	 * Log an event to the audit trail
	 *
	 * @param int    $user_id        User ID.
	 * @param string $username       Username.
	 * @param string $display_name   Display name.
	 * @param string $event_type     Event type.
	 * @param array  $additional_data Additional data for the event.
	 * @param int    $session_duration Session duration in seconds.
	 */
	private function log_event( $user_id, $username, $display_name, $event_type, $additional_data = array(), $session_duration = null ) {
		// Check if audit logging is enabled.
		$options = get_option( 'fwaum_settings', array() );
		if ( ! isset( $options['enable_audit_log'] ) || ! $options['enable_audit_log'] ) {
			return;
		}

		global $wpdb;
		$table_name = Audit_Installer::get_table_name();

		$ip_address = $this->get_client_ip();
		$user_agent = $this->get_user_agent();

		// Prepare data for insertion.
		$data = array(
			'user_id'          => intval( $user_id ),
			'username'         => sanitize_text_field( $username ),
			'display_name'     => sanitize_text_field( $display_name ),
			'event_type'       => $event_type,
			'timestamp'        => current_time( 'mysql' ),
			'ip_address'       => sanitize_text_field( $ip_address ),
			'user_agent'       => sanitize_text_field( $user_agent ),
			'login_method'     => isset( $additional_data['login_method'] ) ? sanitize_text_field( $additional_data['login_method'] ) : 'standard',
			'session_duration' => $session_duration,
			'additional_data'  => wp_json_encode( $additional_data ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		$wpdb->insert( $table_name, $data, $formats );

		// Fire action for extensibility.
		do_action( 'fwaum_audit_event_logged', $data, $wpdb->insert_id );
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip() {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs (from proxies).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				// Validate IP.
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Get user agent
	 *
	 * @return string User agent string.
	 */
	private function get_user_agent() {
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 );
		}
		return 'Unknown';
	}

	/**
	 * Detect login method
	 *
	 * @return string Login method.
	 */
	private function detect_login_method() {
		// Check for various login methods.
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return 'xmlrpc';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest_api';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp_cli';
		}

		// Check for common social login plugins.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- Just detecting login method.
		if ( isset( $_GET['loginSocial'] ) || isset( $_POST['loginSocial'] ) ) {
			return 'social';
		}

		// Check for two-factor authentication.
		if ( class_exists( 'Two_Factor_Core' ) ) {
			return 'two_factor';
		}

		return 'standard';
	}

	/**
	 * Get audit log entries with filtering and pagination
	 *
	 * @param array $args Query arguments.
	 * @return array Results with entries and total count.
	 */
	public static function get_audit_entries( $args = array() ) {
		global $wpdb;
		$table_name = Audit_Installer::get_table_name();

		$defaults = array(
			'per_page'   => 20,
			'page'       => 1,
			'orderby'    => 'timestamp',
			'order'      => 'DESC',
			'user_id'    => null,
			'event_type' => null,
			'date_from'  => null,
			'date_to'    => null,
			'search'     => null,
			'ip_address' => null,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause.
		$where_conditions = array( '1=1' );
		$where_values     = array();

		if ( $args['user_id'] ) {
			$where_conditions[] = 'user_id = %d';
			$where_values[]     = intval( $args['user_id'] );
		}

		if ( $args['event_type'] ) {
			$where_conditions[] = 'event_type = %s';
			$where_values[]     = $args['event_type'];
		}

		if ( $args['date_from'] ) {
			$where_conditions[] = 'timestamp >= %s';
			$where_values[]     = $args['date_from'] . ' 00:00:00';
		}

		if ( $args['date_to'] ) {
			$where_conditions[] = 'timestamp <= %s';
			$where_values[]     = $args['date_to'] . ' 23:59:59';
		}

		if ( $args['search'] ) {
			$where_conditions[] = '(username LIKE %s OR display_name LIKE %s OR ip_address LIKE %s)';
			$search_term        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[]     = $search_term;
			$where_values[]     = $search_term;
			$where_values[]     = $search_term;
		}

		if ( $args['ip_address'] ) {
			$where_conditions[] = 'ip_address = %s';
			$where_values[]     = $args['ip_address'];
		}

		$where_clause = implode( ' AND ', $where_conditions );

		// Build ORDER BY clause.
		$allowed_orderby = array( 'id', 'user_id', 'username', 'event_type', 'timestamp', 'ip_address' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'timestamp';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Calculate LIMIT and OFFSET.
		$per_page = max( 1, intval( $args['per_page'] ) );
		$page     = max( 1, intval( $args['page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Get total count.
		$count_query = "SELECT COUNT(*) FROM %i WHERE $where_clause";
		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared in the next line.
			$count_query = $wpdb->prepare( $count_query, array_merge( array( $table_name ), $where_values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared in the next line.
			$count_query = $wpdb->prepare( $count_query, $table_name );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query was prepared above.
		$total_items = intval( $wpdb->get_var( $count_query ) );

		// Get entries.
		$query        = "SELECT * FROM %i WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$query_values = array_merge( array( $table_name ), $where_values, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with all values.
		$results = $wpdb->get_results( $wpdb->prepare( $query, $query_values ) );

		return array(
			'entries'     => $results,
			'total_items' => $total_items,
			'total_pages' => ceil( $total_items / $per_page ),
		);
	}

	/**
	 * Get audit statistics
	 *
	 * @param string $period Period for stats (today, week, month, year).
	 * @return array Statistics array.
	 */
	public static function get_audit_stats( $period = 'today' ) {
		global $wpdb;
		$table_name = Audit_Installer::get_table_name();

		// Define date ranges.
		$date_ranges = array(
			'today' => array(
				'start' => current_time( 'Y-m-d 00:00:00' ),
				'end'   => current_time( 'Y-m-d 23:59:59' ),
			),
			'week'  => array(
				'start' => gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) ),
				'end'   => current_time( 'Y-m-d 23:59:59' ),
			),
			'month' => array(
				'start' => gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days' ) ),
				'end'   => current_time( 'Y-m-d 23:59:59' ),
			),
			'year'  => array(
				'start' => gmdate( 'Y-m-d 00:00:00', strtotime( '-365 days' ) ),
				'end'   => current_time( 'Y-m-d 23:59:59' ),
			),
		);

		if ( ! isset( $date_ranges[ $period ] ) ) {
			$period = 'today';
		}

		$start_date = $date_ranges[ $period ]['start'];
		$end_date   = $date_ranges[ $period ]['end'];

		// Get event counts by type.
		$event_counts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT event_type, COUNT(*) as count
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				GROUP BY event_type',
				$table_name,
				$start_date,
				$end_date
			)
		);

		$stats = array(
			'login'           => 0,
			'logout'          => 0,
			'failed_login'    => 0,
			'session_expired' => 0,
			'total'           => 0,
		);

		foreach ( $event_counts as $count ) {
			$stats[ $count->event_type ] = intval( $count->count );
			$stats['total']             += intval( $count->count );
		}

		// Get unique users count.
		$unique_users = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT user_id)
				FROM %i
				WHERE timestamp BETWEEN %s AND %s
				AND user_id > 0',
				$table_name,
				$start_date,
				$end_date
			)
		);

		$stats['unique_users'] = intval( $unique_users );

		return $stats;
	}

	/**
	 * Clean up old log entries based on retention settings
	 */
	public function cleanup_old_logs() {
		$options        = get_option( 'fwaum_settings', array() );
		$retention_days = isset( $options['audit_retention_days'] ) ? intval( $options['audit_retention_days'] ) : 90;

		if ( $retention_days > 0 ) {
			Audit_Installer::cleanup_old_entries( $retention_days );
		}
	}

	/**
	 * Export audit entries to CSV
	 *
	 * @param array $args Query arguments.
	 * @return string CSV content.
	 */
	public static function export_to_csv( $args = array() ) {
		$args['per_page'] = 10000; // Large number for export.
		$results          = self::get_audit_entries( $args );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp is a memory stream, not filesystem.
		$output = fopen( 'php://temp', 'r+' );

		// Write CSV header.
		fputcsv(
			$output,
			array(
				'ID',
				'User ID',
				'Username',
				'Display Name',
				'Event Type',
				'Timestamp',
				'IP Address',
				'User Agent',
				'Login Method',
				'Session Duration',
				'Additional Data',
			)
		);

		// Write data rows.
		foreach ( $results['entries'] as $entry ) {
			fputcsv(
				$output,
				array(
					$entry->id,
					$entry->user_id,
					$entry->username,
					$entry->display_name,
					$entry->event_type,
					$entry->timestamp,
					$entry->ip_address,
					$entry->user_agent,
					$entry->login_method,
					$entry->session_duration ? gmdate( 'H:i:s', $entry->session_duration ) : '',
					$entry->additional_data,
				)
			);
		}

		rewind( $output );
		$csv_content = stream_get_contents( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing memory stream, not filesystem.
		fclose( $output );

		return $csv_content;
	}
}
