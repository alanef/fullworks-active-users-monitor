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

		// Add inline styles for visual indicators.
		add_action( 'admin_head', array( $this, 'add_inline_styles' ) );

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
				$new_columns['fwaum_status'] = __( 'Online Status', 'fullworks-active-users-monitor' );
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
		$status_text  = $is_online ? __( 'Online', 'fullworks-active-users-monitor' ) : __( 'Offline', 'fullworks-active-users-monitor' );

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

		// Add badge next to username via JavaScript.
		if ( $is_online ) {
			$user      = get_userdata( $user_id );
			$user_role = $user->roles[0] ?? 'subscriber';

			$output .= sprintf(
				'<script>
				jQuery(document).ready(function($) {
					var row = $("tr#user-%1$d");
					var usernameCell = row.find("td.username");
					if (!usernameCell.find(".fwaum-online-badge").length) {
						usernameCell.find("strong").addClass("fwaum-user-online fwaum-role-%2$s");
						usernameCell.find("strong a").after(\'<span class="fwaum-online-badge">%3$s</span>\');
						row.addClass("fwaum-row-online");
					}
				});
				</script>',
				esc_js( $user_id ),
				esc_js( $user_role ),
				esc_js( __( 'ONLINE', 'fullworks-active-users-monitor' ) )
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameters for URL building
		$url_params = $_GET;

		// Online link.
		$url_params['fwaum_filter'] = 'online';
		$online_url                 = add_query_arg( $url_params, $base_url );
		$online_class               = ( 'online' === $current_filter ) ? 'current' : '';
		$views['fwaum_online']      = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( $online_url ),
			esc_attr( $online_class ),
			__( 'Online', 'fullworks-active-users-monitor' ),
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
			__( 'Offline', 'fullworks-active-users-monitor' ),
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

		$summary_text = ! empty( $role_summary ) ? implode( ', ', $role_summary ) : __( 'No users online', 'fullworks-active-users-monitor' );

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
	 * Add inline styles for visual indicators
	 */
	public function add_inline_styles() {
		$options           = get_option( 'fwaum_settings', array() );
		$enable_animations = isset( $options['enable_animations'] ) ? $options['enable_animations'] : true;

		?>
		<style type="text/css">
			/* Temporary inline styles - will be moved to CSS file */
			.fwaum-user-online.fwaum-role-administrator {
				padding: 2px 6px;
				border: 2px solid #ff9800;
				border-radius: 4px;
				box-shadow: 0 0 5px rgba(255, 152, 0, 0.5);
				display: inline-block;
				<?php if ( $enable_animations ) : ?>
				animation: fwaum-pulse-gold 2s infinite;
				<?php endif; ?>
			}
			
			.fwaum-user-online:not(.fwaum-role-administrator) {
				padding: 2px 6px;
				border: 2px solid #4caf50;
				border-radius: 4px;
				display: inline-block;
				<?php if ( $enable_animations ) : ?>
				animation: fwaum-pulse-green 2s infinite;
				<?php endif; ?>
			}
			
			.fwaum-online-badge {
				background: #4caf50;
				color: white;
				padding: 2px 6px;
				border-radius: 3px;
				font-size: 10px;
				font-weight: bold;
				margin-left: 8px;
				display: inline-block;
			}
			
			.fwaum-row-online {
				background-color: rgba(76, 175, 80, 0.05) !important;
			}
			
			.fwaum-status-indicator {
				display: flex;
				align-items: center;
				gap: 5px;
			}
			
			.fwaum-status-dot {
				font-size: 16px;
				line-height: 1;
			}
			
			.fwaum-status-online .fwaum-status-dot {
				color: #4caf50;
			}
			
			.fwaum-status-offline .fwaum-status-dot {
				color: #999;
			}
			
			.fwaum-last-seen {
				font-size: 11px;
				color: #666;
				display: block;
				margin-top: 2px;
			}
			
			<?php if ( $enable_animations ) : ?>
			@keyframes fwaum-pulse-gold {
				0%, 100% { box-shadow: 0 0 5px rgba(255, 152, 0, 0.5); }
				50% { box-shadow: 0 0 15px rgba(255, 152, 0, 0.8); }
			}
			
			@keyframes fwaum-pulse-green {
				0%, 100% { box-shadow: 0 0 5px rgba(76, 175, 80, 0.5); }
				50% { box-shadow: 0 0 10px rgba(76, 175, 80, 0.8); }
			}
			<?php endif; ?>
		</style>
		<?php
	}
}