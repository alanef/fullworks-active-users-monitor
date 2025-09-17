<?php
/**
 * Settings page for plugin configuration
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
 * Settings class
 */
class Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page to admin menu
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Active Users Monitor Settings', 'fullworks-active-users-monitor' ),
			__( 'Active Users Monitor', 'fullworks-active-users-monitor' ),
			'manage_options',
			'fwaum-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'fwaum_settings_group',
			'fwaum_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);

		// General Settings Section.
		add_settings_section(
			'fwaum_general_section',
			esc_html__( 'General Settings', 'fullworks-active-users-monitor' ),
			array( $this, 'render_general_section' ),
			'fwaum-settings'
		);

		// Admin Bar setting.
		add_settings_field(
			'enable_admin_bar',
			esc_html__( 'Enable Admin Bar Counter', 'fullworks-active-users-monitor' ),
			array( $this, 'render_checkbox_field' ),
			'fwaum-settings',
			'fwaum_general_section',
			array(
				'field' => 'enable_admin_bar',
				'label' => esc_html__( 'Show online users counter in admin bar', 'fullworks-active-users-monitor' ),
			)
		);

		// Refresh interval setting.
		add_settings_field(
			'refresh_interval',
			esc_html__( 'Refresh Interval', 'fullworks-active-users-monitor' ),
			array( $this, 'render_number_field' ),
			'fwaum-settings',
			'fwaum_general_section',
			array(
				'field' => 'refresh_interval',
				'label' => esc_html__( 'seconds (15-300)', 'fullworks-active-users-monitor' ),
				'min'   => 15,
				'max'   => 300,
				'step'  => 5,
			)
		);

		// Dashboard widget setting.
		add_settings_field(
			'enable_dashboard',
			esc_html__( 'Enable Dashboard Widget', 'fullworks-active-users-monitor' ),
			array( $this, 'render_checkbox_field' ),
			'fwaum-settings',
			'fwaum_general_section',
			array(
				'field' => 'enable_dashboard',
				'label' => esc_html__( 'Show online users widget on dashboard', 'fullworks-active-users-monitor' ),
			)
		);

		// Display Settings Section.
		add_settings_section(
			'fwaum_display_section',
			esc_html__( 'Display Settings', 'fullworks-active-users-monitor' ),
			array( $this, 'render_display_section' ),
			'fwaum-settings'
		);

		// Show last seen setting.
		add_settings_field(
			'show_last_seen',
			esc_html__( 'Show Last Seen', 'fullworks-active-users-monitor' ),
			array( $this, 'render_checkbox_field' ),
			'fwaum-settings',
			'fwaum_display_section',
			array(
				'field' => 'show_last_seen',
				'label' => esc_html__( 'Display last seen time for offline users', 'fullworks-active-users-monitor' ),
			)
		);

		// Enable animations setting.
		add_settings_field(
			'enable_animations',
			esc_html__( 'Enable Animations', 'fullworks-active-users-monitor' ),
			array( $this, 'render_checkbox_field' ),
			'fwaum-settings',
			'fwaum_display_section',
			array(
				'field' => 'enable_animations',
				'label' => esc_html__( 'Enable pulse animations for online indicators', 'fullworks-active-users-monitor' ),
			)
		);

		// Permission Settings Section.
		add_settings_section(
			'fwaum_permission_section',
			esc_html__( 'Permission Settings', 'fullworks-active-users-monitor' ),
			array( $this, 'render_permission_section' ),
			'fwaum-settings'
		);

		// Roles that can see online status.
		add_settings_field(
			'view_roles',
			esc_html__( 'Who Can See Online Status', 'fullworks-active-users-monitor' ),
			array( $this, 'render_roles_field' ),
			'fwaum-settings',
			'fwaum_permission_section',
			array(
				'field' => 'view_roles',
			)
		);

		// Audit Trail Settings Section.
		add_settings_section(
			'fwaum_audit_section',
			esc_html__( 'Audit Trail Settings', 'fullworks-active-users-monitor' ),
			array( $this, 'render_audit_section' ),
			'fwaum-settings'
		);

		// Enable audit logging.
		add_settings_field(
			'enable_audit_log',
			esc_html__( 'Enable Audit Trail', 'fullworks-active-users-monitor' ),
			array( $this, 'render_checkbox_field' ),
			'fwaum-settings',
			'fwaum_audit_section',
			array(
				'field' => 'enable_audit_log',
				'label' => esc_html__( 'Track and log all user login/logout activities', 'fullworks-active-users-monitor' ),
			)
		);

		// Audit retention period.
		add_settings_field(
			'audit_retention_days',
			esc_html__( 'Retention Period', 'fullworks-active-users-monitor' ),
			array( $this, 'render_select_field' ),
			'fwaum-settings',
			'fwaum_audit_section',
			array(
				'field' => 'audit_retention_days',
				'label' => esc_html__( 'How long to keep audit log entries', 'fullworks-active-users-monitor' ),
				'options' => array(
					'30'  => esc_html__( '30 days', 'fullworks-active-users-monitor' ),
					'60'  => esc_html__( '60 days', 'fullworks-active-users-monitor' ),
					'90'  => esc_html__( '90 days', 'fullworks-active-users-monitor' ),
					'180' => esc_html__( '6 months', 'fullworks-active-users-monitor' ),
					'365' => esc_html__( '1 year', 'fullworks-active-users-monitor' ),
					'0'   => esc_html__( 'Never delete (indefinite)', 'fullworks-active-users-monitor' ),
				),
			)
		);

		// Track failed login attempts.
		add_settings_field(
			'audit_track_failed_logins',
			esc_html__( 'Track Failed Logins', 'fullworks-active-users-monitor' ),
			array( $this, 'render_checkbox_field' ),
			'fwaum-settings',
			'fwaum_audit_section',
			array(
				'field' => 'audit_track_failed_logins',
				'label' => esc_html__( 'Log failed login attempts for security monitoring', 'fullworks-active-users-monitor' ),
			)
		);

		// Anonymize IP addresses after period.
		add_settings_field(
			'audit_anonymize_ips_days',
			esc_html__( 'IP Address Privacy', 'fullworks-active-users-monitor' ),
			array( $this, 'render_select_field' ),
			'fwaum-settings',
			'fwaum_audit_section',
			array(
				'field' => 'audit_anonymize_ips_days',
				'label' => esc_html__( 'Anonymize IP addresses after specified period for privacy compliance', 'fullworks-active-users-monitor' ),
				'options' => array(
					'0'  => esc_html__( 'Never anonymize', 'fullworks-active-users-monitor' ),
					'7'  => esc_html__( '7 days', 'fullworks-active-users-monitor' ),
					'30' => esc_html__( '30 days', 'fullworks-active-users-monitor' ),
					'90' => esc_html__( '90 days', 'fullworks-active-users-monitor' ),
				),
			)
		);
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings.
	 */
	private function get_default_settings() {
		return array(
			'enable_admin_bar'           => true,
			'refresh_interval'           => 30,
			'enable_dashboard'           => true,
			'show_last_seen'             => true,
			'enable_animations'          => true,
			'view_roles'                 => array( 'administrator' ),
			'enable_audit_log'           => false,
			'audit_retention_days'       => 90,
			'audit_track_failed_logins'  => true,
			'audit_anonymize_ips_days'   => 30,
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Checkboxes.
		$sanitized['enable_admin_bar']           = ! empty( $input['enable_admin_bar'] );
		$sanitized['enable_dashboard']           = ! empty( $input['enable_dashboard'] );
		$sanitized['show_last_seen']             = ! empty( $input['show_last_seen'] );
		$sanitized['enable_animations']          = ! empty( $input['enable_animations'] );
		$sanitized['enable_audit_log']           = ! empty( $input['enable_audit_log'] );
		$sanitized['audit_track_failed_logins']  = ! empty( $input['audit_track_failed_logins'] );

		// Numbers.
		$sanitized['refresh_interval'] = isset( $input['refresh_interval'] ) ? absint( $input['refresh_interval'] ) : 30;
		$sanitized['refresh_interval'] = max( 15, min( 300, $sanitized['refresh_interval'] ) );

		$sanitized['audit_retention_days'] = isset( $input['audit_retention_days'] ) ? absint( $input['audit_retention_days'] ) : 90;
		$sanitized['audit_anonymize_ips_days'] = isset( $input['audit_anonymize_ips_days'] ) ? absint( $input['audit_anonymize_ips_days'] ) : 30;

		// Roles.
		$sanitized['view_roles'] = isset( $input['view_roles'] ) && is_array( $input['view_roles'] )
			? array_map( 'sanitize_text_field', $input['view_roles'] )
			: array( 'administrator' );

		return $sanitized;
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show success message if settings were saved.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress Settings API handles nonce verification internally. Reading GET parameter to display success message after settings save redirect.
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'fwaum_messages',
				'fwaum_message',
				esc_html__( 'Settings saved successfully.', 'fullworks-active-users-monitor' ),
				'updated'
			);
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'fwaum_messages' ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'fwaum_settings_group' );
				do_settings_sections( 'fwaum-settings' );
				submit_button();
				?>
			</form>

			<div class="fwaum-settings-info">
				<h2><?php esc_html_e( 'About Active Users Monitor', 'fullworks-active-users-monitor' ); ?></h2>
				<p><?php esc_html_e( 'This plugin provides real-time visibility of logged-in users using WordPress\'s native session tokens system.', 'fullworks-active-users-monitor' ); ?></p>
				<p><?php esc_html_e( 'Features include:', 'fullworks-active-users-monitor' ); ?></p>
				<ul>
					<li><?php esc_html_e( '• Real-time online user tracking', 'fullworks-active-users-monitor' ); ?></li>
					<li><?php esc_html_e( '• Admin bar counter with role breakdown', 'fullworks-active-users-monitor' ); ?></li>
					<li><?php esc_html_e( '• Visual indicators in users list', 'fullworks-active-users-monitor' ); ?></li>
					<li><?php esc_html_e( '• Filtering by online/offline status', 'fullworks-active-users-monitor' ); ?></li>
					<li><?php esc_html_e( '• Dashboard widget', 'fullworks-active-users-monitor' ); ?></li>
					<li><?php esc_html_e( '• WP-CLI support', 'fullworks-active-users-monitor' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render general section description
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general plugin settings.', 'fullworks-active-users-monitor' ) . '</p>';
	}

	/**
	 * Render display section description
	 */
	public function render_display_section() {
		echo '<p>' . esc_html__( 'Configure how online users are displayed.', 'fullworks-active-users-monitor' ) . '</p>';
	}

	/**
	 * Render permission section description
	 */
	public function render_permission_section() {
		echo '<p>' . esc_html__( 'Configure who can see online user status.', 'fullworks-active-users-monitor' ) . '</p>';
	}

	/**
	 * Render audit section description
	 */
	public function render_audit_section() {
		echo '<p>' . esc_html__( 'Configure audit trail settings to track user login/logout activities.', 'fullworks-active-users-monitor' ) . '</p>';

		// Show audit trail status and statistics if enabled.
		$options = get_option( 'fwaum_settings', $this->get_default_settings() );
		if ( ! empty( $options['enable_audit_log'] ) ) {
			if ( class_exists( '\\FullworksActiveUsersMonitor\\Includes\\Audit_Installer' ) ) {
				$stats = Audit_Installer::get_table_stats();
				echo '<div class="notice notice-info inline" style="margin: 10px 0;"><p>';
				printf(
					/* translators: 1: number of entries, 2: oldest entry date, 3: newest entry date */
					esc_html__( 'Audit trail is active with %1$d entries from %2$s to %3$s.', 'fullworks-active-users-monitor' ),
					esc_html( number_format_i18n( $stats['total_entries'] ) ),
					$stats['oldest_entry'] ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $stats['oldest_entry'] ) ) ) : esc_html__( 'N/A', 'fullworks-active-users-monitor' ),
					$stats['newest_entry'] ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $stats['newest_entry'] ) ) ) : esc_html__( 'N/A', 'fullworks-active-users-monitor' )
				);
				echo ' <a href="' . esc_url( admin_url( 'admin.php?page=fwaum-audit-log' ) ) . '">' . esc_html__( 'View Audit Log', 'fullworks-active-users-monitor' ) . '</a>';
				echo '</p></div>';
			}
		} else {
			echo '<div class="notice notice-warning inline" style="margin: 10px 0;"><p>' . esc_html__( 'Audit trail is currently disabled. Enable it to start tracking user activities.', 'fullworks-active-users-monitor' ) . '</p></div>';
		}
	}

	/**
	 * Render checkbox field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$options = get_option( 'fwaum_settings', $this->get_default_settings() );
		$field   = $args['field'];
		$value   = isset( $options[ $field ] ) ? $options[ $field ] : false;
		?>
		<label for="fwaum-<?php echo esc_attr( $field ); ?>">
			<input type="checkbox" 
					id="fwaum-<?php echo esc_attr( $field ); ?>" 
					name="fwaum_settings[<?php echo esc_attr( $field ); ?>]" 
					value="1" 
					<?php checked( $value, true ); ?> />
			<?php echo esc_html( $args['label'] ); ?>
		</label>
		<?php
	}

	/**
	 * Render number field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$options = get_option( 'fwaum_settings', $this->get_default_settings() );
		$field   = $args['field'];
		$value   = isset( $options[ $field ] ) ? $options[ $field ] : 30;
		?>
		<input type="number"
				id="fwaum-<?php echo esc_attr( $field ); ?>"
				name="fwaum_settings[<?php echo esc_attr( $field ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				min="<?php echo esc_attr( $args['min'] ); ?>"
				max="<?php echo esc_attr( $args['max'] ); ?>"
				step="<?php echo esc_attr( $args['step'] ); ?>" />
		<span class="description"><?php echo esc_html( $args['label'] ); ?></span>
		<?php
	}

	/**
	 * Render select field
	 *
	 * @param array $args Field arguments.
	 */
	public function render_select_field( $args ) {
		$options = get_option( 'fwaum_settings', $this->get_default_settings() );
		$field   = $args['field'];
		$value   = isset( $options[ $field ] ) ? $options[ $field ] : '';
		?>
		<select id="fwaum-<?php echo esc_attr( $field ); ?>"
				name="fwaum_settings[<?php echo esc_attr( $field ); ?>]">
			<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>"
						<?php selected( $value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['label'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['label'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render roles field
	 *
	 * @param array $args Field arguments. Not used but required by WordPress Settings API callback signature.
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found
	 */
	public function render_roles_field( $args ) {
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$options        = get_option( 'fwaum_settings', $this->get_default_settings() );
		$selected_roles = isset( $options['view_roles'] ) ? $options['view_roles'] : array( 'administrator' );
		$roles          = wp_roles()->roles;
		?>
		<fieldset>
			<?php foreach ( $roles as $role => $details ) : ?>
				<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox" 
							name="fwaum_settings[view_roles][]" 
							value="<?php echo esc_attr( $role ); ?>"
							<?php checked( in_array( $role, $selected_roles, true ) ); ?> />
					<?php echo esc_html( translate_user_role( $details['name'] ) ); ?>
				</label>
			<?php endforeach; ?>
			<p class="description">
				<?php esc_html_e( 'Select which roles can see online user status. Administrator role is always included.', 'fullworks-active-users-monitor' ); ?>
			</p>
		</fieldset>
		<?php
	}
}