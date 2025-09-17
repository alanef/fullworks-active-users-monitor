<?php
/**
 * WP_List_Table for displaying audit log entries
 *
 * @package FullworksActiveUsersMonitor\Includes
 * @since 1.0.2
 */

namespace FullworksActiveUsersMonitor\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Audit Table class extending WP_List_Table
 */
class Audit_Table extends \WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'audit_entry',
				'plural'   => 'audit_entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns
	 *
	 * @return array Columns array.
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'username'         => esc_html__( 'User', 'fullworks-active-users-monitor' ),
			'event_type'       => esc_html__( 'Event', 'fullworks-active-users-monitor' ),
			'timestamp'        => esc_html__( 'Date & Time', 'fullworks-active-users-monitor' ),
			'ip_address'       => esc_html__( 'IP Address', 'fullworks-active-users-monitor' ),
			'login_method'     => esc_html__( 'Method', 'fullworks-active-users-monitor' ),
			'session_duration' => esc_html__( 'Duration', 'fullworks-active-users-monitor' ),
			'user_agent'       => esc_html__( 'User Agent', 'fullworks-active-users-monitor' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array Sortable columns.
	 */
	protected function get_sortable_columns() {
		return array(
			'username'   => array( 'username', false ),
			'event_type' => array( 'event_type', false ),
			'timestamp'  => array( 'timestamp', true ),
			'ip_address' => array( 'ip_address', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array Bulk actions.
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => esc_html__( 'Delete', 'fullworks-active-users-monitor' ),
		);
	}

	/**
	 * Column checkbox
	 *
	 * @param object $item Row item.
	 * @return string Checkbox HTML.
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="audit_entries[]" value="%s" />', esc_attr( $item->id ) );
	}

	/**
	 * Column username
	 *
	 * @param object $item Row item.
	 * @return string Username column HTML.
	 */
	protected function column_username( $item ) {
		$user_link = '';
		if ( intval( $item->user_id ) > 0 ) {
			$user_edit_url = add_query_arg(
				array(
					'user_id' => $item->user_id,
				),
				admin_url( 'user-edit.php' )
			);
			$user_link     = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $user_edit_url ),
				esc_html( $item->display_name )
			);
		} else {
			$user_link = esc_html( $item->display_name );
		}

		$username_info = sprintf(
			'%s<br><small>%s</small>',
			$user_link,
			esc_html( $item->username )
		);

		// Row actions.
		$actions = array();
		if ( current_user_can( 'manage_options' ) ) {
			$actions['view'] = sprintf(
				'<a href="#" class="fwaum-view-details" data-id="%d">%s</a>',
				intval( $item->id ),
				esc_html__( 'View Details', 'fullworks-active-users-monitor' )
			);
		}

		return $username_info . $this->row_actions( $actions );
	}

	/**
	 * Column event type
	 *
	 * @param object $item Row item.
	 * @return string Event type column HTML.
	 */
	protected function column_event_type( $item ) {
		$event_labels = array(
			'login'           => array(
				'label' => esc_html__( 'Login', 'fullworks-active-users-monitor' ),
				'class' => 'success',
			),
			'logout'          => array(
				'label' => esc_html__( 'Logout', 'fullworks-active-users-monitor' ),
				'class' => 'info',
			),
			'failed_login'    => array(
				'label' => esc_html__( 'Failed Login', 'fullworks-active-users-monitor' ),
				'class' => 'error',
			),
			'session_expired' => array(
				'label' => esc_html__( 'Session Expired', 'fullworks-active-users-monitor' ),
				'class' => 'warning',
			),
		);

		$event_info = isset( $event_labels[ $item->event_type ] ) ? $event_labels[ $item->event_type ] : array(
			'label' => $item->event_type,
			'class' => 'default',
		);

		return sprintf(
			'<span class="fwaum-event-badge fwaum-event-%s">%s</span>',
			esc_attr( $event_info['class'] ),
			esc_html( $event_info['label'] )
		);
	}

	/**
	 * Column timestamp
	 *
	 * @param object $item Row item.
	 * @return string Timestamp column HTML.
	 */
	protected function column_timestamp( $item ) {
		$timestamp      = strtotime( $item->timestamp );
		$formatted_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
		$relative_time  = human_time_diff( $timestamp, time() );

		return sprintf(
			'%s<br><small>%s ago</small>',
			esc_html( $formatted_date ),
			esc_html( $relative_time )
		);
	}

	/**
	 * Column IP address
	 *
	 * @param object $item Row item.
	 * @return string IP address column HTML.
	 */
	protected function column_ip_address( $item ) {
		if ( '0.0.0.0' === $item->ip_address || empty( $item->ip_address ) ) {
			return '<span class="fwaum-no-ip">—</span>';
		}

		return sprintf(
			'<span class="fwaum-ip-address">%s</span>',
			esc_html( $item->ip_address )
		);
	}

	/**
	 * Column login method
	 *
	 * @param object $item Row item.
	 * @return string Login method column HTML.
	 */
	protected function column_login_method( $item ) {
		$method_labels = array(
			'standard'   => esc_html__( 'Standard', 'fullworks-active-users-monitor' ),
			'social'     => esc_html__( 'Social', 'fullworks-active-users-monitor' ),
			'two_factor' => esc_html__( 'Two Factor', 'fullworks-active-users-monitor' ),
			'xmlrpc'     => esc_html__( 'XML-RPC', 'fullworks-active-users-monitor' ),
			'rest_api'   => esc_html__( 'REST API', 'fullworks-active-users-monitor' ),
			'wp_cli'     => esc_html__( 'WP-CLI', 'fullworks-active-users-monitor' ),
			'migrated'   => esc_html__( 'Migrated', 'fullworks-active-users-monitor' ),
		);

		$method_label = isset( $method_labels[ $item->login_method ] ) ? $method_labels[ $item->login_method ] : esc_html( $item->login_method );

		return sprintf(
			'<span class="fwaum-login-method fwaum-method-%s">%s</span>',
			esc_attr( sanitize_html_class( $item->login_method ) ),
			$method_label
		);
	}

	/**
	 * Column session duration
	 *
	 * @param object $item Row item.
	 * @return string Session duration column HTML.
	 */
	protected function column_session_duration( $item ) {
		if ( null === $item->session_duration || '' === $item->session_duration ) {
			return '<span class="fwaum-no-duration">—</span>';
		}

		$duration = intval( $item->session_duration );
		if ( $duration < 60 ) {
			/* translators: %d: Duration in seconds */
			return sprintf( esc_html__( '%ds', 'fullworks-active-users-monitor' ), $duration );
		} elseif ( $duration < 3600 ) {
			/* translators: %d: Duration in minutes */
			return sprintf( esc_html__( '%dm', 'fullworks-active-users-monitor' ), round( $duration / 60 ) );
		} else {
			$hours   = floor( $duration / 3600 );
			$minutes = round( ( $duration % 3600 ) / 60 );
			if ( $minutes > 0 ) {
				/* translators: 1: Hours, 2: Minutes */
				return sprintf( esc_html__( '%1$dh %2$dm', 'fullworks-active-users-monitor' ), $hours, $minutes );
			} else {
				/* translators: %d: Duration in hours */
				return sprintf( esc_html__( '%dh', 'fullworks-active-users-monitor' ), $hours );
			}
		}
	}

	/**
	 * Column user agent
	 *
	 * @param object $item Row item.
	 * @return string User agent column HTML.
	 */
	protected function column_user_agent( $item ) {
		$user_agent = $item->user_agent;
		if ( empty( $user_agent ) || 'Unknown' === $user_agent ) {
			return '<span class="fwaum-no-agent">—</span>';
		}

		// Truncate long user agents.
		$truncated = strlen( $user_agent ) > 50 ? substr( $user_agent, 0, 50 ) . '...' : $user_agent;

		return sprintf(
			'<span class="fwaum-user-agent" title="%s">%s</span>',
			esc_attr( $user_agent ),
			esc_html( $truncated )
		);
	}

	/**
	 * Default column handler
	 *
	 * @param object $item        Row item.
	 * @param string $column_name Column name.
	 * @return string Column HTML.
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		// Get filter values.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are display filters only, not data modifications.
		$user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are display filters only, not data modifications.
		$event_type = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are display filters only, not data modifications.
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are display filters only, not data modifications.
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are display filters only, not data modifications.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : null;

		// Get sorting parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are display filters only, not data modifications.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'timestamp';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These are display filters only, not data modifications.
		$order = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc';

		// Prepare query arguments.
		$args = array(
			'per_page'   => $per_page,
			'page'       => $current_page,
			'orderby'    => $orderby,
			'order'      => $order,
			'user_id'    => $user_id,
			'event_type' => $event_type,
			'date_from'  => $date_from,
			'date_to'    => $date_to,
			'search'     => $search,
		);

		// Get data from audit logger.
		$results = Audit_Logger::get_audit_entries( $args );

		$this->items = $results['entries'];

		// Set pagination info.
		$this->set_pagination_args(
			array(
				'total_items' => $results['total_items'],
				'per_page'    => $per_page,
				'total_pages' => $results['total_pages'],
			)
		);

		// Set column headers.
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Display when no items are found
	 */
	public function no_items() {
		esc_html_e( 'No audit entries found.', 'fullworks-active-users-monitor' );
	}

	/**
	 * Get views (filter links)
	 *
	 * @return array Views array.
	 */
	protected function get_views() {
		$views = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
		$current_view = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '';

		// All entries.
		$all_url      = remove_query_arg( array( 'event_type' ) );
		$views['all'] = sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $all_url ),
			'' === $current_view ? ' class="current"' : '',
			esc_html__( 'All', 'fullworks-active-users-monitor' )
		);

		// Event type filters.
		$event_types = array(
			'login'           => esc_html__( 'Logins', 'fullworks-active-users-monitor' ),
			'logout'          => esc_html__( 'Logouts', 'fullworks-active-users-monitor' ),
			'failed_login'    => esc_html__( 'Failed Logins', 'fullworks-active-users-monitor' ),
			'session_expired' => esc_html__( 'Session Expired', 'fullworks-active-users-monitor' ),
		);

		foreach ( $event_types as $type => $label ) {
			$type_url       = add_query_arg( array( 'event_type' => $type ) );
			$views[ $type ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $type_url ),
				$type === $current_view ? ' class="current"' : '',
				$label
			);
		}

		return $views;
	}

	/**
	 * Extra tablenav (filters)
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<label for="filter-date-from" class="screen-reader-text">
				<?php esc_html_e( 'Filter by date from', 'fullworks-active-users-monitor' ); ?>
			</label>
			<input type="date"
					id="filter-date-from"
					name="date_from"
					value="
					<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
					echo esc_attr( isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '' );
					?>
				"
					placeholder="<?php esc_attr_e( 'From date', 'fullworks-active-users-monitor' ); ?>" />

			<label for="filter-date-to" class="screen-reader-text">
				<?php esc_html_e( 'Filter by date to', 'fullworks-active-users-monitor' ); ?>
			</label>
			<input type="date"
					id="filter-date-to"
					name="date_to"
					value="
					<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display filter only.
					echo esc_attr( isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '' );
					?>
				"
					placeholder="<?php esc_attr_e( 'To date', 'fullworks-active-users-monitor' ); ?>" />

			<?php submit_button( esc_html__( 'Filter', 'fullworks-active-users-monitor' ), 'secondary', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Handle bulk actions
	 */
	public function process_bulk_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = $this->current_action();

		if ( 'delete' === $action ) {
			// Verify nonce.
			if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-audit_entries' ) ) {
				wp_die( esc_html__( 'Invalid nonce.', 'fullworks-active-users-monitor' ) );
			}

			// Get selected entries.
			$entries = isset( $_REQUEST['audit_entries'] ) ? array_map( 'intval', (array) wp_unslash( $_REQUEST['audit_entries'] ) ) : array();

			if ( ! empty( $entries ) ) {
				global $wpdb;
				$table_name   = Audit_Installer::get_table_name();
				$placeholders = implode( ',', array_fill( 0, count( $entries ), '%d' ) );

				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are dynamically generated but safe.
						'DELETE FROM %i WHERE id IN (' . $placeholders . ')',
						array_merge( array( $table_name ), $entries )
					)
				);

				$message = sprintf(
					/* translators: %d: Number of deleted entries */
					_n( 'Deleted %d audit entry.', 'Deleted %d audit entries.', count( $entries ), 'fullworks-active-users-monitor' ),
					count( $entries )
				);

				add_settings_error( 'fwaum_audit_messages', 'entries_deleted', $message, 'updated' );
			}
		}
	}
}