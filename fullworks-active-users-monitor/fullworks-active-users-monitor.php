<?php
/**
 * Plugin Name:       Fullworks Active Users Monitor
 * Plugin URI:        https://fullworks.net/products/active-users-monitor/
 * Description:       Provides real-time visibility of logged-in users for administrators with visual indicators and filtering capabilities.
 * Version:           1.0.2
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Fullworks
 * Author URI:        https://fullworks.net/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       fullworks-active-users-monitor
 * Domain Path:       /languages
 *
 * @package FullworksActiveUsersMonitor
 */

namespace FullworksActiveUsersMonitor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants with unique prefix (minimum 4 characters).
define( 'FWAUM_VERSION', '1.0.2' );
define( 'FWAUM_PLUGIN_FILE', __FILE__ );
define( 'FWAUM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'FWAUM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FWAUM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Require Composer autoloader if it exists.
if ( file_exists( FWAUM_PLUGIN_PATH . 'includes/vendor/autoload.php' ) ) {
	require_once FWAUM_PLUGIN_PATH . 'includes/vendor/autoload.php';
}

// Manually include required files if autoloader not available.
require_once FWAUM_PLUGIN_PATH . 'includes/class-user-tracker.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-admin-bar.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-users-list.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-ajax-handler.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-settings.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-dashboard-widget.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-cli-command.php';

// Audit trail classes.
require_once FWAUM_PLUGIN_PATH . 'includes/class-audit-installer.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-audit-logger.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-audit-table.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-audit-exporter.php';
require_once FWAUM_PLUGIN_PATH . 'includes/class-audit-admin.php';

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * User tracker instance
	 *
	 * @var Includes\User_Tracker
	 */
	private $user_tracker;

	/**
	 * Admin bar instance
	 *
	 * @var Includes\Admin_Bar
	 */
	private $admin_bar;

	/**
	 * Users list instance
	 *
	 * @var Includes\Users_List
	 */
	private $users_list;

	/**
	 * Ajax handler instance
	 *
	 * @var Includes\Ajax_Handler
	 */
	private $ajax_handler;

	/**
	 * Settings instance
	 *
	 * @var Includes\Settings
	 */
	private $settings;

	/**
	 * Dashboard widget instance
	 *
	 * @var Includes\Dashboard_Widget
	 */
	private $dashboard_widget;

	/**
	 * Audit logger instance
	 *
	 * @var Includes\Audit_Logger
	 */
	private $audit_logger;

	/**
	 * Audit exporter instance
	 *
	 * @var Includes\Audit_Exporter
	 */
	private $audit_exporter;

	/**
	 * Audit admin instance
	 *
	 * @var Includes\Audit_Admin
	 */
	private $audit_admin;

	/**
	 * Get singleton instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		// Initialize components.
		$this->user_tracker     = new Includes\User_Tracker();
		$this->admin_bar        = new Includes\Admin_Bar( $this->user_tracker );
		$this->users_list       = new Includes\Users_List( $this->user_tracker );
		$this->ajax_handler     = new Includes\Ajax_Handler( $this->user_tracker );
		$this->settings         = new Includes\Settings();
		$this->dashboard_widget = new Includes\Dashboard_Widget( $this->user_tracker );

		// Initialize audit trail components.
		$this->audit_logger   = new Includes\Audit_Logger();
		$this->audit_exporter = new Includes\Audit_Exporter();
		$this->audit_admin    = new Includes\Audit_Admin();

		// Register hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_assets' ) );
		add_filter( 'plugin_action_links_' . FWAUM_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

		// Register WP-CLI command if available.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'active-users', __NAMESPACE__ . '\\Includes\\CLI_Command' );
		}

		// Check if audit trail database needs update.
		add_action( 'admin_init', array( $this, 'maybe_update_audit_database' ) );
	}


	/**
	 * Maybe update audit trail database
	 */
	public function maybe_update_audit_database() {
		if ( Includes\Audit_Installer::needs_update() ) {
			Includes\Audit_Installer::install();
		}
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Load on users page, settings page, dashboard, and audit pages.
		$allowed_hooks = array(
			'users.php',
			'settings_page_fwaum-settings',
			'index.php',
			'toplevel_page_fwaum-audit-log',
			'audit-log_page_fwaum-audit-export',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'fwaum-admin',
			FWAUM_PLUGIN_URL . 'assets/css/admin-style.css',
			array(),
			FWAUM_VERSION
		);

		wp_enqueue_script(
			'fwaum-admin',
			FWAUM_PLUGIN_URL . 'assets/js/admin-script.js',
			array( 'jquery' ),
			FWAUM_VERSION,
			true
		);

		$options          = get_option( 'fwaum_settings', array() );
		$refresh_interval = isset( $options['refresh_interval'] ) ? absint( $options['refresh_interval'] ) : 30;

		wp_localize_script(
			'fwaum-admin',
			'fwaumAjax',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'fwaum_ajax_nonce' ),
				'refreshInterval' => $refresh_interval * 1000, // Convert to milliseconds.
				'strings'         => array(
					'error' => __( 'An error occurred while updating online users.', 'fullworks-active-users-monitor' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin bar assets
	 */
	public function enqueue_admin_bar_assets() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'list_users' ) ) {
			return;
		}

		wp_enqueue_style(
			'fwaum-admin-bar',
			FWAUM_PLUGIN_URL . 'assets/css/admin-bar.css',
			array(),
			FWAUM_VERSION
		);
	}

	/**
	 * Add settings link to plugin actions
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=fwaum-settings' ) ),
			__( 'Settings', 'fullworks-active-users-monitor' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}

// Initialize plugin.
add_action(
	'plugins_loaded',
	function () {
		Plugin::get_instance();
	}
);

// Activation hook.
register_activation_hook(
	__FILE__,
	function () {
		// Set default options.
		$default_options = array(
			'enable_admin_bar'          => true,
			'refresh_interval'          => 30,
			'enable_dashboard'          => true,
			'show_last_seen'            => true,
			'enable_animations'         => true,
			'enable_audit_log'          => false,
			'audit_retention_days'      => 90,
			'audit_track_failed_logins' => true,
			'audit_anonymize_ips_days'  => 30,
		);

		if ( false === get_option( 'fwaum_settings' ) ) {
			add_option( 'fwaum_settings', $default_options );
		}

		// Install audit trail database table.
		Includes\Audit_Installer::install();

		// Clear any transients.
		delete_transient( 'fwaum_online_users_cache' );
	}
);

// Deactivation hook.
register_deactivation_hook(
	__FILE__,
	function () {
		// Clear transients.
		delete_transient( 'fwaum_online_users_cache' );
	}
);
