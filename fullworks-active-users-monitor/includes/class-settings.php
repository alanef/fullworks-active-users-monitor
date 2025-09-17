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

		// Initialize free plugin library.
		if ( class_exists( '\\Fullworks_Free_Plugin_Lib\\Main' ) ) {
			new \Fullworks_Free_Plugin_Lib\Main(
				'fullworks-active-users-monitor/fullworks-active-users-monitor.php',
				admin_url( 'options-general.php?page=fwaum-settings' ),
				'FWAUM-Free',
				'settings_page_fwaum-settings',
				__( 'Active Users Monitor Settings', 'fullworks-active-users-monitor' )
			);
		}
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
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings.
	 */
	private function get_default_settings() {
		return array(
			'enable_admin_bar'  => true,
			'refresh_interval'  => 30,
			'enable_dashboard'  => true,
			'show_last_seen'    => true,
			'enable_animations' => true,
			'view_roles'        => array( 'administrator' ),
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
		$sanitized['enable_admin_bar']  = ! empty( $input['enable_admin_bar'] );
		$sanitized['enable_dashboard']  = ! empty( $input['enable_dashboard'] );
		$sanitized['show_last_seen']    = ! empty( $input['show_last_seen'] );
		$sanitized['enable_animations'] = ! empty( $input['enable_animations'] );

		// Numbers.
		$sanitized['refresh_interval'] = isset( $input['refresh_interval'] ) ? absint( $input['refresh_interval'] ) : 30;
		$sanitized['refresh_interval'] = max( 15, min( 300, $sanitized['refresh_interval'] ) );

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
				<?php do_action( 'ffpl_ad_display' ); ?>
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