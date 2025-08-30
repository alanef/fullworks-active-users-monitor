<?php
/**
 * User Tracker class for session-based online status detection
 *
 * @package FullworksActiveUsersMonitor\Includes
 * @since 1.0.0
 */

namespace FullworksActiveUsersMonitor\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Tracker class
 */
class User_Tracker {

	/**
	 * Cache transient name
	 *
	 * @var string
	 */
	const CACHE_KEY = 'fwaum_online_users_cache';

	/**
	 * Cache duration in seconds
	 *
	 * @var int
	 */
	const CACHE_DURATION = 30;

	/**
	 * Check if a user is online by checking their session tokens
	 *
	 * @param int $user_id User ID to check.
	 * @return bool True if user is online, false otherwise.
	 */
	public function is_user_online( $user_id ) {
		if ( ! class_exists( 'WP_Session_Tokens' ) ) {
			return false;
		}

		$sessions      = \WP_Session_Tokens::get_instance( $user_id );
		$user_sessions = $sessions->get_all();

		// User is online if they have any active sessions.
		return ! empty( $user_sessions );
	}

	/**
	 * Get all online users
	 *
	 * @param bool $use_cache Whether to use cached results.
	 * @return array Array of online user IDs.
	 */
	public function get_online_users( $use_cache = true ) {
		// Check cache first.
		if ( $use_cache ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$online_users = array();

		// Get all users with the list_users capability to check.
		$users = get_users(
			array(
				'fields' => 'ID',
				'number' => 1000, // Limit for performance.
			)
		);

		foreach ( $users as $user_id ) {
			if ( $this->is_user_online( $user_id ) ) {
				$online_users[] = $user_id;
			}
		}

		// Cache results.
		set_transient( self::CACHE_KEY, $online_users, self::CACHE_DURATION );

		return $online_users;
	}

	/**
	 * Get online users grouped by role
	 *
	 * @param bool $use_cache Whether to use cached results.
	 * @return array Array of online users grouped by role.
	 */
	public function get_online_users_by_role( $use_cache = true ) {
		$online_user_ids = $this->get_online_users( $use_cache );
		$users_by_role   = array();

		foreach ( $online_user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$roles = $user->roles;
			if ( empty( $roles ) ) {
				$roles = array( 'none' );
			}

			foreach ( $roles as $role ) {
				if ( ! isset( $users_by_role[ $role ] ) ) {
					$users_by_role[ $role ] = array();
				}
				$users_by_role[ $role ][] = $user_id;
			}
		}

		return $users_by_role;
	}

	/**
	 * Get online user count
	 *
	 * @param bool $use_cache Whether to use cached results.
	 * @return int Number of online users.
	 */
	public function get_online_user_count( $use_cache = true ) {
		$online_users = $this->get_online_users( $use_cache );
		return count( $online_users );
	}

	/**
	 * Get online user counts by role
	 *
	 * @param bool $use_cache Whether to use cached results.
	 * @return array Array of counts by role.
	 */
	public function get_online_counts_by_role( $use_cache = true ) {
		$users_by_role = $this->get_online_users_by_role( $use_cache );
		$counts        = array();

		foreach ( $users_by_role as $role => $users ) {
			$counts[ $role ] = count( $users );
		}

		return $counts;
	}

	/**
	 * Get last seen time for a user
	 *
	 * @param int $user_id User ID.
	 * @return string|false Last seen time or false if not available.
	 */
	public function get_user_last_seen( $user_id ) {
		if ( ! class_exists( 'WP_Session_Tokens' ) ) {
			return false;
		}

		$sessions      = \WP_Session_Tokens::get_instance( $user_id );
		$user_sessions = $sessions->get_all();

		if ( empty( $user_sessions ) ) {
			// Check user meta for last login if no active sessions.
			$last_login = get_user_meta( $user_id, 'fwaum_last_login', true );
			if ( $last_login ) {
				return $last_login;
			}
			return false;
		}

		// Get the most recent session login time.
		$latest_login = 0;
		foreach ( $user_sessions as $session ) {
			if ( isset( $session['login'] ) && $session['login'] > $latest_login ) {
				$latest_login = $session['login'];
			}
		}

		if ( $latest_login > 0 ) {
			// Store last login for future reference.
			update_user_meta( $user_id, 'fwaum_last_login', $latest_login );
			return $latest_login;
		}

		return false;
	}

	/**
	 * Format last seen time for display
	 *
	 * @param int $user_id User ID.
	 * @return string Formatted last seen string.
	 */
	public function get_formatted_last_seen( $user_id ) {
		$last_seen = $this->get_user_last_seen( $user_id );

		if ( false === $last_seen ) {
			return __( 'Never', 'fullworks-active-users-monitor' );
		}

		if ( $this->is_user_online( $user_id ) ) {
			return __( 'Online now', 'fullworks-active-users-monitor' );
		}

		$time_diff = time() - $last_seen;

		if ( $time_diff < MINUTE_IN_SECONDS ) {
			return __( 'Just now', 'fullworks-active-users-monitor' );
		} elseif ( $time_diff < HOUR_IN_SECONDS ) {
			$minutes = round( $time_diff / MINUTE_IN_SECONDS );
			/* translators: %d: Number of minutes */
			return sprintf( _n( '%d minute ago', '%d minutes ago', $minutes, 'fullworks-active-users-monitor' ), $minutes );
		} elseif ( $time_diff < DAY_IN_SECONDS ) {
			$hours = round( $time_diff / HOUR_IN_SECONDS );
			/* translators: %d: Number of hours */
			return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'fullworks-active-users-monitor' ), $hours );
		} elseif ( $time_diff < WEEK_IN_SECONDS ) {
			$days = round( $time_diff / DAY_IN_SECONDS );
			/* translators: %d: Number of days */
			return sprintf( _n( '%d day ago', '%d days ago', $days, 'fullworks-active-users-monitor' ), $days );
		} else {
			return date_i18n( get_option( 'date_format' ), $last_seen );
		}
	}

	/**
	 * Clear cache
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Track user login
	 *
	 * @param string $user_login Username.
	 * @param object $user WP_User object.
	 */
	public function track_login( $user_login, $user ) {
		update_user_meta( $user->ID, 'fwaum_last_login', time() );
		$this->clear_cache();
	}

	/**
	 * Track user logout
	 */
	public function track_logout() {
		$this->clear_cache();
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Track login/logout for cache clearing.
		add_action( 'wp_login', array( $this, 'track_login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'track_logout' ) );
	}
}
