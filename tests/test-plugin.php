<?php
/**
 * Plugin tests.
 *
 * @package FullworksActiveUsersMonitor
 */

use FullworksActiveUsersMonitor\Includes\Audit_Installer;
use FullworksActiveUsersMonitor\Includes\Audit_Logger;
use FullworksActiveUsersMonitor\Includes\Privacy;
use FullworksActiveUsersMonitor\Includes\User_Tracker;

/**
 * Plugin behaviour tests.
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * Reset the audit table and settings before each test.
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Audit_Installer::get_table_name() ) );

		update_option(
			'fwaum_settings',
			array(
				'enable_audit_log'          => true,
				'audit_track_failed_logins' => true,
				'view_roles'                => array( 'administrator' ),
			)
		);
		delete_transient( User_Tracker::CACHE_KEY );
	}

	/**
	 * Tear down: restore server vars touched by tests.
	 */
	public function tear_down() {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_COOKIE[ LOGGED_IN_COOKIE ] );
		remove_all_filters( 'fwaum_client_ip_headers' );
		parent::tear_down();
	}

	/**
	 * Count audit rows of a given event type.
	 *
	 * @param string $event_type Event type.
	 * @return int
	 */
	private function count_events( $event_type ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE event_type = %s', Audit_Installer::get_table_name(), $event_type ) );
	}

	/**
	 * The version constant matches the plugin header.
	 */
	public function test_version_constant_matches_header() {
		$data = get_file_data( FWAUM_PLUGIN_FILE, array( 'Version' => 'Version' ) );
		$this->assertSame( $data['Version'], FWAUM_VERSION );
	}

	/**
	 * Users with an unexpired session are online; expired or no session are not.
	 */
	public function test_online_users_come_from_unexpired_sessions() {
		$online  = self::factory()->user->create();
		$expired = self::factory()->user->create();
		$never   = self::factory()->user->create();

		WP_Session_Tokens::get_instance( $online )->create( time() + HOUR_IN_SECONDS );
		update_user_meta(
			$expired,
			'session_tokens',
			array(
				hash( 'sha256', 'old' ) => array(
					'expiration' => time() - HOUR_IN_SECONDS,
					'login'      => time() - DAY_IN_SECONDS,
				),
			)
		);

		$ids = ( new User_Tracker() )->get_online_users( false );

		$this->assertContains( $online, $ids );
		$this->assertNotContains( $expired, $ids );
		$this->assertNotContains( $never, $ids );
	}

	/**
	 * Administrators always see online status; other roles only when chosen.
	 */
	public function test_view_roles_setting_is_enforced() {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $editor );
		$this->assertFalse( User_Tracker::current_user_can_view() );

		$options               = get_option( 'fwaum_settings' );
		$options['view_roles'] = array( 'administrator', 'editor' );
		update_option( 'fwaum_settings', $options );
		$this->assertTrue( User_Tracker::current_user_can_view() );

		wp_set_current_user( $admin );
		$options['view_roles'] = array();
		update_option( 'fwaum_settings', $options );
		$this->assertTrue( User_Tracker::current_user_can_view() );
	}

	/**
	 * An expired auth cookie must not send WordPress into infinite recursion
	 * while it works out the current user, and a genuine one is logged once.
	 */
	public function test_expired_cookie_is_logged_once_without_recursion() {
		global $current_user;

		$user_id = self::factory()->user->create();
		$cookie  = wp_generate_auth_cookie( $user_id, time() - 10, 'logged_in', 'tok' . wp_generate_password( 20, false ) );

		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
		$current_user                = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Force WordPress to determine the user from the cookie.

		$this->assertSame( 0, get_current_user_id() );
		$this->assertSame( 1, $this->count_events( 'session_expired' ) );

		// The browser resends the same cookie on the next request.
		$this->assertFalse( wp_validate_auth_cookie( $cookie, 'logged_in' ) );
		$this->assertSame( 1, $this->count_events( 'session_expired' ) );
	}

	/**
	 * A forged expired cookie is not logged.
	 */
	public function test_forged_expired_cookie_is_not_logged() {
		$user   = get_userdata( self::factory()->user->create() );
		$cookie = $user->user_login . '|' . ( time() - 10 ) . '|forgedtoken|' . str_repeat( 'a', 64 );

		$this->assertFalse( wp_validate_auth_cookie( $cookie, 'logged_in' ) );
		$this->assertSame( 0, $this->count_events( 'session_expired' ) );
	}

	/**
	 * Failed logins respect the "Track Failed Logins" setting.
	 */
	public function test_failed_login_setting_is_respected() {
		do_action( 'wp_login_failed', 'nobody' );
		$this->assertSame( 1, $this->count_events( 'failed_login' ) );

		$options                              = get_option( 'fwaum_settings' );
		$options['audit_track_failed_logins'] = false;
		update_option( 'fwaum_settings', $options );

		do_action( 'wp_login_failed', 'nobody' );
		$this->assertSame( 1, $this->count_events( 'failed_login' ) );
	}

	/**
	 * Over-long usernames are still logged rather than failing the insert.
	 */
	public function test_long_username_is_logged() {
		do_action( 'wp_login_failed', str_repeat( 'x', 200 ) );
		$this->assertSame( 1, $this->count_events( 'failed_login' ) );
	}

	/**
	 * Client-supplied forwarding headers are ignored unless trusted by filter.
	 */
	public function test_forwarded_ip_is_not_trusted_by_default() {
		global $wpdb;
		$_SERVER['REMOTE_ADDR']          = '198.51.100.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99';

		do_action( 'wp_login_failed', 'nobody' );
		$ip = $wpdb->get_var( $wpdb->prepare( 'SELECT ip_address FROM %i ORDER BY id DESC LIMIT 1', Audit_Installer::get_table_name() ) );
		$this->assertSame( '198.51.100.7', $ip );

		add_filter(
			'fwaum_client_ip_headers',
			function () {
				return array( 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
			}
		);
		do_action( 'wp_login_failed', 'nobody' );
		$ip = $wpdb->get_var( $wpdb->prepare( 'SELECT ip_address FROM %i ORDER BY id DESC LIMIT 1', Audit_Installer::get_table_name() ) );
		$this->assertSame( '203.0.113.99', $ip );
	}

	/**
	 * IP addresses older than the anonymize period are anonymized; newer ones are not.
	 */
	public function test_old_ips_are_anonymized() {
		global $wpdb;
		$table = Audit_Installer::get_table_name();
		$row   = array(
			'user_id'      => 0,
			'username'     => 'someone',
			'display_name' => 'someone',
			'event_type'   => 'login',
			'user_agent'   => 'test',
			'login_method' => 'standard',
		);
		$wpdb->insert( $table, $row + array( 'timestamp' => wp_date( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ), 'ip_address' => '203.0.113.45' ) );
		$wpdb->insert( $table, $row + array( 'timestamp' => wp_date( 'Y-m-d H:i:s' ), 'ip_address' => '203.0.113.46' ) );

		Audit_Logger::anonymize_old_ips( 30 );

		$ips = $wpdb->get_col( $wpdb->prepare( 'SELECT ip_address FROM %i ORDER BY id', $table ) );
		$this->assertSame( array( '203.0.113.0', '203.0.113.46' ), $ips );
	}

	/**
	 * Spreadsheet formulas in exported values are neutralised.
	 */
	public function test_csv_values_cannot_be_formulas() {
		$exporter = new FullworksActiveUsersMonitor\Includes\Audit_Exporter();
		$method   = new ReflectionMethod( $exporter, 'csv_safe' );
		$method->setAccessible( true );

		$this->assertSame( "'=HYPERLINK(\"http://x\")", $method->invoke( $exporter, '=HYPERLINK("http://x")' ) );
		$this->assertSame( "'@SUM(1)", $method->invoke( $exporter, '@SUM(1)' ) );
		$this->assertSame( 'alice', $method->invoke( $exporter, 'alice' ) );
	}

	/**
	 * The personal data exporter returns the user's entries and the eraser removes them.
	 */
	public function test_privacy_export_and_erase() {
		$user = get_userdata( self::factory()->user->create() );
		do_action( 'wp_login_failed', $user->user_login );
		do_action( 'wp_login_failed', 'someone-else' );

		$privacy = new Privacy();
		$export  = $privacy->export_personal_data( $user->user_email );
		$this->assertCount( 1, $export['data'] );
		$this->assertTrue( $export['done'] );

		$erase = $privacy->erase_personal_data( $user->user_email );
		$this->assertTrue( $erase['items_removed'] );
		$this->assertSame( 1, $this->count_events( 'failed_login' ) );
	}
}
