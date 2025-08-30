<?php
/**
 * WP-CLI Command for Active Users Monitor
 *
 * @package FullworksActiveUsersMonitor\Includes
 * @since 1.0.0
 */

namespace FullworksActiveUsersMonitor\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if WP-CLI is available.
if ( ! class_exists( 'WP_CLI_Command' ) ) {
	return;
}

/**
 * Manage and monitor active users via WP-CLI
 */
class CLI_Command extends \WP_CLI_Command {

	/**
	 * User tracker instance
	 *
	 * @var User_Tracker
	 */
	private $user_tracker;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->user_tracker = new User_Tracker();
	}

	/**
	 * List all online users
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - count
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * ## EXAMPLES
	 *
	 *     wp active-users list
	 *     wp active-users list --format=json
	 *     wp active-users list --fields=ID,user_login,display_name
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of named arguments.
	 * @subcommand list
	 */
	public function list_users( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		// Get online users.
		$online_user_ids = $this->user_tracker->get_online_users( false );

		if ( 'count' === $format ) {
			\WP_CLI::line( count( $online_user_ids ) );
			return;
		}

		if ( empty( $online_user_ids ) ) {
			\WP_CLI::line( 'No users are currently online.' );
			return;
		}

		// Build user data array.
		$users_data = array();
		foreach ( $online_user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$users_data[] = array(
				'ID'           => $user->ID,
				'user_login'   => $user->user_login,
				'display_name' => $user->display_name,
				'user_email'   => $user->user_email,
				'roles'        => implode( ', ', $user->roles ),
				'last_seen'    => $this->user_tracker->get_formatted_last_seen( $user_id ),
			);
		}

		// Determine fields to display.
		$fields = isset( $assoc_args['fields'] )
			? explode( ',', $assoc_args['fields'] )
			: array( 'ID', 'user_login', 'display_name', 'roles', 'last_seen' );

		// Output in requested format.
		\WP_CLI\Utils\format_items( $format, $users_data, $fields );
	}

	/**
	 * Get online user statistics
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp active-users stats
	 *     wp active-users stats --format=json
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of named arguments.
	 * @subcommand stats
	 */
	public function stats( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		// Get statistics.
		$online_count   = $this->user_tracker->get_online_user_count( false );
		$counts_by_role = $this->user_tracker->get_online_counts_by_role( false );
		$total_users    = count_users();

		// Build stats array.
		$stats = array(
			array(
				'metric' => 'Total Online',
				'value'  => $online_count,
			),
			array(
				'metric' => 'Total Users',
				'value'  => $total_users['total_users'],
			),
			array(
				'metric' => 'Percentage Online',
				'value'  => $total_users['total_users'] > 0
					? round( ( $online_count / $total_users['total_users'] ) * 100, 2 ) . '%'
					: '0%',
			),
		);

		// Add role breakdown.
		foreach ( $counts_by_role as $role => $count ) {
			$role_obj  = get_role( $role );
			$role_name = $role_obj ? translate_user_role( $role_obj->name ) : ucfirst( $role );
			$stats[]   = array(
				'metric' => $role_name . ' Online',
				'value'  => $count,
			);
		}

		// Output in requested format.
		\WP_CLI\Utils\format_items( $format, $stats, array( 'metric', 'value' ) );
	}

	/**
	 * Check if a specific user is online
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, login, or email.
	 *
	 * ## EXAMPLES
	 *
	 *     wp active-users check 1
	 *     wp active-users check admin
	 *     wp active-users check user@example.com
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of named arguments.
	 * @subcommand check
	 */
	public function check( $args, $assoc_args ) {
		$user_identifier = $args[0];

		// Get user by ID, login, or email.
		if ( is_numeric( $user_identifier ) ) {
			$user = get_user_by( 'id', $user_identifier );
		} elseif ( is_email( $user_identifier ) ) {
			$user = get_user_by( 'email', $user_identifier );
		} else {
			$user = get_user_by( 'login', $user_identifier );
		}

		if ( ! $user ) {
			\WP_CLI::error( 'User not found.' );
		}

		$is_online = $this->user_tracker->is_user_online( $user->ID );
		$last_seen = $this->user_tracker->get_formatted_last_seen( $user->ID );

		if ( $is_online ) {
			\WP_CLI::success(
				sprintf(
					'User "%s" (ID: %d) is currently online.',
					$user->user_login,
					$user->ID
				)
			);
		} else {
			\WP_CLI::line(
				sprintf(
					'User "%s" (ID: %d) is offline. Last seen: %s',
					$user->user_login,
					$user->ID,
					$last_seen
				)
			);
		}
	}

	/**
	 * Clear the online users cache
	 *
	 * ## EXAMPLES
	 *
	 *     wp active-users clear-cache
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of named arguments.
	 * @subcommand clear-cache
	 */
	public function clear_cache( $args, $assoc_args ) {
		$this->user_tracker->clear_cache();
		\WP_CLI::success( 'Online users cache cleared successfully.' );
	}

	/**
	 * Check if any users are active (for scripting)
	 *
	 * ## OPTIONS
	 *
	 * [--quiet]
	 * : Only return exit code (0 = users online, 1 = no users online).
	 *
	 * [--count]
	 * : Return the count of online users instead of yes/no.
	 *
	 * [--json]
	 * : Return JSON output with details.
	 *
	 * ## EXAMPLES
	 *
	 *     # Simple check for scripts
	 *     if wp active-users any --quiet; then
	 *         echo "Users are online, postponing upgrade"
	 *     else
	 *         echo "No users online, safe to upgrade"
	 *         wp core update
	 *     fi
	 *
	 *     # Get count
	 *     ONLINE_COUNT=$(wp active-users any --count)
	 *     if [ "$ONLINE_COUNT" -eq "0" ]; then
	 *         wp plugin update --all
	 *     fi
	 *
	 *     # Get JSON details
	 *     wp active-users any --json
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of named arguments.
	 * @subcommand any
	 */
	public function any( $args, $assoc_args ) {
		// Clear cache for fresh data.
		$this->user_tracker->clear_cache();

		// Get online users.
		$online_users = $this->user_tracker->get_online_users( false );
		$online_count = count( $online_users );

		// Handle different output modes.
		if ( isset( $assoc_args['quiet'] ) ) {
			// Silent mode for scripting - exit code only.
			exit( $online_count > 0 ? 0 : 1 );
		} elseif ( isset( $assoc_args['count'] ) ) {
			// Just output the count.
			\WP_CLI::line( $online_count );
		} elseif ( isset( $assoc_args['json'] ) ) {
			// JSON output with details.
			$counts_by_role = $this->user_tracker->get_online_counts_by_role( false );
			$output         = array(
				'online'         => $online_count > 0,
				'count'          => $online_count,
				'user_ids'       => $online_users,
				'counts_by_role' => $counts_by_role,
				'timestamp'      => current_time( 'mysql' ),
			);
			\WP_CLI::line( wp_json_encode( $output ) );
		} elseif ( $online_count > 0 ) {
			// Default human-readable output.
			\WP_CLI::success( sprintf( 'Yes - %d user(s) currently online', $online_count ) );
			exit( 0 );
		} else {
			\WP_CLI::line( 'No - no users currently online' );
			exit( 1 );
		}
	}

	/**
	 * Wait until no users are online
	 *
	 * ## OPTIONS
	 *
	 * [--timeout=<seconds>]
	 * : Maximum time to wait (0 for indefinite).
	 * ---
	 * default: 300
	 * ---
	 *
	 * [--check-interval=<seconds>]
	 * : How often to check for online users.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--quiet]
	 * : Suppress progress messages.
	 *
	 * ## EXAMPLES
	 *
	 *     # Wait for users to go offline before upgrading
	 *     wp active-users wait-clear && wp core update
	 *
	 *     # Wait up to 10 minutes for users to go offline
	 *     wp active-users wait-clear --timeout=600
	 *
	 *     # Check every 10 seconds with no timeout
	 *     wp active-users wait-clear --check-interval=10 --timeout=0
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of named arguments.
	 * @subcommand wait-clear
	 */
	public function wait_clear( $args, $assoc_args ) {
		$timeout        = isset( $assoc_args['timeout'] ) ? absint( $assoc_args['timeout'] ) : 300;
		$check_interval = isset( $assoc_args['check-interval'] ) ? absint( $assoc_args['check-interval'] ) : 30;
		$quiet          = isset( $assoc_args['quiet'] );

		if ( $check_interval < 5 ) {
			\WP_CLI::warning( 'Check interval set to minimum of 5 seconds.' );
			$check_interval = 5;
		}

		$start_time = time();
		$checks     = 0;

		if ( ! $quiet ) {
			\WP_CLI::line( 'Waiting for all users to go offline...' );
		}

		while ( true ) {
			++$checks;

			// Clear cache for fresh data.
			$this->user_tracker->clear_cache();
			$online_users = $this->user_tracker->get_online_users( false );
			$online_count = count( $online_users );

			if ( 0 === $online_count ) {
				if ( ! $quiet ) {
					\WP_CLI::success( 'All users are now offline.' );
				}
				exit( 0 );
			}

			// Check timeout.
			$elapsed = time() - $start_time;
			if ( $timeout > 0 && $elapsed >= $timeout ) {
				\WP_CLI::error( sprintf( 'Timeout reached after %d seconds. %d user(s) still online.', $timeout, $online_count ) );
			}

			if ( ! $quiet ) {
				$time_waiting = gmdate( 'H:i:s', $elapsed );
				\WP_CLI::line( sprintf( '[%s] Still waiting... %d user(s) online', $time_waiting, $online_count ) );
			}

			// Wait before next check.
			sleep( $check_interval );
		}
	}

	/**
	 * Monitor online users in real-time
	 *
	 * ## OPTIONS
	 *
	 * [--interval=<seconds>]
	 * : Refresh interval in seconds.
	 * ---
	 * default: 30
	 * ---
	 *
	 * [--duration=<seconds>]
	 * : How long to monitor (0 for indefinite).
	 * ---
	 * default: 0
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp active-users monitor
	 *     wp active-users monitor --interval=10
	 *     wp active-users monitor --interval=5 --duration=60
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of named arguments.
	 * @subcommand monitor
	 */
	public function monitor( $args, $assoc_args ) {
		$interval = isset( $assoc_args['interval'] ) ? absint( $assoc_args['interval'] ) : 30;
		$duration = isset( $assoc_args['duration'] ) ? absint( $assoc_args['duration'] ) : 0;

		if ( $interval < 5 ) {
			\WP_CLI::warning( 'Interval set to minimum of 5 seconds.' );
			$interval = 5;
		}

		$start_time = time();
		$iteration  = 0;

		\WP_CLI::line( 'Monitoring online users... Press Ctrl+C to stop.' );
		\WP_CLI::line( '' );

		while ( true ) {
			++$iteration;

			// Clear screen (works on most terminals).
			if ( $iteration > 1 ) {
				// Use ANSI escape codes to clear screen instead of passthru.
				echo "\033[2J\033[H";
			}

			// Get fresh data.
			$this->user_tracker->clear_cache();
			$online_users   = $this->user_tracker->get_online_users( false );
			$counts_by_role = $this->user_tracker->get_online_counts_by_role( false );

			// Display header.
			\WP_CLI::line( '┌─────────────────────────────────────────┐' );
			\WP_CLI::line( '│     ACTIVE USERS MONITOR                │' );
			\WP_CLI::line( '├─────────────────────────────────────────┤' );
			\WP_CLI::line( sprintf( '│ Time: %-34s │', current_time( 'Y-m-d H:i:s' ) ) );
			\WP_CLI::line( sprintf( '│ Total Online: %-26s │', count( $online_users ) ) );
			\WP_CLI::line( '└─────────────────────────────────────────┘' );
			\WP_CLI::line( '' );

			// Display role breakdown.
			if ( ! empty( $counts_by_role ) ) {
				\WP_CLI::line( 'By Role:' );
				foreach ( $counts_by_role as $role => $count ) {
					$role_obj  = get_role( $role );
					$role_name = $role_obj ? translate_user_role( $role_obj->name ) : ucfirst( $role );
					\WP_CLI::line( sprintf( '  • %-20s %d', $role_name . ':', $count ) );
				}
				\WP_CLI::line( '' );
			}

			// Display online users.
			if ( ! empty( $online_users ) ) {
				\WP_CLI::line( 'Online Users:' );
				foreach ( array_slice( $online_users, 0, 10 ) as $user_id ) {
					$user = get_userdata( $user_id );
					if ( $user ) {
						\WP_CLI::line(
							sprintf(
								'  • %-20s (%s)',
								$user->user_login,
								implode( ', ', $user->roles )
							)
						);
					}
				}

				if ( count( $online_users ) > 10 ) {
					\WP_CLI::line( sprintf( '  ... and %d more', count( $online_users ) - 10 ) );
				}
			} else {
				\WP_CLI::line( 'No users currently online.' );
			}

			// Check duration.
			if ( $duration > 0 && ( time() - $start_time ) >= $duration ) {
				\WP_CLI::line( '' );
				\WP_CLI::success( 'Monitoring complete.' );
				break;
			}

			// Wait for next iteration.
			sleep( $interval );
		}
	}
}
