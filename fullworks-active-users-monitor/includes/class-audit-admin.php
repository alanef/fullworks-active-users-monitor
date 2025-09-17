<?php
/**
 * Admin page for audit log management
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
 * Audit Admin class
 */
class Audit_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_fwaum_audit_get_details', array( $this, 'ajax_get_entry_details' ) );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_admin_pages() {
		// Add audit log as submenu under Users.
		add_users_page(
			esc_html__( 'Audit Log', 'fullworks-active-users-monitor' ),
			esc_html__( 'Audit Log', 'fullworks-active-users-monitor' ),
			'manage_options',
			'fwaum-audit-log',
			array( $this, 'render_audit_log_page' )
		);

		// Add export as submenu under Users.
		add_users_page(
			esc_html__( 'Export Audit Log', 'fullworks-active-users-monitor' ),
			esc_html__( 'Export Audit Log', 'fullworks-active-users-monitor' ),
			'manage_options',
			'fwaum-audit-export',
			array( $this, 'render_export_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'users_page_fwaum-audit-log', 'users_page_fwaum-audit-export' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'fwaum-audit-admin',
			FWAUM_PLUGIN_URL . 'assets/css/audit-admin.css',
			array(),
			FWAUM_VERSION
		);

		wp_enqueue_script(
			'fwaum-audit-admin',
			FWAUM_PLUGIN_URL . 'assets/js/audit-admin.js',
			array( 'jquery' ),
			FWAUM_VERSION,
			true
		);

		wp_localize_script(
			'fwaum-audit-admin',
			'fwaumAuditAjax',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'fwaum_audit_nonce' ),
				'exportNonce' => wp_create_nonce( 'fwaum_export_nonce' ),
				'strings'     => array(
					'confirmDelete' => esc_html__( 'Are you sure you want to delete the selected audit entries? This action cannot be undone.', 'fullworks-active-users-monitor' ),
					'loading'       => esc_html__( 'Loading...', 'fullworks-active-users-monitor' ),
					'error'         => esc_html__( 'An error occurred while processing your request.', 'fullworks-active-users-monitor' ),
					'exportStart'   => esc_html__( 'Starting export...', 'fullworks-active-users-monitor' ),
				),
			)
		);
	}

	/**
	 * Render audit log page
	 */
	public function render_audit_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fullworks-active-users-monitor' ) );
		}

		// Check if audit logging is enabled.
		$options = get_option( 'fwaum_settings', array() );
		if ( empty( $options['enable_audit_log'] ) ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Audit Log', 'fullworks-active-users-monitor' ); ?></h1>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'Audit logging is currently disabled.', 'fullworks-active-users-monitor' ); ?>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=fwaum-settings' ) ); ?>">
							<?php esc_html_e( 'Enable it in settings', 'fullworks-active-users-monitor' ); ?>
						</a>
						<?php esc_html_e( 'to start tracking user activities.', 'fullworks-active-users-monitor' ); ?>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		// Initialize table.
		$audit_table = new Audit_Table();
		$audit_table->prepare_items();
		$audit_table->process_bulk_action();

		// Get stats for dashboard.
		$stats       = Audit_Logger::get_audit_stats( 'today' );
		$table_stats = Audit_Installer::get_table_stats();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Audit Log', 'fullworks-active-users-monitor' ); ?></h1>

			<!-- Statistics Dashboard -->
			<div class="fwaum-audit-stats">
				<div class="fwaum-stat-cards">
					<div class="fwaum-stat-card">
						<h3><?php esc_html_e( 'Today\'s Activity', 'fullworks-active-users-monitor' ); ?></h3>
						<div class="fwaum-stat-grid">
							<div class="fwaum-stat-item">
								<span class="fwaum-stat-number"><?php echo esc_html( number_format_i18n( $stats['login'] ) ); ?></span>
								<span class="fwaum-stat-label"><?php esc_html_e( 'Logins', 'fullworks-active-users-monitor' ); ?></span>
							</div>
							<div class="fwaum-stat-item">
								<span class="fwaum-stat-number"><?php echo esc_html( number_format_i18n( $stats['failed_login'] ) ); ?></span>
								<span class="fwaum-stat-label"><?php esc_html_e( 'Failed Logins', 'fullworks-active-users-monitor' ); ?></span>
							</div>
							<div class="fwaum-stat-item">
								<span class="fwaum-stat-number"><?php echo esc_html( number_format_i18n( $stats['unique_users'] ) ); ?></span>
								<span class="fwaum-stat-label"><?php esc_html_e( 'Unique Users', 'fullworks-active-users-monitor' ); ?></span>
							</div>
						</div>
					</div>

					<div class="fwaum-stat-card">
						<h3><?php esc_html_e( 'Total Records', 'fullworks-active-users-monitor' ); ?></h3>
						<div class="fwaum-stat-big">
							<span class="fwaum-stat-number"><?php echo esc_html( number_format_i18n( $table_stats['total_entries'] ) ); ?></span>
							<span class="fwaum-stat-label"><?php esc_html_e( 'Audit Entries', 'fullworks-active-users-monitor' ); ?></span>
						</div>
						<?php if ( $table_stats['oldest_entry'] ) : ?>
							<p class="fwaum-stat-meta">
								<?php
								printf(
									/* translators: %s: date */
									esc_html__( 'Since %s', 'fullworks-active-users-monitor' ),
									esc_html( date_i18n( get_option( 'date_format' ), strtotime( $table_stats['oldest_entry'] ) ) )
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="fwaum-quick-actions">
					<a href="<?php echo esc_url( admin_url( 'users.php?page=fwaum-audit-export' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Export Audit Log', 'fullworks-active-users-monitor' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=fwaum-settings#fwaum_audit_section' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Audit Settings', 'fullworks-active-users-monitor' ); ?>
					</a>
				</div>
			</div>

			<!-- Audit Log Table -->
			<form method="get">
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just preserving the admin page parameter. ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php
				$audit_table->search_box( esc_html__( 'Search audit entries', 'fullworks-active-users-monitor' ), 'search' );
				$audit_table->views();
				$audit_table->display();
				?>
			</form>
		</div>

		<!-- Modal for entry details -->
		<div id="fwaum-audit-details-modal" class="fwaum-modal" style="display: none;">
			<div class="fwaum-modal-content">
				<span class="fwaum-modal-close">&times;</span>
				<h2><?php esc_html_e( 'Audit Entry Details', 'fullworks-active-users-monitor' ); ?></h2>
				<div id="fwaum-audit-details-content">
					<!-- Content will be loaded via AJAX -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render export page
	 */
	public function render_export_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'fullworks-active-users-monitor' ) );
		}

		$stats = Audit_Exporter::get_export_stats();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export Audit Log', 'fullworks-active-users-monitor' ); ?></h1>

			<div class="fwaum-export-stats">
				<h2><?php esc_html_e( 'Export Information', 'fullworks-active-users-monitor' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Total Entries', 'fullworks-active-users-monitor' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $stats['total_entries'] ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Estimated Size', 'fullworks-active-users-monitor' ); ?></th>
						<td><?php echo esc_html( $stats['size_estimate'] ); ?></td>
					</tr>
					<?php if ( $stats['oldest_entry'] && $stats['newest_entry'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Date Range', 'fullworks-active-users-monitor' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: 1: start date, 2: end date */
								esc_html__( '%1$s to %2$s', 'fullworks-active-users-monitor' ),
								esc_html( date_i18n( get_option( 'date_format' ), strtotime( $stats['oldest_entry'] ) ) ),
								esc_html( date_i18n( get_option( 'date_format' ), strtotime( $stats['newest_entry'] ) ) )
							);
							?>
						</td>
					</tr>
					<?php endif; ?>
				</table>
			</div>

			<form id="fwaum-export-form" class="fwaum-export-form">
				<h2><?php esc_html_e( 'Export Options', 'fullworks-active-users-monitor' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="export-format"><?php esc_html_e( 'Export Format', 'fullworks-active-users-monitor' ); ?></label>
						</th>
						<td>
							<select id="export-format" name="format">
								<option value="csv"><?php esc_html_e( 'CSV (Comma Separated Values)', 'fullworks-active-users-monitor' ); ?></option>
								<option value="json"><?php esc_html_e( 'JSON (JavaScript Object Notation)', 'fullworks-active-users-monitor' ); ?></option>
								<option value="excel"><?php esc_html_e( 'Excel (XML Format)', 'fullworks-active-users-monitor' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose the format for your export file.', 'fullworks-active-users-monitor' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="export-event-type"><?php esc_html_e( 'Event Type', 'fullworks-active-users-monitor' ); ?></label>
						</th>
						<td>
							<select id="export-event-type" name="event_type">
								<option value=""><?php esc_html_e( 'All Events', 'fullworks-active-users-monitor' ); ?></option>
								<option value="login"><?php esc_html_e( 'Logins Only', 'fullworks-active-users-monitor' ); ?></option>
								<option value="logout"><?php esc_html_e( 'Logouts Only', 'fullworks-active-users-monitor' ); ?></option>
								<option value="failed_login"><?php esc_html_e( 'Failed Logins Only', 'fullworks-active-users-monitor' ); ?></option>
								<option value="session_expired"><?php esc_html_e( 'Session Expired Only', 'fullworks-active-users-monitor' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="export-date-from"><?php esc_html_e( 'Date Range', 'fullworks-active-users-monitor' ); ?></label>
						</th>
						<td>
							<input type="date" id="export-date-from" name="date_from" />
							<span style="margin: 0 10px;"><?php esc_html_e( 'to', 'fullworks-active-users-monitor' ); ?></span>
							<input type="date" id="export-date-to" name="date_to" />
							<p class="description"><?php esc_html_e( 'Leave empty to export all dates.', 'fullworks-active-users-monitor' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="export-search"><?php esc_html_e( 'Search Filter', 'fullworks-active-users-monitor' ); ?></label>
						</th>
						<td>
							<input type="text" id="export-search" name="search" class="regular-text" placeholder="<?php esc_attr_e( 'Username, display name, or IP address', 'fullworks-active-users-monitor' ); ?>" />
							<p class="description"><?php esc_html_e( 'Filter entries containing specific text.', 'fullworks-active-users-monitor' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary" id="fwaum-export-button">
						<?php esc_html_e( 'Export Audit Log', 'fullworks-active-users-monitor' ); ?>
					</button>
				</p>

				<input type="hidden" name="action" value="fwaum_export_audit_log" />
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'fwaum_export_nonce' ) ); ?>" />
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX handler to get entry details
	 */
	public function ajax_get_entry_details() {
		// Verify nonce and capabilities.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) ), 'fwaum_audit_nonce' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'fullworks-active-users-monitor' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view audit details.', 'fullworks-active-users-monitor' ) );
		}

		$entry_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;
		if ( ! $entry_id ) {
			wp_die( esc_html__( 'Invalid entry ID.', 'fullworks-active-users-monitor' ) );
		}

		global $wpdb;
		$table_name = Audit_Installer::get_table_name();

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table_name,
				$entry_id
			)
		);

		if ( ! $entry ) {
			wp_die( esc_html__( 'Audit entry not found.', 'fullworks-active-users-monitor' ) );
		}

		// Parse additional data.
		$additional_data = json_decode( $entry->additional_data, true );
		if ( ! is_array( $additional_data ) ) {
			$additional_data = array();
		}

		?>
		<table class="fwaum-details-table">
			<tr>
				<th><?php esc_html_e( 'Entry ID', 'fullworks-active-users-monitor' ); ?></th>
				<td><?php echo esc_html( $entry->id ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'User', 'fullworks-active-users-monitor' ); ?></th>
				<td>
					<?php if ( intval( $entry->user_id ) > 0 ) : ?>
						<a href="<?php echo esc_url( get_edit_user_link( $entry->user_id ) ); ?>">
							<?php echo esc_html( $entry->display_name ); ?>
						</a>
						<br><small><?php echo esc_html( $entry->username ); ?> (ID: <?php echo esc_html( $entry->user_id ); ?>)</small>
					<?php else : ?>
						<?php echo esc_html( $entry->display_name ); ?>
						<br><small><?php echo esc_html( $entry->username ); ?></small>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Event Type', 'fullworks-active-users-monitor' ); ?></th>
				<td>
					<?php
					$event_labels = array(
						'login'           => esc_html__( 'Login', 'fullworks-active-users-monitor' ),
						'logout'          => esc_html__( 'Logout', 'fullworks-active-users-monitor' ),
						'failed_login'    => esc_html__( 'Failed Login', 'fullworks-active-users-monitor' ),
						'session_expired' => esc_html__( 'Session Expired', 'fullworks-active-users-monitor' ),
					);
					$event_label  = isset( $event_labels[ $entry->event_type ] ) ? $event_labels[ $entry->event_type ] : $entry->event_type;
					echo esc_html( $event_label );
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Date & Time', 'fullworks-active-users-monitor' ); ?></th>
				<td>
					<?php
					$timestamp = strtotime( $entry->timestamp );
					echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );
					?>
					<br><small><?php echo esc_html( human_time_diff( $timestamp, time() ) . ' ago' ); ?></small>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'IP Address', 'fullworks-active-users-monitor' ); ?></th>
				<td><?php echo esc_html( $entry->ip_address ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'User Agent', 'fullworks-active-users-monitor' ); ?></th>
				<td style="word-break: break-all;"><?php echo esc_html( $entry->user_agent ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Login Method', 'fullworks-active-users-monitor' ); ?></th>
				<td>
					<?php
					$method_labels = array(
						'standard'   => esc_html__( 'Standard', 'fullworks-active-users-monitor' ),
						'social'     => esc_html__( 'Social', 'fullworks-active-users-monitor' ),
						'two_factor' => esc_html__( 'Two Factor', 'fullworks-active-users-monitor' ),
						'xmlrpc'     => esc_html__( 'XML-RPC', 'fullworks-active-users-monitor' ),
						'rest_api'   => esc_html__( 'REST API', 'fullworks-active-users-monitor' ),
						'wp_cli'     => esc_html__( 'WP-CLI', 'fullworks-active-users-monitor' ),
						'migrated'   => esc_html__( 'Migrated', 'fullworks-active-users-monitor' ),
					);
					$method_label  = isset( $method_labels[ $entry->login_method ] ) ? $method_labels[ $entry->login_method ] : $entry->login_method;
					echo esc_html( $method_label );
					?>
				</td>
			</tr>
			<?php if ( $entry->session_duration ) : ?>
			<tr>
				<th><?php esc_html_e( 'Session Duration', 'fullworks-active-users-monitor' ); ?></th>
				<td>
					<?php
					$duration = intval( $entry->session_duration );
					if ( $duration < 60 ) {
						/* translators: %d: number of seconds */
						echo esc_html( sprintf( _n( '%d second', '%d seconds', $duration, 'fullworks-active-users-monitor' ), $duration ) );
					} elseif ( $duration < 3600 ) {
						$minutes = round( $duration / 60 );
						/* translators: %d: number of minutes */
						echo esc_html( sprintf( _n( '%d minute', '%d minutes', $minutes, 'fullworks-active-users-monitor' ), $minutes ) );
					} else {
						$hours   = floor( $duration / 3600 );
						$minutes = round( ( $duration % 3600 ) / 60 );
						if ( $minutes > 0 ) {
							/* translators: 1: number of hours, 2: number of minutes */
							echo esc_html( sprintf( __( '%1$d hours %2$d minutes', 'fullworks-active-users-monitor' ), $hours, $minutes ) );
						} else {
							/* translators: %d: number of hours */
							echo esc_html( sprintf( _n( '%d hour', '%d hours', $hours, 'fullworks-active-users-monitor' ), $hours ) );
						}
					}
					?>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ( ! empty( $additional_data ) ) : ?>
			<tr>
				<th><?php esc_html_e( 'Additional Data', 'fullworks-active-users-monitor' ); ?></th>
				<td>
					<pre style="white-space: pre-wrap; font-size: 12px;"><?php echo esc_html( wp_json_encode( $additional_data, JSON_PRETTY_PRINT ) ); ?></pre>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php

		wp_die();
	}
}