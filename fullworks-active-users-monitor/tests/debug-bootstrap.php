<?php
/**
 * Debug bootstrap loading
 */

echo "=== Debug Bootstrap ===" . PHP_EOL;
echo "PHP Version: " . PHP_VERSION . PHP_EOL;

$plugin_dir = dirname( __DIR__ );
echo "Plugin dir: $plugin_dir" . PHP_EOL;

$autoload = $plugin_dir . '/includes/vendor/autoload.php';
echo "Autoload file exists: " . (file_exists($autoload) ? 'YES' : 'NO') . PHP_EOL;

if (file_exists($autoload)) {
    require_once $autoload;
    echo "Autoload loaded" . PHP_EOL;
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/wordpress-phpunit';
}
echo "Tests dir: $_tests_dir" . PHP_EOL;
echo "Tests dir exists: " . (is_dir($_tests_dir) ? 'YES' : 'NO') . PHP_EOL;

$functions_file = $_tests_dir . '/includes/functions.php';
echo "Functions file: $functions_file" . PHP_EOL;
echo "Functions file exists: " . (file_exists($functions_file) ? 'YES' : 'NO') . PHP_EOL;

if (file_exists($functions_file)) {
    require_once $functions_file;
    echo "Functions loaded" . PHP_EOL;
}

function _manually_load_plugin() {
    $plugin_file = dirname( __DIR__ ) . '/fullworks-active-users-monitor.php';
    echo "Loading plugin: $plugin_file" . PHP_EOL;
    if ( file_exists( $plugin_file ) ) {
        require $plugin_file;
        echo "Plugin loaded" . PHP_EOL;
    }
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

$bootstrap_file = $_tests_dir . '/includes/bootstrap.php';
echo "Bootstrap file: $bootstrap_file" . PHP_EOL;
echo "Bootstrap file exists: " . (file_exists($bootstrap_file) ? 'YES' : 'NO') . PHP_EOL;
echo "About to load WordPress test bootstrap..." . PHP_EOL;

require $bootstrap_file;

echo "Bootstrap loaded successfully!" . PHP_EOL;
