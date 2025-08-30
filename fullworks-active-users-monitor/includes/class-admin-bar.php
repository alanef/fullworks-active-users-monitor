<?php
/**
 * Admin Bar functionality
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
 * Admin Bar class
 */
class Admin_Bar {

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

		// Check if admin bar is enabled in settings.
		$options = get_option( 'fwaum_settings', array() );
		$enabled = isset( $options['enable_admin_bar'] ) ? $options['enable_admin_bar'] : true;

		if ( $enabled ) {
			add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_item' ), 100 );
			add_action( 'wp_ajax_fwaum_update_admin_bar', array( $this, 'ajax_update_admin_bar' ) );
		}
	}

	/**
	 * Add admin bar item
	 *
	 * @param object $wp_admin_bar WP_Admin_Bar instance.
	 */
	public function add_admin_bar_item( $wp_admin_bar ) {
		// Only show to users who can list users.
		if ( ! current_user_can( 'list_users' ) ) {
			return;
		}

		// Get online users count.
		$online_count   = $this->user_tracker->get_online_user_count();
		$counts_by_role = $this->user_tracker->get_online_counts_by_role();

		// Build the title.
		$title = sprintf(
			'<span class="fwaum-admin-bar-icon">👥</span> <span class="fwaum-admin-bar-text">%s: <span class="fwaum-online-count">%d</span></span>',
			__( 'Users Online', 'fullworks-active-users-monitor' ),
			$online_count
		);

		// Add main node.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'fwaum-online-users',
				'title' => $title,
				'href'  => admin_url( 'users.php?fwaum_filter=online' ),
				'meta'  => array(
					'class' => 'fwaum-admin-bar-item',
					'title' => __( 'View online users', 'fullworks-active-users-monitor' ),
				),
			)
		);

		// Add submenu with role breakdown.
		if ( ! empty( $counts_by_role ) ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'fwaum-online-users',
					'id'     => 'fwaum-role-breakdown',
					'title'  => '<strong>' . __( 'Online by Role:', 'fullworks-active-users-monitor' ) . '</strong>',
					'meta'   => array(
						'class' => 'fwaum-role-header',
					),
				)
			);

			// Sort roles by priority.
			$role_priority = array(
				'administrator' => 1,
				'editor'        => 2,
				'author'        => 3,
				'contributor'   => 4,
				'subscriber'    => 5,
			);

			uksort(
				$counts_by_role,
				function ( $a, $b ) use ( $role_priority ) {
					$priority_a = isset( $role_priority[ $a ] ) ? $role_priority[ $a ] : 999;
					$priority_b = isset( $role_priority[ $b ] ) ? $role_priority[ $b ] : 999;
					return $priority_a - $priority_b;
				}
			);

			foreach ( $counts_by_role as $role => $count ) {
				$role_obj  = get_role( $role );
				$role_name = $role_obj ? translate_user_role( $role_obj->name ) : ucfirst( $role );

				$wp_admin_bar->add_node(
					array(
						'parent' => 'fwaum-online-users',
						'id'     => 'fwaum-role-' . $role,
						'title'  => sprintf(
							'<span class="fwaum-role-count">%d %s</span>',
							$count,
							$role_name
						),
						'href'   => admin_url( 'users.php?role=' . $role . '&fwaum_filter=online' ),
						'meta'   => array(
							'class' => 'fwaum-role-item fwaum-role-' . $role,
						),
					)
				);
			}
		}

		// Add separator.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'fwaum-online-users',
				'id'     => 'fwaum-separator',
				'title'  => '<hr class="fwaum-separator" />',
				'meta'   => array(
					'class' => 'fwaum-separator-item',
				),
			)
		);

		// Add link to all users.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'fwaum-online-users',
				'id'     => 'fwaum-view-all',
				'title'  => __( 'View All Users', 'fullworks-active-users-monitor' ),
				'href'   => admin_url( 'users.php' ),
			)
		);

		// Add settings link.
		if ( current_user_can( 'manage_options' ) ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'fwaum-online-users',
					'id'     => 'fwaum-settings',
					'title'  => __( 'Settings', 'fullworks-active-users-monitor' ),
					'href'   => admin_url( 'options-general.php?page=fwaum-settings' ),
				)
			);
		}
	}

	/**
	 * Handle AJAX request to update admin bar
	 */
	public function ajax_update_admin_bar() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'fwaum_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Invalid security token', 'fullworks-active-users-monitor' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'list_users' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'fullworks-active-users-monitor' ) );
		}

		// Get fresh data.
		$this->user_tracker->clear_cache();
		$online_count   = $this->user_tracker->get_online_user_count( false );
		$counts_by_role = $this->user_tracker->get_online_counts_by_role( false );

		// Format role data.
		$role_data = array();
		foreach ( $counts_by_role as $role => $count ) {
			$role_obj    = get_role( $role );
			$role_name   = $role_obj ? translate_user_role( $role_obj->name ) : ucfirst( $role );
			$role_data[] = array(
				'role'  => $role,
				'name'  => $role_name,
				'count' => $count,
			);
		}

		wp_send_json_success(
			array(
				'total'      => $online_count,
				'roles'      => $role_data,
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Using timestamp format for JavaScript Date() compatibility in AJAX response. Site timezone needed for accurate "last updated" display.
				'updated_at' => current_time( 'timestamp' ),
			)
		);
	}
}
