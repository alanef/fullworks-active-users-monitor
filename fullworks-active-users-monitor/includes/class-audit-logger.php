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

		// Daily cleanup; scheduled on activation, cleared on deactivation.
		add_action( 'fwaum_cleanup_audit_logs', array( $this, 'cleanup_old_logs' ) );
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
		$options = get_option( 'fwaum_settings', array() );
		if ( isset( $options['audit_track_failed_logins'] ) && ! $options['audit_track_failed_logins'] ) {
			return;
		}

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
	 * Runs while WordPress is still working out who the current user is, so it
	 * must never call get_current_user_id() or anything else that triggers that
	 * lookup: doing so re-validates the same cookie, fires this hook again and
	 * recurses until the request dies.
	 *
	 * @param array $cookie_elements Parsed auth cookie (username, expiration, token, hmac, scheme).
	 */
	public function log_session_expired( $cookie_elements ) {
		if ( ! is_array( $cookie_elements ) || empty( $cookie_elements['username'] ) || empty( $cookie_elements['token'] ) ) {
			return;
		}

		$user = get_user_by( 'login', $cookie_elements['username'] );
		if ( ! $user || ! $this->is_genuine_cookie( $user, $cookie_elements ) ) {
			return;
		}

		// An open browser resends the expired cookie on every request; log it once.
		$token_hash = hash( 'sha256', $cookie_elements['token'] );
		$seen_key   = 'fwaum_expired_' . substr( $token_hash, 0, 32 );
		if ( get_transient( $seen_key ) ) {
			return;
		}
		set_transient( $seen_key, 1, DAY_IN_SECONDS );

		$this->log_event(
			$user->ID,
			$user->user_login,
			$user->display_name,
			'session_expired',
			array(
				'token_hash' => substr( $token_hash, 0, 8 ),
			)
		);
	}

	/**
	 * Check an expired auth cookie was really issued by this site.
	 *
	 * WordPress checks expiry before the signature, so the auth_cookie_expired
	 * hook also fires for forged cookies. This repeats core's HMAC check from
	 * wp_validate_auth_cookie() so only real sessions are logged.
	 *
	 * @param \WP_User $user            User named in the cookie.
	 * @param array    $cookie_elements Parsed auth cookie.
	 * @return bool
	 */
	private function is_genuine_cookie( $user, $cookie_elements ) {
		if ( ! isset( $cookie_elements['expiration'], $cookie_elements['hmac'], $cookie_elements['scheme'] ) ) {
			return false;
		}

		// Since WordPress 6.8 only phpass and vanilla bcrypt hashes use the old fragment.
		$pass = $user->user_pass;
		if ( version_compare( get_bloginfo( 'version' ), '6.8', '<' ) || str_starts_with( $pass, '$P$' ) || str_starts_with( $pass, '$2y$' ) ) {
			$pass_frag = substr( $pass, 8, 4 );
		} else {
			$pass_frag = substr( $pass, -4 );
		}

		$key  = wp_hash( $cookie_elements['username'] . '|' . $pass_frag . '|' . $cookie_elements['expiration'] . '|' . $cookie_elements['token'], $cookie_elements['scheme'] );
		$hash = hash_hmac( 'sha256', $cookie_elements['username'] . '|' . $cookie_elements['expiration'] . '|' . $cookie_elements['token'], $key );

		return hash_equals( $hash, $cookie_elements['hmac'] );
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
			// Truncate to the column widths: an over-long value would make the insert fail and the event go unlogged.
			'username'         => mb_substr( sanitize_text_field( $username ), 0, 60 ),
			'display_name'     => mb_substr( sanitize_text_field( $display_name ), 0, 250 ),
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
		/**
		 * Filter the $_SERVER keys trusted to hold the client IP, in order of preference.
		 *
		 * Only REMOTE_ADDR is trusted by default. Headers such as X-Forwarded-For are
		 * set by the client and can be forged unless a proxy you control overwrites
		 * them, so add them here only when the site sits behind such a proxy, e.g.
		 * array( 'HTTP_CF_CONNECTING_IP', 'REMOTE_ADDR' ) behind Cloudflare.
		 *
		 * @since 1.2.0
		 *
		 * @param string[] $ip_headers $_SERVER keys to check.
		 */
		$ip_headers = (array) apply_filters( 'fwaum_client_ip_headers', array( 'REMOTE_ADDR' ) );

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs (from proxies).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
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

		$anonymize_days = isset( $options['audit_anonymize_ips_days'] ) ? intval( $options['audit_anonymize_ips_days'] ) : 30;
		if ( $anonymize_days > 0 ) {
			self::anonymize_old_ips( $anonymize_days );
		}
	}

	/**
	 * Anonymize IP addresses on entries older than the given number of days.
	 *
	 * Uses core's wp_privacy_anonymize_ip(): the last octet of an IPv4 address
	 * and the last 80 bits of an IPv6 address are zeroed. Entries already
	 * anonymized are left alone because the value no longer changes.
	 *
	 * @param int $days Anonymize entries older than this many days.
	 */
	public static function anonymize_old_ips( $days ) {
		global $wpdb;
		$table_name = Audit_Installer::get_table_name();
		// Entries are stored in site-local time (current_time( 'mysql' )), so compare in the same zone.
		$cutoff = wp_date( 'Y-m-d H:i:s', time() - ( absint( $days ) * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance query on the plugin's own table.
		$ips = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT ip_address FROM %i WHERE timestamp < %s',
				$table_name,
				$cutoff
			)
		);

		foreach ( $ips as $ip ) {
			$anonymized = wp_privacy_anonymize_ip( $ip );
			if ( $anonymized === $ip ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance query on the plugin's own table.
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET ip_address = %s WHERE ip_address = %s AND timestamp < %s',
					$table_name,
					$anonymized,
					$ip,
					$cutoff
				)
			);
		}
	}
}
