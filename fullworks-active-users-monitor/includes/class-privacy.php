<?php
/**
 * Privacy tools integration: policy text, personal data exporter and eraser
 *
 * @package FullworksActiveUsersMonitor\Includes
 * @since 1.2.0
 */

namespace FullworksActiveUsersMonitor\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy class
 */
class Privacy {

	/**
	 * Entries handled per exporter/eraser page.
	 *
	 * @var int
	 */
	const PAGE_SIZE = 500;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Suggest privacy policy text under Settings > Privacy.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p>' . esc_html__( 'When the audit trail is enabled, this site records each login, logout, failed login attempt and expired session. Each record holds the username and display name involved, the IP address and browser user agent of the request, the login method and the time.', 'fullworks-active-users-monitor' ) . '</p>'
			. '<p>' . esc_html__( 'Failed login attempts are recorded with the username that was tried, even when no such account exists.', 'fullworks-active-users-monitor' ) . '</p>'
			. '<p>' . esc_html__( 'Records are kept for the retention period set by the site administrator and IP addresses are anonymized after the period they choose. Site administrators can see and export these records. They can be included in a personal data export and removed on an erasure request.', 'fullworks-active-users-monitor' ) . '</p>'
			. '<p>' . esc_html__( 'The time of each user\'s most recent login is also stored with their account to show when they were last seen.', 'fullworks-active-users-monitor' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Fullworks Active Users Monitor', 'fullworks-active-users-monitor' ), wp_kses_post( $content ) );
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['fullworks-active-users-monitor'] = array(
			'exporter_friendly_name' => __( 'Active Users Monitor audit log', 'fullworks-active-users-monitor' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['fullworks-active-users-monitor'] = array(
			'eraser_friendly_name' => __( 'Active Users Monitor audit log', 'fullworks-active-users-monitor' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Export a user's audit log entries.
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          Page number, from 1.
	 * @return array
	 */
	public function export_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		global $wpdb;
		$page   = max( 1, (int) $page );
		$offset = ( $page - 1 ) * self::PAGE_SIZE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off privacy request on the plugin's own table.
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d OR username = %s ORDER BY id LIMIT %d OFFSET %d',
				Audit_Installer::get_table_name(),
				$user->ID,
				$user->user_login,
				self::PAGE_SIZE,
				$offset
			)
		);

		$data = array();
		foreach ( $entries as $entry ) {
			$data[] = array(
				'group_id'    => 'fwaum-audit-log',
				'group_label' => __( 'Login activity', 'fullworks-active-users-monitor' ),
				'item_id'     => 'fwaum-audit-' . $entry->id,
				'data'        => array(
					array(
						'name'  => __( 'Event', 'fullworks-active-users-monitor' ),
						'value' => $entry->event_type,
					),
					array(
						'name'  => __( 'Date & Time', 'fullworks-active-users-monitor' ),
						'value' => $entry->timestamp,
					),
					array(
						'name'  => __( 'Username', 'fullworks-active-users-monitor' ),
						'value' => $entry->username,
					),
					array(
						'name'  => __( 'IP Address', 'fullworks-active-users-monitor' ),
						'value' => $entry->ip_address,
					),
					array(
						'name'  => __( 'User Agent', 'fullworks-active-users-monitor' ),
						'value' => $entry->user_agent,
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => count( $entries ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Erase a user's audit log entries.
	 *
	 * @param string $email_address User email address.
	 * @param int    $page          Page number, from 1. Unused: each call deletes the next batch.
	 * @return array
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 */
	public function erase_personal_data( $email_address, $page = 1 ) {
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off privacy request on the plugin's own table.
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE user_id = %d OR username = %s LIMIT %d',
				Audit_Installer::get_table_name(),
				$user->ID,
				$user->user_login,
				self::PAGE_SIZE
			)
		);

		if ( $deleted < self::PAGE_SIZE ) {
			delete_user_meta( $user->ID, 'fwaum_last_login' );
			delete_user_meta( $user->ID, 'fwaum_session_start' );
		}

		return array(
			'items_removed'  => $deleted > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $deleted < self::PAGE_SIZE,
		);
	}
}
