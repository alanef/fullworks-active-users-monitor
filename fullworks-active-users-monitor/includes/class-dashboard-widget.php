<?php
/**
 * Dashboard Widget for online users summary
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
 * Dashboard Widget class
 */
class Dashboard_Widget {

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

		// Check if dashboard widget is enabled in settings.
		$options = get_option( 'fwaum_settings', array() );
		$enabled = isset( $options['enable_dashboard'] ) ? $options['enable_dashboard'] : true;

		if ( $enabled ) {
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}

	/**
	 * Add dashboard widget
	 */
	public function add_dashboard_widget() {
		// Only show to users who can list users.
		if ( ! current_user_can( 'list_users' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'fwaum_dashboard_widget',
			__( 'Active Users Monitor', 'fullworks-active-users-monitor' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget content
	 */
	public function render_dashboard_widget() {
		// Get online users data.
		$online_users   = $this->user_tracker->get_online_users();
		$online_count   = count( $online_users );
		$counts_by_role = $this->user_tracker->get_online_counts_by_role();

		?>
		<div class="fwaum-dashboard-widget">
			<div class="fwaum-dashboard-summary">
				<div class="fwaum-total-online">
					<span class="fwaum-big-number"><?php echo esc_html( $online_count ); ?></span>
					<span class="fwaum-label">
						<?php
						/* translators: %d: Number of users */
						echo esc_html( _n( 'User Online', 'Users Online', $online_count, 'fullworks-active-users-monitor' ) );
						?>
					</span>
				</div>
			</div>

			<?php if ( ! empty( $counts_by_role ) ) : ?>
				<div class="fwaum-role-breakdown">
					<h4><?php esc_html_e( 'By Role', 'fullworks-active-users-monitor' ); ?></h4>
					<ul class="fwaum-role-list">
						<?php
						// Sort roles by priority.
						$role_priority = array(
							'administrator' => 1,
							'editor'        => 2,
							'author'        => 3,
							'contributor'   => 4,
							'subscriber'    => 5,
						);

						uksort(
							$counts_by_role,
							function ( $a, $b ) use ( $role_priority ) {
								$priority_a = isset( $role_priority[ $a ] ) ? $role_priority[ $a ] : 999;
								$priority_b = isset( $role_priority[ $b ] ) ? $role_priority[ $b ] : 999;
								return $priority_a - $priority_b;
							}
						);

						foreach ( $counts_by_role as $role => $count ) :
							$role_obj  = get_role( $role );
							$role_name = $role_obj ? translate_user_role( $role_obj->name ) : ucfirst( $role );
							?>
							<li class="fwaum-role-item">
								<span class="fwaum-role-indicator fwaum-role-<?php echo esc_attr( $role ); ?>"></span>
								<span class="fwaum-role-name"><?php echo esc_html( $role_name ); ?>:</span>
								<span class="fwaum-role-count"><?php echo esc_html( $count ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $online_users ) ) : ?>
				<div class="fwaum-recent-users">
					<h4><?php esc_html_e( 'Recently Active', 'fullworks-active-users-monitor' ); ?></h4>
					<ul class="fwaum-user-list">
						<?php
						// Show up to 5 most recent online users.
						$display_users = array_slice( $online_users, 0, 5 );
						foreach ( $display_users as $user_id ) :
							$user = get_userdata( $user_id );
							if ( ! $user ) {
								continue;
							}
							?>
							<li class="fwaum-user-item">
								<?php echo get_avatar( $user_id, 24 ); ?>
								<div class="fwaum-user-info">
									<a href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>">
										<?php echo esc_html( $user->display_name ); ?>
									</a>
									<span class="fwaum-user-role">
										<?php
										$user_roles = array();
										foreach ( $user->roles as $role ) {
											$role_obj     = get_role( $role );
											$user_roles[] = $role_obj ? translate_user_role( $role_obj->name ) : ucfirst( $role );
										}
										echo esc_html( implode( ', ', $user_roles ) );
										?>
									</span>
								</div>
								<span class="fwaum-online-indicator" title="<?php esc_attr_e( 'Online now', 'fullworks-active-users-monitor' ); ?>">●</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="fwaum-dashboard-footer">
				<p class="fwaum-links">
					<a href="<?php echo esc_url( admin_url( 'users.php?fwaum_filter=online' ) ); ?>">
						<?php esc_html_e( 'View All Online Users', 'fullworks-active-users-monitor' ); ?>
					</a>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<span class="separator">|</span>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=fwaum-settings' ) ); ?>">
							<?php esc_html_e( 'Settings', 'fullworks-active-users-monitor' ); ?>
						</a>
					<?php endif; ?>
				</p>
				<p class="fwaum-last-updated">
					<small>
						<?php esc_html_e( 'Last updated:', 'fullworks-active-users-monitor' ); ?>
						<span class="fwaum-timestamp"><?php echo esc_html( current_time( 'g:i:s a' ) ); ?></span>
					</small>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles for dashboard widget
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'fwaum-dashboard-widget',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/dashboard-widget.css',
			array(),
			FWAUM_VERSION
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'fwaum-dashboard-widget',
			plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/dashboard-widget.js',
			array( 'jquery' ),
			FWAUM_VERSION,
			true
		);

		// Localize script with data.
		$settings = get_option( 'fwaum_settings', array() );
		wp_localize_script(
			'fwaum-dashboard-widget',
			'fwaum_dashboard',
			array(
				'nonce'            => wp_create_nonce( 'fwaum_ajax_nonce' ),
				'refresh_interval' => isset( $settings['refresh_interval'] ) ? $settings['refresh_interval'] : 30,
			)
		);
	}
}