<?php
/**
 * User Tracker Test
 *
 * @package FullworksActiveUsersMonitor
 */

namespace FullworksActiveUsersMonitor\Tests;

use FullworksActiveUsersMonitor\Includes\User_Tracker;
use WP_UnitTestCase;

/**
 * Class UserTrackerTest
 */
class UserTrackerTest extends WP_UnitTestCase {

	/**
	 * @var User_Tracker
	 */
	protected $user_tracker;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->user_tracker = new User_Tracker();
	}

	/**
	 * Tear down after each test.
	 */
	public function tearDown(): void {
		unset( $this->user_tracker );
		parent::tearDown();
	}

	/**
	 * Test get_user_last_activity() when timestamp exists.
	 */
	public function test_get_user_last_activity_exists() {
		$user_id = $this->factory()->user->create();
		$timestamp = time() - 300; // 5 minutes ago.
		update_user_meta( $user_id, 'fwaum_last_activity_timestamp', $timestamp );

		$last_activity = $this->user_tracker->get_user_last_activity( $user_id );
		$this->assertEquals( $timestamp, $last_activity );
	}

	/**
	 * Test get_user_last_activity() when timestamp does not exist.
	 */
	public function test_get_user_last_activity_not_exists() {
		$user_id = $this->factory()->user->create();
		$last_activity = $this->user_tracker->get_user_last_activity( $user_id );
		$this->assertFalse( $last_activity );
	}

	/**
	 * Test is_user_truly_active() when enabled and user is active.
	 */
	public function test_is_user_truly_active_enabled_and_active() {
		$user_id = $this->factory()->user->create();
		// Mock options.
		add_filter( 'pre_option_fwaum_settings', function( $value ) {
			return array_merge( (array) $value, [
				'fwaum_enable_true_activity'    => true,
				'fwaum_true_activity_threshold' => 5, // 5 minutes.
			] );
		});

		update_user_meta( $user_id, 'fwaum_last_activity_timestamp', time() - 60 ); // 1 minute ago.
		
		// Mock current time for test.
		$current_time = time();
		wp_test_set_current_time($current_time);

		$this->assertTrue( $this->user_tracker->is_user_truly_active( $user_id ) );
	}

	/**
	 * Test is_user_truly_active() when enabled and user is inactive.
	 */
	public function test_is_user_truly_active_enabled_and_inactive() {
		$user_id = $this->factory()->user->create();
		// Mock options.
		add_filter( 'pre_option_fwaum_settings', function( $value ) {
			return array_merge( (array) $value, [
				'fwaum_enable_true_activity'    => true,
				'fwaum_true_activity_threshold' => 5, // 5 minutes.
			] );
		});

		update_user_meta( $user_id, 'fwaum_last_activity_timestamp', time() - 360 ); // 6 minutes ago.
		
		// Mock current time for test.
		$current_time = time();
		wp_test_set_current_time($current_time);

		$this->assertFalse( $this->user_tracker->is_user_truly_active( $user_id ) );
	}

	/**
	 * Test is_user_truly_active() when enabled but no timestamp.
	 */
	public function test_is_user_truly_active_enabled_no_timestamp() {
		$user_id = $this->factory()->user->create();
		// Mock options.
		add_filter( 'pre_option_fwaum_settings', function( $value ) {
			return array_merge( (array) $value, [
				'fwaum_enable_true_activity'    => true,
				'fwaum_true_activity_threshold' => 5, // 5 minutes.
			] );
		});
		// No 'fwaum_last_activity_timestamp' set.
		
		// Mock current time for test.
		$current_time = time();
		wp_test_set_current_time($current_time);

		$this->assertFalse( $this->user_tracker->is_user_truly_active( $user_id ) );
	}

	/**
	 * Test is_user_truly_active() when disabled (should check session online status).
	 */
	public function test_is_user_truly_active_disabled_falls_back_to_session() {
		$user_id = $this->factory()->user->create();
		// Mock options (true activity disabled).
		add_filter( 'pre_option_fwaum_settings', function( $value ) {
			return array_merge( (array) $value, [
				'fwaum_enable_true_activity'    => false, // Disabled.
				'fwaum_true_activity_threshold' => 5,
			] );
		});

		// To make is_user_online return true, we need an active session token.
		// WP_Session_Tokens::get_instance() doesn't have a public setter for sessions.
		// We can directly manipulate user meta, but that bypasses WP_Session_Tokens logic.
		// For a unit test, we can create a mock User_Tracker or ensure the session.
		// For simplicity in a unit test, let's assume if true activity is disabled,
		// and the user has an active session (which factory user create might imply briefly).
		// Or we can mock the dependency.

		// Let's create a "logged in" state for the user for a session check.
		// This is a bit of a hack for unit tests.
		wp_set_current_user( $user_id );
		
		// Simulate a session token.
		\WP_Session_Tokens::get_instance( $user_id )->create( time() + DAY_IN_SECONDS ); // Valid for 1 day.

		$this->assertTrue( $this->user_tracker->is_user_truly_active( $user_id ) );

		// Clean up the session token if created manually.
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
	}

	/**
	 * Test get_formatted_last_seen() for 'Active now'.
	 */
	public function test_get_formatted_last_seen_active_now() {
		$user_id = $this->factory()->user->create();
		// Mock options.
		add_filter( 'pre_option_fwaum_settings', function( $value ) {
			return array_merge( (array) $value, [
				'fwaum_enable_true_activity'    => true,
				'fwaum_true_activity_threshold' => 5,
			] );
		});
		update_user_meta( $user_id, 'fwaum_last_activity_timestamp', time() - 60 ); // 1 minute ago.
		
		// Mock current time for test.
		$current_time = time();
		wp_test_set_current_time($current_time);

		$this->assertEquals( 'Active now', $this->user_tracker->get_formatted_last_seen( $user_id ) );
	}

	/**
	 * Test get_formatted_last_seen() for 'Online now' (session but not truly active).
	 */
	public function test_get_formatted_last_seen_online_now() {
		$user_id = $this->factory()->user->create();
		// Mock options (true activity enabled).
		add_filter( 'pre_option_fwaum_settings', function( $value ) {
			return array_merge( (array) $value, [
				'fwaum_enable_true_activity'    => true,
				'fwaum_true_activity_threshold' => 5,
			] );
		});
		// User is online (session exists)
		wp_set_current_user( $user_id );
		\WP_Session_Tokens::get_instance( $user_id )->create( time() + DAY_IN_SECONDS );
		
		// User is NOT truly active.
		update_user_meta( $user_id, 'fwaum_last_activity_timestamp', time() - 360 ); // 6 minutes ago.

		// Mock current time for test.
		$current_time = time();
		wp_test_set_current_time($current_time);

		$this->assertEquals( 'Online now', $this->user_tracker->get_formatted_last_seen( $user_id ) );

		// Clean up.
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
	}

	/**
	 * Test get_formatted_last_seen() for 'Just now' (offline but recently).
	 */
	public function test_get_formatted_last_seen_just_now() {
		$user_id = $this->factory()->user->create();
		// Mock options (true activity enabled, but user is offline).
		add_filter( 'pre_option_fwaum_settings', function( $value ) {
			return array_merge( (array) $value, [
				'fwaum_enable_true_activity'    => true,
				'fwaum_true_activity_threshold' => 5,
			] );
		});
		// User is offline, last login very recent.
		update_user_meta( $user_id, 'fwaum_last_login', time() - 30 ); // 30 seconds ago.
		
		// Mock current time for test.
		$current_time = time();
		wp_test_set_current_time($current_time);

		$this->assertEquals( 'Just now', $this->user_tracker->get_formatted_last_seen( $user_id ) );
	}
}