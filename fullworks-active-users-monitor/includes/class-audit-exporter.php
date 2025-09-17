<?php
/**
 * Export functionality for audit log entries
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
 * Audit Exporter class
 */
class Audit_Exporter {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_fwaum_export_audit_log', array( $this, 'handle_export_request' ) );
	}

	/**
	 * Handle AJAX export request
	 */
	public function handle_export_request() {
		// Verify nonce and capabilities.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) ), 'fwaum_export_nonce' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'fullworks-active-users-monitor' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export audit logs.', 'fullworks-active-users-monitor' ) );
		}

		// Get export parameters.
		$format     = isset( $_REQUEST['format'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['format'] ) ) : 'csv';
		$user_id    = isset( $_REQUEST['user_id'] ) ? intval( $_REQUEST['user_id'] ) : null;
		$event_type = isset( $_REQUEST['event_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['event_type'] ) ) : null;
		$date_from  = isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : null;
		$date_to    = isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : null;
		$search     = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : null;

		// Prepare query arguments.
		$args = array(
			'per_page'   => 50000, // Large number for export.
			'page'       => 1,
			'orderby'    => 'timestamp',
			'order'      => 'DESC',
			'user_id'    => $user_id,
			'event_type' => $event_type,
			'date_from'  => $date_from,
			'date_to'    => $date_to,
			'search'     => $search,
		);

		// Generate filename.
		$filename = $this->generate_filename( $format, $args );

		// Export based on format.
		switch ( $format ) {
			case 'json':
				$this->export_json( $args, $filename );
				break;
			case 'excel':
				$this->export_excel( $args, $filename );
				break;
			case 'csv':
			default:
				$this->export_csv( $args, $filename );
				break;
		}
	}

	/**
	 * Export to CSV format
	 *
	 * @param array  $args     Query arguments.
	 * @param string $filename Output filename.
	 */
	private function export_csv( $args, $filename ) {
		$results = Audit_Logger::get_audit_entries( $args );

		// Set headers for download.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Open output stream.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://output is for direct browser output, not filesystem.
		$output = fopen( 'php://output', 'w' );

		// Write BOM for proper Excel UTF-8 handling.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Write CSV header.
		fputcsv(
			$output,
			array(
				__( 'ID', 'fullworks-active-users-monitor' ),
				__( 'User ID', 'fullworks-active-users-monitor' ),
				__( 'Username', 'fullworks-active-users-monitor' ),
				__( 'Display Name', 'fullworks-active-users-monitor' ),
				__( 'Event Type', 'fullworks-active-users-monitor' ),
				__( 'Date & Time', 'fullworks-active-users-monitor' ),
				__( 'IP Address', 'fullworks-active-users-monitor' ),
				__( 'User Agent', 'fullworks-active-users-monitor' ),
				__( 'Login Method', 'fullworks-active-users-monitor' ),
				__( 'Session Duration (seconds)', 'fullworks-active-users-monitor' ),
				__( 'Additional Data', 'fullworks-active-users-monitor' ),
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
					$this->get_event_type_label( $entry->event_type ),
					$entry->timestamp,
					$entry->ip_address,
					$entry->user_agent,
					$this->get_login_method_label( $entry->login_method ),
					$entry->session_duration,
					$entry->additional_data,
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream, not filesystem.
		fclose( $output );
		exit;
	}

	/**
	 * Export to JSON format
	 *
	 * @param array  $args     Query arguments.
	 * @param string $filename Output filename.
	 */
	private function export_json( $args, $filename ) {
		$results = Audit_Logger::get_audit_entries( $args );

		// Prepare data for JSON export.
		$data = array(
			'export_info' => array(
				'exported_at'     => current_time( 'mysql' ),
				'total_entries'   => $results['total_items'],
				'filters_applied' => array_filter( $args ),
			),
			'entries'     => array(),
		);

		foreach ( $results['entries'] as $entry ) {
			$data['entries'][] = array(
				'id'                 => intval( $entry->id ),
				'user_id'            => intval( $entry->user_id ),
				'username'           => $entry->username,
				'display_name'       => $entry->display_name,
				'event_type'         => $entry->event_type,
				'event_type_label'   => $this->get_event_type_label( $entry->event_type ),
				'timestamp'          => $entry->timestamp,
				'ip_address'         => $entry->ip_address,
				'user_agent'         => $entry->user_agent,
				'login_method'       => $entry->login_method,
				'login_method_label' => $this->get_login_method_label( $entry->login_method ),
				'session_duration'   => $entry->session_duration ? intval( $entry->session_duration ) : null,
				'additional_data'    => json_decode( $entry->additional_data, true ),
			);
		}

		// Set headers for download.
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Export to Excel format (XML-based)
	 *
	 * @param array  $args     Query arguments.
	 * @param string $filename Output filename.
	 */
	private function export_excel( $args, $filename ) {
		$results = Audit_Logger::get_audit_entries( $args );

		// Set headers for download.
		header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Start Excel XML.
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
		echo '<Worksheet ss:Name="Audit Log">' . "\n";
		echo '<Table>' . "\n";

		// Header row.
		echo '<Row>' . "\n";
		$headers = array(
			__( 'ID', 'fullworks-active-users-monitor' ),
			__( 'User ID', 'fullworks-active-users-monitor' ),
			__( 'Username', 'fullworks-active-users-monitor' ),
			__( 'Display Name', 'fullworks-active-users-monitor' ),
			__( 'Event Type', 'fullworks-active-users-monitor' ),
			__( 'Date & Time', 'fullworks-active-users-monitor' ),
			__( 'IP Address', 'fullworks-active-users-monitor' ),
			__( 'User Agent', 'fullworks-active-users-monitor' ),
			__( 'Login Method', 'fullworks-active-users-monitor' ),
			__( 'Session Duration (seconds)', 'fullworks-active-users-monitor' ),
		);

		foreach ( $headers as $header ) {
			echo '<Cell><Data ss:Type="String">' . esc_html( $header ) . '</Data></Cell>' . "\n";
		}
		echo '</Row>' . "\n";

		// Data rows.
		foreach ( $results['entries'] as $entry ) {
			echo '<Row>' . "\n";
			$cells = array(
				array(
					'type'  => 'Number',
					'value' => $entry->id,
				),
				array(
					'type'  => 'Number',
					'value' => $entry->user_id,
				),
				array(
					'type'  => 'String',
					'value' => $entry->username,
				),
				array(
					'type'  => 'String',
					'value' => $entry->display_name,
				),
				array(
					'type'  => 'String',
					'value' => $this->get_event_type_label( $entry->event_type ),
				),
				array(
					'type'  => 'DateTime',
					'value' => $entry->timestamp,
				),
				array(
					'type'  => 'String',
					'value' => $entry->ip_address,
				),
				array(
					'type'  => 'String',
					'value' => $entry->user_agent,
				),
				array(
					'type'  => 'String',
					'value' => $this->get_login_method_label( $entry->login_method ),
				),
				array(
					'type'  => 'Number',
					'value' => $entry->session_duration ?? '',
				),
			);

			foreach ( $cells as $cell ) {
				echo '<Cell><Data ss:Type="' . esc_attr( $cell['type'] ) . '">' . esc_html( $cell['value'] ) . '</Data></Cell>' . "\n";
			}
			echo '</Row>' . "\n";
		}

		echo '</Table>' . "\n";
		echo '</Worksheet>' . "\n";
		echo '</Workbook>' . "\n";
		exit;
	}

	/**
	 * Generate export filename
	 *
	 * @param string $format Export format.
	 * @param array  $args   Query arguments.
	 * @return string Filename.
	 */
	private function generate_filename( $format, $args ) {
		$site_name = sanitize_title( get_bloginfo( 'name' ) );
		$timestamp = gmdate( 'Y-m-d-H-i-s' );

		$filename_parts = array( 'audit-log', $site_name, $timestamp );

		// Add filter indicators to filename.
		if ( ! empty( $args['event_type'] ) ) {
			$filename_parts[] = $args['event_type'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$user = get_userdata( $args['user_id'] );
			if ( $user ) {
				$filename_parts[] = 'user-' . sanitize_title( $user->user_login );
			}
		}

		if ( ! empty( $args['date_from'] ) || ! empty( $args['date_to'] ) ) {
			$date_range = '';
			if ( ! empty( $args['date_from'] ) ) {
				$date_range .= $args['date_from'];
			}
			if ( ! empty( $args['date_to'] ) ) {
				$date_range .= '-to-' . $args['date_to'];
			}
			if ( $date_range ) {
				$filename_parts[] = $date_range;
			}
		}

		$filename = implode( '-', $filename_parts );

		// Add extension.
		$extensions = array(
			'csv'   => 'csv',
			'json'  => 'json',
			'excel' => 'xls',
		);

		$extension = isset( $extensions[ $format ] ) ? $extensions[ $format ] : 'csv';

		return $filename . '.' . $extension;
	}

	/**
	 * Get human-readable event type label
	 *
	 * @param string $event_type Event type.
	 * @return string Event type label.
	 */
	private function get_event_type_label( $event_type ) {
		$labels = array(
			'login'           => __( 'Login', 'fullworks-active-users-monitor' ),
			'logout'          => __( 'Logout', 'fullworks-active-users-monitor' ),
			'failed_login'    => __( 'Failed Login', 'fullworks-active-users-monitor' ),
			'session_expired' => __( 'Session Expired', 'fullworks-active-users-monitor' ),
		);

		return isset( $labels[ $event_type ] ) ? $labels[ $event_type ] : $event_type;
	}

	/**
	 * Get human-readable login method label
	 *
	 * @param string $login_method Login method.
	 * @return string Login method label.
	 */
	private function get_login_method_label( $login_method ) {
		$labels = array(
			'standard'   => __( 'Standard', 'fullworks-active-users-monitor' ),
			'social'     => __( 'Social', 'fullworks-active-users-monitor' ),
			'two_factor' => __( 'Two Factor', 'fullworks-active-users-monitor' ),
			'xmlrpc'     => __( 'XML-RPC', 'fullworks-active-users-monitor' ),
			'rest_api'   => __( 'REST API', 'fullworks-active-users-monitor' ),
			'wp_cli'     => __( 'WP-CLI', 'fullworks-active-users-monitor' ),
			'migrated'   => __( 'Migrated', 'fullworks-active-users-monitor' ),
		);

		return isset( $labels[ $login_method ] ) ? $labels[ $login_method ] : $login_method;
	}

	/**
	 * Get current export statistics
	 *
	 * @return array Export statistics.
	 */
	public static function get_export_stats() {
		global $wpdb;
		$table_name = Audit_Installer::get_table_name();

		$stats = array(
			'total_entries' => 0,
			'size_estimate' => '0 KB',
			'oldest_entry'  => null,
			'newest_entry'  => null,
		);

		// Get basic stats.
		$total                  = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) );
		$stats['total_entries'] = intval( $total );

		if ( $stats['total_entries'] > 0 ) {
			// Estimate size (rough calculation).
			$avg_row_size           = 500; // bytes.
			$estimated_size         = $stats['total_entries'] * $avg_row_size;
			$stats['size_estimate'] = size_format( $estimated_size );

			// Get date range.
			$date_range = $wpdb->get_row(
				$wpdb->prepare( 'SELECT MIN(timestamp) as oldest, MAX(timestamp) as newest FROM %i', $table_name )
			);

			if ( $date_range ) {
				$stats['oldest_entry'] = $date_range->oldest;
				$stats['newest_entry'] = $date_range->newest;
			}
		}

		return $stats;
	}
}
