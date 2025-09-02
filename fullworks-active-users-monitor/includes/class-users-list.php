<?php
/**
 * Users List enhancements
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
 * Users List class
 */
class Users_List {

	/**
	 * User tracker instance
	 *
	 * @var User_Tracker
	 */
	private $user_tracker;

	/**
	 * Constructor
	 *
	 * @param User_Tracker $user_tracker User tracker instance.
	 */
	public function __construct( User_Tracker $user_tracker ) {
		$this->user_tracker = $user_tracker;

		// Add hooks only for admin users page.
		add_action( 'admin_init', array( $this, 'init_users_list' ) );
		add_filter( 'views_users', array( $this, 'add_online_filter_links' ) );
		add_filter( 'pre_get_users', array( $this, 'filter_users_query' ) );
	}

	/**
	 * Initialize users list modifications
	 */
	public function init_users_list() {
		// Check if we're on the users page.
		global $pagenow;
		if ( 'users.php' !== $pagenow ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'list_users' ) ) {
			return;
		}

		// Add columns.
		add_filter( 'manage_users_columns', array( $this, 'add_online_status_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_online_status_column' ), 10, 3 );
		add_filter( 'manage_users_sortable_columns', array( $this, 'make_online_status_sortable' ) );

		// Enqueue scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add stats summary.
		add_action( 'admin_notices', array( $this, 'display_online_stats' ) );
	}

	/**
	 * Add online status column
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_online_status_column( $columns ) {
		// Add after username column.
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'username' === $key ) {
				$new_columns['fwaum_status'] = esc_html__( 'Online Status', 'fullworks-active-users-monitor' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render online status column
	 *
	 * @param string $output Custom column output.
	 * @param string $column_name Column name.
	 * @param int    $user_id User ID.
	 * @return string Column content.
	 */
	public function render_online_status_column( $output, $column_name, $user_id ) {
		if ( 'fwaum_status' !== $column_name ) {
			return $output;
		}

		$is_online      = $this->user_tracker->is_user_online( $user_id );
		$last_seen      = $this->user_tracker->get_formatted_last_seen( $user_id );
		$options        = get_option( 'fwaum_settings', array() );
		$show_last_seen = isset( $options['show_last_seen'] ) ? $options['show_last_seen'] : true;

		$status_class = $is_online ? 'online' : 'offline';
		$status_icon  = $is_online ? '●' : '○';
		$status_text  = $is_online ? esc_html__( 'Online', 'fullworks-active-users-monitor' ) : esc_html__( 'Offline', 'fullworks-active-users-monitor' );

		$output = sprintf(
			'<span class="fwaum-status-indicator fwaum-status-%1$s" data-user-id="%2$d">
				<span class="fwaum-status-dot">%3$s</span>
				<span class="fwaum-status-text">%4$s</span>',
			esc_attr( $status_class ),
			esc_attr( $user_id ),
			esc_html( $status_icon ),
			esc_html( $status_text )
		);

		if ( $show_last_seen && ! $is_online ) {
			$output .= sprintf(
				'<span class="fwaum-last-seen" title="%1$s">%2$s</span>',
				esc_attr( $last_seen ),
				esc_html( $last_seen )
			);
		}

		$output .= '</span>';

		// Store data for JavaScript to process later.
		if ( $is_online ) {
			$user      = get_userdata( $user_id );
			$user_role = $user->roles[0] ?? 'subscriber';

			// Add data attribute for JavaScript to process.
			$output .= sprintf(
				'<span class="fwaum-online-data" data-user-id="%1$d" data-user-role="%2$s" data-badge-text="%3$s" style="display:none;"></span>',
				esc_attr( $user_id ),
				esc_attr( $user_role ),
				esc_attr( esc_html__( 'ONLINE', 'fullworks-active-users-monitor' ) )
			);
		}

		return $output;
	}

	/**
	 * Make online status column sortable
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified columns.
	 */
	public function make_online_status_sortable( $columns ) {
		$columns['fwaum_status'] = 'fwaum_status';
		return $columns;
	}

	/**
	 * Add online/offline filter links
	 *
	 * @param array $views Existing view links.
	 * @return array Modified view links.
	 */
	public function add_online_filter_links( $views ) {
		// Get counts.
		$total_users   = count_users();
		$online_users  = $this->user_tracker->get_online_users();
		$online_count  = count( $online_users );
		$offline_count = $total_users['total_users'] - $online_count;

		// Get current filter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for filter display
		$current_filter = isset( $_GET['fwaum_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['fwaum_filter'] ) ) : '';

		// Build URLs preserving other parameters.
		$base_url = admin_url( 'users.php' );
		// Build safe URL parameters, only preserving known safe parameters.
		$url_params = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
		if ( isset( $_GET['orderby'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
			$url_params['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
		if ( isset( $_GET['order'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
			$url_params['order'] = sanitize_text_field( wp_unslash( $_GET['order'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
		if ( isset( $_GET['role'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
			$url_params['role'] = sanitize_text_field( wp_unslash( $_GET['role'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
		if ( isset( $_GET['s'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
			$url_params['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}

		// Online link.
		$url_params['fwaum_filter'] = 'online';
		$online_url                 = add_query_arg( $url_params, $base_url );
		$online_class               = ( 'online' === $current_filter ) ? 'current' : '';
		$views['fwaum_online']      = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( $online_url ),
			esc_attr( $online_class ),
			esc_html__( 'Online', 'fullworks-active-users-monitor' ),
			number_format_i18n( $online_count )
		);

		// Offline link.
		$url_params['fwaum_filter'] = 'offline';
		$offline_url                = add_query_arg( $url_params, $base_url );
		$offline_class              = ( 'offline' === $current_filter ) ? 'current' : '';
		$views['fwaum_offline']     = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( $offline_url ),
			esc_attr( $offline_class ),
			esc_html__( 'Offline', 'fullworks-active-users-monitor' ),
			number_format_i18n( $offline_count )
		);

		// If we're filtering by online status, update the "All" link to remove our filter.
		if ( ! empty( $current_filter ) ) {
			unset( $url_params['fwaum_filter'] );
			$all_url = ! empty( $url_params ) ? add_query_arg( $url_params, $base_url ) : $base_url;

			// Find and update the "All" link to remove the current class if we're filtering.
			if ( isset( $views['all'] ) ) {
				$views['all'] = str_replace( 'class="current"', '', $views['all'] );
			}
		}

		return $views;
	}

	/**
	 * Filter users query based on online status
	 *
	 * @param object $query WP_User_Query instance.
	 */
	public function filter_users_query( $query ) {
		global $pagenow;

		if ( 'users.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for query filtering
		if ( ! isset( $_GET['fwaum_filter'] ) || empty( $_GET['fwaum_filter'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for query filtering
		$filter = sanitize_text_field( wp_unslash( $_GET['fwaum_filter'] ) );

		if ( 'online' === $filter ) {
			$online_users = $this->user_tracker->get_online_users();
			if ( empty( $online_users ) ) {
				// Force no results.
				$query->set( 'include', array( 0 ) );
			} else {
				$query->set( 'include', $online_users );
			}
		} elseif ( 'offline' === $filter ) {
			$online_users = $this->user_tracker->get_online_users();
			if ( ! empty( $online_users ) ) {
				// Note: Using 'exclude' parameter is necessary here to show offline users.
				// While this can impact performance on sites with many users, it's required
				// for accurate filtering. The impact is mitigated by our caching strategy.
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Required for offline users filter
				$query->set( 'exclude', $online_users );
			}
		}
	}

	/**
	 * Display online stats summary
	 */
	public function display_online_stats() {
		global $pagenow;

		if ( 'users.php' !== $pagenow ) {
			return;
		}

		$online_count   = $this->user_tracker->get_online_user_count();
		$counts_by_role = $this->user_tracker->get_online_counts_by_role();

		// Build role summary.
		$role_summary = array();
		foreach ( $counts_by_role as $role => $count ) {
			if ( $count > 0 ) {
				$role_obj  = get_role( $role );
				$role_name = $role_obj ? translate_user_role( $role_obj->name ) : ucfirst( $role );
				/* translators: 1: Count, 2: Role name */
				$role_summary[] = sprintf( _n( '%1$d %2$s', '%1$d %2$s', $count, 'fullworks-active-users-monitor' ), $count, $role_name );
			}
		}

		$summary_text = ! empty( $role_summary ) ? implode( ', ', $role_summary ) : esc_html__( 'No users online', 'fullworks-active-users-monitor' );

		?>
		<div class="notice notice-info fwaum-stats-notice">
			<p>
				<strong><?php esc_html_e( 'Online Users:', 'fullworks-active-users-monitor' ); ?></strong>
				<span class="fwaum-stats-summary">
					<?php
					/* translators: %d: Total online users */
					printf( esc_html__( '%d users online', 'fullworks-active-users-monitor' ), esc_html( $online_count ) );
					if ( ! empty( $role_summary ) ) {
						echo ' (' . esc_html( $summary_text ) . ')';
					}
					?>
				</span>
				<span class="fwaum-stats-updated">
					<?php esc_html_e( 'Last updated:', 'fullworks-active-users-monitor' ); ?>
					<span class="fwaum-update-time"><?php echo esc_html( current_time( 'g:i:s a' ) ); ?></span>
				</span>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles for users list
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'users.php' !== $hook ) {
			return;
		}

		$options           = get_option( 'fwaum_settings', array() );
		$enable_animations = isset( $options['enable_animations'] ) ? $options['enable_animations'] : true;

		// Enqueue CSS.
		wp_enqueue_style(
			'fwaum-users-list',
			plugin_dir_url( __DIR__ ) . 'admin/css/users-list.css',
			array(),
			FWAUM_VERSION
		);

		// Add class to body tag for animations.
		if ( $enable_animations ) {
			wp_add_inline_style( 'fwaum-users-list', 'body { --fwaum-animations: enabled; }' );
			add_filter(
				'admin_body_class',
				function ( $classes ) {
					return $classes . ' fwaum-animations-enabled';
				}
			);
		}

		// Enqueue JavaScript.
		wp_enqueue_script(
			'fwaum-users-list',
			plugin_dir_url( __DIR__ ) . 'admin/js/users-list.js',
			array( 'jquery' ),
			FWAUM_VERSION,
			true
		);

		// Collect online users data for JavaScript.
		$online_users      = $this->user_tracker->get_online_users();
		$online_users_data = array();
		foreach ( $online_users as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$online_users_data[] = array(
					'user_id'    => $user_id,
					'user_role'  => $user->roles[0] ?? 'subscriber',
					'badge_text' => esc_html__( 'ONLINE', 'fullworks-active-users-monitor' ),
				);
			}
		}

		// Localize script with data.
		wp_localize_script(
			'fwaum-users-list',
			'fwaum_users_list',
			array(
				'online_users' => $online_users_data,
			)
		);
	}
}
