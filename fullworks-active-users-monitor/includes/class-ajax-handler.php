<?php
/**
 * AJAX Handler for real-time updates
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
 * AJAX Handler class
 */
class Ajax_Handler {

	/**
	 * User tracker instance
	 *
	 * @var User_Tracker
	 */
	private $user_tracker;

	/**
	 * Constructor
	 *
	 * @param User_Tracker $user_tracker User tracker instance.
	 */
	public function __construct( User_Tracker $user_tracker ) {
		$this->user_tracker = $user_tracker;

		// Register AJAX handlers.
		add_action( 'wp_ajax_fwaum_get_online_users', array( $this, 'get_online_users' ) );
		add_action( 'wp_ajax_fwaum_refresh_users_list', array( $this, 'refresh_users_list' ) );
		add_action( 'wp_ajax_fwaum_get_user_status', array( $this, 'get_user_status' ) );
	}

	/**
	 * Get online users via AJAX
	 */
	public function get_online_users() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'fwaum_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Invalid security token', 'fullworks-active-users-monitor' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'list_users' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'fullworks-active-users-monitor' ) );
		}

		// Clear cache for fresh data.
		$this->user_tracker->clear_cache();

		// Get online users.
		$online_users   = $this->user_tracker->get_online_users( false );
		$online_count   = count( $online_users );
		$counts_by_role = $this->user_tracker->get_online_counts_by_role( false );

		// Get user details.
		$users_data = array();
		foreach ( $online_users as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$users_data[] = array(
					'id'           => $user_id,
					'username'     => $user->user_login,
					'display_name' => $user->display_name,
					'email'        => $user->user_email,
					'roles'        => $user->roles,
					'avatar_url'   => get_avatar_url( $user_id, array( 'size' => 32 ) ),
					'profile_url'  => get_edit_user_link( $user_id ),
					'last_seen'    => $this->user_tracker->get_formatted_last_seen( $user_id ),
				);
			}
		}

		// Format role counts.
		$role_counts = array();
		foreach ( $counts_by_role as $role => $count ) {
			$role_obj      = get_role( $role );
			$role_counts[] = array(
				'role'  => $role,
				'name'  => $role_obj ? translate_user_role( $role_obj->name ) : ucfirst( $role ),
				'count' => $count,
			);
		}

		wp_send_json_success(
			array(
				'total'       => $online_count,
				'users'       => $users_data,
				'role_counts' => $role_counts,
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Using timestamp format for JavaScript Date() compatibility in AJAX response. Site timezone needed for accurate "last updated" display.
				'timestamp'   => current_time( 'timestamp' ),
			)
		);
	}

	/**
	 * Refresh users list table via AJAX
	 */
	public function refresh_users_list() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'fwaum_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Invalid security token', 'fullworks-active-users-monitor' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'list_users' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'fullworks-active-users-monitor' ) );
		}

		// Get page of users to update.
		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 20;

		// Clear cache for fresh data.
		$this->user_tracker->clear_cache();

		// Get users on current page.
		$args = array(
			'number' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
			'fields' => 'ID',
		);

		// Apply filter if set.
		if ( isset( $_POST['filter'] ) && ! empty( $_POST['filter'] ) ) {
			$filter = sanitize_text_field( wp_unslash( $_POST['filter'] ) );
			if ( 'online' === $filter ) {
				$online_users = $this->user_tracker->get_online_users( false );
				if ( empty( $online_users ) ) {
					$args['include'] = array( 0 );
				} else {
					$args['include'] = $online_users;
				}
			} elseif ( 'offline' === $filter ) {
				$online_users = $this->user_tracker->get_online_users( false );
				if ( ! empty( $online_users ) ) {
					$args['exclude'] = $online_users;
				}
			}
		}

		$user_query = new \WP_User_Query( $args );
		$users      = $user_query->get_results();

		// Build status data for each user.
		$status_data = array();
		foreach ( $users as $user_id ) {
			$is_online = $this->user_tracker->is_user_online( $user_id );
			$user      = get_userdata( $user_id );

			$status_data[] = array(
				'user_id'   => $user_id,
				'is_online' => $is_online,
				'last_seen' => $this->user_tracker->get_formatted_last_seen( $user_id ),
				'role'      => ! empty( $user->roles ) ? $user->roles[0] : 'subscriber',
			);
		}

		// Get updated stats.
		$online_count   = $this->user_tracker->get_online_user_count( false );
		$counts_by_role = $this->user_tracker->get_online_counts_by_role( false );

		// Get total user count for offline calculation.
		$total_users   = count_users();
		$offline_count = $total_users['total_users'] - $online_count;

		wp_send_json_success(
			array(
				'users'         => $status_data,
				'total_online'  => $online_count,
				'total_offline' => $offline_count,
				'total_users'   => $total_users['total_users'],
				'role_counts'   => $counts_by_role,
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Using timestamp format for JavaScript Date() compatibility in AJAX response. Site timezone needed for accurate "last updated" display.
				'timestamp'     => current_time( 'timestamp' ),
			)
		);
	}

	/**
	 * Get single user status via AJAX
	 */
	public function get_user_status() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'fwaum_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Invalid security token', 'fullworks-active-users-monitor' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'list_users' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'fullworks-active-users-monitor' ) );
		}

		// Get user ID.
		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( __( 'Invalid user ID', 'fullworks-active-users-monitor' ) );
		}

		// Get user status.
		$is_online = $this->user_tracker->is_user_online( $user_id );
		$last_seen = $this->user_tracker->get_formatted_last_seen( $user_id );

		// Get user details.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( __( 'User not found', 'fullworks-active-users-monitor' ) );
		}

		wp_send_json_success(
			array(
				'user_id'      => $user_id,
				'is_online'    => $is_online,
				'last_seen'    => $last_seen,
				'username'     => $user->user_login,
				'display_name' => $user->display_name,
				'roles'        => $user->roles,
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Using timestamp format for JavaScript Date() compatibility in AJAX response. Site timezone needed for accurate "last updated" display.
				'timestamp'    => current_time( 'timestamp' ),
			)
		);
	}
}
