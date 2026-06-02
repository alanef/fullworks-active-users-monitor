<?php
/**
 * WordPress test configuration
 *
 * @package FullworksActiveUsersMonitor
 */

// Test database settings
define( 'DB_NAME', getenv( 'WORDPRESS_DB_NAME' ) ?: 'tests-wordpress' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ?: 'password' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ?: 'tests-mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// Test mode
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

// Debug settings
define( 'WP_DEBUG', true );

// Use default test config from WordPress
$table_prefix = 'wptests_';

// Load the default wp-tests-config if it exists
if ( file_exists( '/wordpress-phpunit/wp-tests-config.php' ) ) {
    // Don't load it, we've set everything we need above
}
