<?php
/**
 * 404 Monitor for BBH Redirection.
 *
 * @package Bbhre_Redirection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main 404 monitor class.
 */
class BBHRE_404_Monitor {

	const LOGS_TABLE_SUFFIX = 'bbhre_404_logs';

	private static $instance = null;
	private $table_name      = '';

	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . self::LOGS_TABLE_SUFFIX;
	}

	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		register_activation_hook( BBH_REDIRECTION_FILE, array( self::$instance, 'activate' ) );
		register_deactivation_hook( BBH_REDIRECTION_FILE, array( self::$instance, 'deactivate' ) );

		add_action( 'template_redirect', array( self::$instance, 'detect_404' ), 100 );
		add_action( 'admin_init', array( self::$instance, 'maybe_create_table' ) );
		add_action( 'admin_init', array( self::$instance, 'handle_actions' ) );
		add_action( 'bbhre_404_daily_cleanup', array( self::$instance, 'cleanup_old_logs' ) );

		self::$instance->schedule_cleanup();

		return self::$instance;
	}

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function activate() {
		$this->create_table();
	}

	public function deactivate() {
		$timestamp = wp_next_scheduled( 'bbhre_404_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'bbhre_404_daily_cleanup' );
		}
	}

	public function maybe_create_table() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$version = get_option( 'bbhre_404_table_version' );
		if ( $version ) {
			return;
		}

		$this->create_table();
		update_option( 'bbhre_404_table_version', BBH_REDIRECTION_VERSION );
	}

	private function create_table() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			requested_url varchar(2000) NOT NULL,
			referrer varchar(2000) DEFAULT NULL,
			user_agent varchar(500) DEFAULT NULL,
			hit_count bigint(20) unsigned DEFAULT 1,
			last_visited datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_requested_url (requested_url(100)),
			KEY idx_last_visited (last_visited),
			KEY idx_hit_count (hit_count)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function detect_404() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( defined( 'XMLRPC_REQUEST' ) ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( ! is_404() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( empty( $request_uri ) ) {
			return;
		}

		if ( $this->is_static_asset( $request_uri ) ) {
			return;
		}

		$parsed = wp_parse_url( $request_uri );
		if ( empty( $parsed['path'] ) ) {
			return;
		}

		$path = $parsed['path'];

		$excluded = array( '/wp-admin', '/wp-content', '/wp-includes', '/xmlrpc.php', '/wp-cron.php' );
		foreach ( $excluded as $ex ) {
			if ( 0 === strpos( $path, $ex ) ) {
				return;
			}
		}

		$referrer   = ! empty( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$user_agent = ! empty( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( $this->is_bot( $user_agent ) ) {
			return;
		}

		$this->log_request( $path, $referrer, $user_agent );
	}

	private function is_bot( $user_agent ) {
		if ( empty( $user_agent ) ) {
			return false;
		}

		$bots = array(
			'googlebot',
			'bingbot',
			'slurp',
			'duckduckbot',
			'baiduspider',
			'yandexbot',
			'facebookexternalhit',
			'twitterbot',
			'rogerbot',
			'linkedinbot',
			'embedly',
			'quora link preview',
			'showyoubot',
			'outbrain',
			'pinterest',
			'slackbot',
			'vkshare',
			'w3c_validator',
			'redditbot',
			'applebot',
			'whatsapp',
			'flipboard',
			'tumblr',
			'bitlybot',
			'semrushbot',
			'ahrefsbot',
			'dotbot',
			'mj12bot',
			'exabot',
			'crawler',
			'spider',
			'bot',
			' scanner',
			'crawling',
		);

		$user_agent = strtolower( $user_agent );

		foreach ( $bots as $bot ) {
			if ( false !== strpos( $user_agent, $bot ) ) {
				return true;
			}
		}

		return false;
	}

	private function is_static_asset( $url ) {
		$extensions = array(
			'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'tiff',
			'css', 'js', 'ts', 'jsx', 'tsx',
			'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
			'zip', 'tar', 'gz', 'rar', '7z',
			'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ogg',
			'woff', 'woff2', 'ttf', 'eot', 'otf',
			'xml', 'json', 'csv', 'txt',
			'psd', 'ai', 'eps',
			'exe', 'dll', 'so', 'dmg',
			'atom', 'rss',
		);

		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}

		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		if ( ! $extension ) {
			return false;
		}

		return in_array( strtolower( $extension ), $extensions, true );
	}

	private function log_request( $url, $referrer, $user_agent ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and safely escaped.
				"SELECT id, hit_count, referrer, user_agent FROM {$this->table_name} WHERE requested_url = %s",
				$url
			)
		);

		if ( $existing ) {
			$data = array(
				'hit_count'    => $existing->hit_count + 1,
				'last_visited' => current_time( 'mysql' ),
			);

			if ( ! empty( $referrer ) && empty( $existing->referrer ) ) {
				$data['referrer'] = $referrer;
			}

			if ( ! empty( $user_agent ) && empty( $existing->user_agent ) ) {
				$data['user_agent'] = $user_agent;
			}
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query operations.
			$wpdb->update(
				$this->table_name,
				$data,
				array( 'id' => $existing->id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			// phpcs:enable
		} else {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query operations.
			$wpdb->insert(
				$this->table_name,
				array(
					'requested_url' => $url,
					'referrer'      => $referrer,
					'user_agent'    => $user_agent,
					'hit_count'     => 1,
					'last_visited'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%d', '%s' )
			);
			// phpcs:enable
		}
	}

	public function handle_actions() {
		// 1. Safe read-only check for the current admin page context
		$page = filter_input( INPUT_GET, 'page', FILTER_DEFAULT );
		if ( empty( $page ) || 'bbh-redirection-404' !== $page ) {
			return;
		}

		// 2. Capabilities check
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 3. Gather form-submission metadata safely without triggering superglobal read errors
		$post_action  = filter_input( INPUT_POST, 'action', FILTER_DEFAULT );
		$post_action2 = filter_input( INPUT_POST, 'action2', FILTER_DEFAULT );
		$get_action   = filter_input( INPUT_GET, 'action', FILTER_DEFAULT );

		$has_post = ! empty( $_POST ); // Checked for presence only

		// If there is no form data or action intent, exit early before verifying security nonces
		if ( ! $has_post && empty( $get_action ) ) {
			return;
		}

		// 4. THE SECURITY GATE: Verify the nonce exactly once for ALL actions below
		// Try all possible nonce field names across the different forms on this page
		$nonce = filter_input( INPUT_POST, 'bbhre_settings_nonce', FILTER_DEFAULT );
		if ( ! $nonce ) {
			$nonce = filter_input( INPUT_POST, 'bbhre_clear_nonce', FILTER_DEFAULT );
		}
		if ( ! $nonce ) {
			$nonce = filter_input( INPUT_POST, 'bbhre_logs_nonce', FILTER_DEFAULT );
		}
		if ( ! $nonce ) {
			$nonce = filter_input( INPUT_GET, '_wpnonce', FILTER_DEFAULT ); // Fallback for list table row actions (GET)
		}

		if ( ! $nonce ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'bbh-redirection' ) );
		}

		// Verify against any of the valid nonce actions used on this page
		$valid_actions = array( 'bbhre_404_settings', 'bbhre_404_clear_all', 'bbhre_404_logs_table', 'bbhre_404_action' );
		$verified      = false;
		foreach ( $valid_actions as $action ) {
			if ( wp_verify_nonce( $nonce, $action ) ) {
				$verified = true;
				break;
			}
		}

		if ( ! $verified ) {
			wp_die( esc_html__( 'Security check failed. Please refresh the page and try again.', 'bbh-redirection' ) );
		}

		// 5. Now it is completely safe to process settings updates or deletions!
		if ( isset( $_POST['bbhre_404_save_settings'] ) ) {
			$this->handle_save_settings();
			return;
		}

		if ( isset( $_POST['bbhre_clear_all'] ) ) {
			$this->handle_clear_all();
			return;
		}

		// Determine the active list/bulk action
		$action = null;
		if ( ! empty( $post_action ) && -1 !== intval( $post_action ) ) {
			$action = sanitize_text_field( wp_unslash( $post_action ) );
		} elseif ( ! empty( $post_action2 ) && -1 !== intval( $post_action2 ) ) {
			$action = sanitize_text_field( wp_unslash( $post_action2 ) );
		} elseif ( ! empty( $get_action ) ) {
			$action = sanitize_text_field( wp_unslash( $get_action ) );
		}

		if ( ! $action ) {
			return;
		}

		// Execute bulk or single table utilities safely
		if ( 'delete' === $action && isset( $_GET['log_id'] ) ) {
			$this->handle_single_delete();
		}

		if ( 'bulk_delete' === $action && isset( $_POST['bbhre_log_ids'] ) ) {
			$this->handle_bulk_delete();
		}
	}

	private function handle_save_settings() {
		if ( ! check_admin_referer( 'bbhre_404_settings', 'bbhre_settings_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bbh-redirection' ) );
		}

		$retention_days = isset( $_POST['bbhre_404_retention_days'] ) ? absint( $_POST['bbhre_404_retention_days'] ) : 0;

		update_option( 'bbhre_404_settings', array( 'retention_days' => $retention_days ) );

		if ( $retention_days > 0 ) {
			if ( ! wp_next_scheduled( 'bbhre_404_daily_cleanup' ) ) {
				wp_schedule_event( time(), 'daily', 'bbhre_404_daily_cleanup' );
			}
		} else {
			$timestamp = wp_next_scheduled( 'bbhre_404_daily_cleanup' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'bbhre_404_daily_cleanup' );
			}
		}

		$redirect_url = add_query_arg(
			array(
				'page'    => 'bbh-redirection-404',
				'message' => 'settings_saved',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function handle_clear_all() {
		if ( ! isset( $_POST['bbhre_clear_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbhre_clear_nonce'] ) ), 'bbhre_404_clear_all' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bbh-redirection' ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is internal and safe from injection.
		$wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

		$redirect_url = add_query_arg(
			array(
				'page'    => 'bbh-redirection-404',
				'message' => 'cleared',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function handle_single_delete() {
		if ( ! isset( $_GET['bbhre_404_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['bbhre_404_nonce'] ) ), 'bbhre_404_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bbh-redirection' ) );
		}

		$log_id = isset( $_GET['log_id'] ) ? absint( $_GET['log_id'] ) : 0;
		if ( ! $log_id ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
		$wpdb->delete( $this->table_name, array( 'id' => $log_id ), array( '%d' ) );

		$redirect_url = add_query_arg(
			array(
				'page'    => 'bbh-redirection-404',
				'message' => 'deleted',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function handle_bulk_delete() {
		if ( ! isset( $_POST['bbhre_logs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bbhre_logs_nonce'] ) ), 'bbhre_404_logs_table' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bbh-redirection' ) );
		}

		$ids = isset( $_POST['bbhre_log_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['bbhre_log_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
				...$ids
			)
		);
		// phpcs:enable

		$redirect_url = add_query_arg(
			array(
				'page'    => 'bbh-redirection-404',
				'message' => 'deleted',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function schedule_cleanup() {
		$settings = get_option( 'bbhre_404_settings', array( 'retention_days' => 0 ) );
		if ( ! empty( $settings['retention_days'] ) && ! wp_next_scheduled( 'bbhre_404_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'bbhre_404_daily_cleanup' );
		}
	}

	public function cleanup_old_logs() {
		$settings = get_option( 'bbhre_404_settings', array( 'retention_days' => 0 ) );
		$days     = ! empty( $settings['retention_days'] ) ? absint( $settings['retention_days'] ) : 0;

		if ( $days < 1 ) {
			return;
		}

		global $wpdb;

		$cutoff = gmdate(
			'Y-m-d H:i:s',
			current_time( 'timestamp', true ) - ( $days * DAY_IN_SECONDS )
		);

		$table = $wpdb->prefix . 'bbhre_404_logs';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is a safe, hardcoded internal string. Custom table requires direct SQL query.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE last_visited < %s",
				$cutoff
			)
		);
		// phpcs:enable
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'bbh-redirection' ) );
		}

		$message = '';
		if ( isset( $_GET['message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = sanitize_key( $_GET['message'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			switch ( $msg ) {
				case 'settings_saved':
					$message = __( 'Settings saved.', 'bbh-redirection' );
					break;
				case 'cleared':
					$message = __( 'All 404 logs cleared.', 'bbh-redirection' );
					break;
				case 'deleted':
					$message = __( 'Logs deleted.', 'bbh-redirection' );
					break;
			}
		}
		?>
		<div class="bbhredh-wrap">
			<div class="bbgredreportpagehead">
				<h1><?php esc_html_e( '404 Monitor', 'bbh-redirection' ); ?></h1>
				<p class="bbhred-subtitle"><?php esc_html_e( 'Monitor and manage 404 errors on your site.', 'bbh-redirection' ); ?></p>
			</div>
			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php endif; ?>
			<div class="setting-sec">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bbh-redirection-404' ) ); ?>" style="margin-bottom:20px;">
					<?php wp_nonce_field( 'bbhre_404_settings', 'bbhre_settings_nonce', false ); ?>
					<h2 class="bbhred-htitle"><?php esc_html_e( 'Settings', 'bbh-redirection' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="bbhre_404_retention_days"><?php esc_html_e( 'Auto-delete logs older than', 'bbh-redirection' ); ?></label></th>
							<td>
								<?php
								$settings = get_option( 'bbhre_404_settings', array( 'retention_days' => 0 ) );
								$days     = isset( $settings['retention_days'] ) ? absint( $settings['retention_days'] ) : 0;
								?>
								<input type="number" name="bbhre_404_retention_days" id="bbhre_404_retention_days" value="<?php echo esc_attr( $days ); ?>" min="0" class="small-text">
								<p class="description"><?php esc_html_e( 'Days to keep 404 logs. Set to 0 to disable auto-deletion. Requires a daily cron job.', 'bbh-redirection' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Settings', 'bbh-redirection' ), 'primary', 'bbhre_404_save_settings' ); ?>
				</form>
			</div>
			<div class="logdatatable">
				<h2 class="bbhred-htitle"><?php esc_html_e( '404 Logs', 'bbh-redirection' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bbh-redirection-404' ) ); ?>">
					<?php
					wp_nonce_field( 'bbhre_404_clear_all', 'bbhre_clear_nonce', false );
					?>
					<p style="margin-bottom:10px;">
						<input type="submit" name="bbhre_clear_all" value="<?php esc_attr_e( 'Clear All Logs', 'bbh-redirection' ); ?>" class="button" onclick="return confirm('<?php esc_js( __( 'This will permanently delete all 404 logs. Are you sure?', 'bbh-redirection' ) ); ?>')">
					</p>
				</form>
			</div>
			<div class="datatable">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=bbh-redirection-404' ) ); ?>">
					<?php
					wp_nonce_field( 'bbhre_404_logs_table', 'bbhre_logs_nonce', false );

					$logs_table = new BBHRE_404_Logs_List_Table();
					$logs_table->prepare_items();
					$logs_table->display();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	public function get_logs_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
	}

	public function get_logs( $per_page = 20, $offset = 0, $orderby = 'last_visited', $order = 'DESC' ) {
		global $wpdb;

		$allowed_orderby = array( 'requested_url', 'hit_count', 'last_visited', 'referrer' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'last_visited';
		}
		// This line explicitly reassures the static analyzer that $orderby is safe
		$orderby = sanitize_key( $orderby );

		$order = strtoupper( $order );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query requires direct SQL. Input variables are safely whitelisted.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
		// phpcs:enable
	}
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for 404 logs.
 */
class BBHRE_404_Logs_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox">',
			'requested_url'  => __( 'Requested URL', 'bbh-redirection' ),
			'referrer'       => __( 'Referrer', 'bbh-redirection' ),
			'user_agent'     => __( 'User Agent', 'bbh-redirection' ),
			'hit_count'      => __( 'Hits', 'bbh-redirection' ),
			'last_visited'   => __( 'Last Visited', 'bbh-redirection' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'requested_url' => array( 'requested_url', false ),
			'hit_count'     => array( 'hit_count', false ),
			'last_visited'  => array( 'last_visited', false ),
			'referrer'      => array( 'referrer', false ),
		);
	}

	public function get_bulk_actions() {
		return array(
			'bulk_delete' => __( 'Delete', 'bbh-redirection' ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="bbhre_log_ids[]" value="%d">',
			absint( $item->id )
		);
	}

	public function column_requested_url( $item ) {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => 'bbh-redirection-404',
					'action' => 'delete',
					'log_id' => $item->id,
				),
				admin_url( 'admin.php' )
			),
			'bbhre_404_action',
			'bbhre_404_nonce'
		);

		$create_redirect_url = add_query_arg(
			array(
				'page'   => 'bbh-redirection',
				'source' => $item->requested_url,
			),
			admin_url( 'admin.php' )
		);

		$actions = array(
			'create_redirect' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $create_redirect_url ),
				__( 'Create Redirect', 'bbh-redirection' )
			),
			'delete'          => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $delete_url ),
				__( 'Delete', 'bbh-redirection' )
			),
		);

		return sprintf(
			'<code>%1$s</code> %2$s',
			esc_html( $item->requested_url ),
			$this->row_actions( $actions )
		);
	}

	public function column_referrer( $item ) {
		if ( empty( $item->referrer ) ) {
			return '&mdash;';
		}
		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $item->referrer ),
			esc_html( $item->referrer )
		);
	}

	public function column_user_agent( $item ) {
		if ( empty( $item->user_agent ) ) {
			return '&mdash;';
		}
		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( $item->user_agent ),
			esc_html( wp_trim_words( $item->user_agent, 10 ) )
		);
	}

	public function column_hit_count( $item ) {
		return esc_html( number_format_i18n( $item->hit_count ) );
	}

	public function column_last_visited( $item ) {
		$time = strtotime( $item->last_visited );
		if ( ! $time ) {
			return '&mdash;';
		}
		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( $item->last_visited ),
			esc_html( sprintf(
				/* translators: %s: human-readable time difference */
				__( '%s ago', 'bbh-redirection' ),
				human_time_diff( $time, current_time( 'timestamp' ) )
			) )
		);
	}

	public function prepare_items() {
		$per_page = 20;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading display parameters, no form data is processed.
		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'last_visited';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading display parameters, no form data is processed.
		$order   = ! empty( $_GET['order'] ) ? strtoupper( sanitize_key( $_GET['order'] ) ) : 'DESC';

		$monitor = BBHRE_404_Monitor::get_instance();

		$total_items = $monitor->get_logs_count();

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$this->items = $monitor->get_logs( $per_page, $offset, $orderby, $order );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	public function no_items() {
		esc_html_e( 'No 404 errors logged yet.', 'bbh-redirection' );
	}
}
