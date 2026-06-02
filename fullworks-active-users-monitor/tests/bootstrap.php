<?php
/**
 * PHPUnit bootstrap file for wp-env
 *
 * @package FullworksActiveUsersMonitor
 */

// Load Composer autoloader for phpunit-polyfills.
$plugin_dir = dirname( __DIR__ );
require_once $plugin_dir . '/includes/vendor/autoload.php';

// Set the path to the WordPress tests directory (wp-env provides this).
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
	if ( false !== $_phpunit_polyfills_path ) {
		define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
	} else {
		define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $plugin_dir . '/includes/vendor/yoast/phpunit-polyfills' );
	}
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	$plugin_file = dirname( __DIR__ ) . '/fullworks-active-users-monitor.php';
	if ( file_exists( $plugin_file ) ) {
		require $plugin_file;
	}
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';