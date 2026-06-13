<?php
/**
 * Admin class for Bbh Redirection.
 *
 * @package Bbhre_Redirection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBHRE_Admin {

	const PAGE_SLUG = 'bbh-redirection';

	private static $instance = null;
	private $base;

	private function __construct() {
		$this->base = BBHRE_Base::get_instance();
	}

	// Enqueue admin styles and scripts
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, self::PAGE_SLUG ) === false ) {
			return;
		}
		wp_enqueue_style( 'bbhre-redirection-admin-style', plugin_dir_url( __FILE__ ) . '../assets/css/bbhre-redirection-admin-style.css', array(), BBH_REDIRECTION_VERSION );
		wp_enqueue_script( 'bbhre-redirection-admin-script', plugin_dir_url( __FILE__ ) . '../assets/js/bbhre-redirection-admin-script.js', array( 'jquery' ), BBH_REDIRECTION_VERSION, true );
	}

	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		add_action( 'admin_menu', array( self::$instance, 'add_menu' ) );
		add_action( 'admin_init', array( self::$instance, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( self::$instance, 'enqueue_assets' ) );

		return self::$instance;
	}

	public function add_menu() {
		add_menu_page(
			__( 'BBH Redirections', 'bbh-redirection' ),
			__( 'BBH Redirection', 'bbh-redirection' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::$instance, 'render_page' ),
			'dashicons-randomize',
			99
		);
		// 2. Override the auto-generated sub-menu item with your specific text
		add_submenu_page(
			self::PAGE_SLUG,
			__( '301 Redirection', 'bbh-redirection' ),
			__( '301 Redirection', 'bbh-redirection' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::$instance, 'render_page' )
		);
		// 3. 404 Monitor
		add_submenu_page(
			self::PAGE_SLUG,
			__( '404 Monitor', 'bbh-redirection' ),
			__( '404 Monitor', 'bbh-redirection' ),
			'manage_options',
			self::PAGE_SLUG . '-404',
			array( self::$instance, 'render_404_monitor_page' )
		);

		// 4. Submenu for Documentation
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Documentation', 'bbh-redirection' ),
			__( 'Documentation', 'bbh-redirection' ),
			'manage_options',
			self::PAGE_SLUG . '-docs',
			array( self::$instance, 'render_docs_page' )
		);
	}

	public function render_docs_page() {
		BBHRE_Documentation::render();
	}

	public function render_404_monitor_page() {
		BBHRE_404_Monitor::render_page();
	}

	public function handle_actions() {
		if ( empty( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( empty( $_POST ) ) {
			return;
		}

		if ( ! isset( $_POST['page'] ) || self::PAGE_SLUG !== $_POST['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'bbhre_action' ) ) {
			return;
		}

		if ( isset( $_POST['add_redirect'] ) ) {
			$this->handle_add();
		} elseif ( isset( $_POST['edit_redirect'] ) ) {
			$this->handle_edit();
		} elseif ( isset( $_POST['bulk_delete'] ) ) {
			$this->handle_bulk_delete();
		}
	}

	private function handle_add() {
		// 1. Get and sanitize the nonce from the request
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';

		// 2. Verify Nonce with the sanitized variable
		if ( ! wp_verify_nonce( $nonce, 'bbhre_action' ) ) {
			$this->add_notice( __( 'Security check failed. Please refresh the page.', 'bbh-redirection' ), 'error' );
			return;
		}

		// 3. Check Permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->add_notice( __( 'You do not have permission to do this.', 'bbh-redirection' ), 'error' );
			return;
		}

		// 4. Get and sanitize input
		$source = isset( $_POST['source_url'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['source_url'] ) ) ) : '';
		$dest   = isset( $_POST['destination_url'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['destination_url'] ) ) ) : '';

		if ( empty( $source ) || empty( $dest ) ) {
			$this->add_notice( __( 'Source and destination URLs are required.', 'bbh-redirection' ), 'error' );
			return;
		}

		if ( $this->base->check_loop( $source, $dest ) ) {
			$this->add_notice( __( 'Redirect loop detected.', 'bbh-redirection' ), 'error' );
			return;
		}

		$result = $this->base->add( $source, $dest );

		if ( is_wp_error( $result ) ) {
			$this->add_notice( $result->get_error_message(), 'error' );
			return;
		}

		$this->add_notice( __( 'Redirect added successfully.', 'bbh-redirection' ), 'success' );

		$redirect_url = add_query_arg(
			array( 'page' => 'bbh-redirection' ),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function handle_edit() {
		// 1. Verify Nonce (Proof of Intent)
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bbhre_action' ) ) {
			$this->add_notice( __( 'Security check failed.', 'bbh-redirection' ), 'error' );
			return;
		}

		// 2. Check Permissions (Proof of Authority)
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->add_notice( __( 'You do not have permission to do this.', 'bbh-redirection' ), 'error' );
			return;
		}

		// 3. Now safely process the data
		$id     = isset( $_POST['redirect_id'] ) ? absint( $_POST['redirect_id'] ) : 0;
		$source = isset( $_POST['source_url'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['source_url'] ) ) ) : '';
		$dest   = isset( $_POST['destination_url'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['destination_url'] ) ) ) : '';

		if ( ! $id ) {
			$this->add_notice( __( 'Invalid redirect ID.', 'bbh-redirection' ), 'error' );
			return;
		}

		if ( empty( $source ) || empty( $dest ) ) {
			$this->add_notice( __( 'Source and destination URLs are required.', 'bbh-redirection' ), 'error' );
			return;
		}

		$result = $this->base->update( $id, $source, $dest );

		if ( is_wp_error( $result ) ) {
			$this->add_notice( $result->get_error_message(), 'error' );
			return;
		}

		$this->add_notice( __( 'Redirect updated successfully.', 'bbh-redirection' ), 'success' );
	}

	private function handle_bulk_delete() {
		// 1. Verify Nonce
		$nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bbhre_action' ) ) {
			$this->add_notice( __( 'Security check failed.', 'bbh-redirection' ), 'error' );
			return;
		}

		// 2. Check Permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->add_notice( __( 'You do not have permission to do this.', 'bbh-redirection' ), 'error' );
			return;
		}

		// 3. Unslash and Sanitize the array of IDs
		$ids = isset( $_POST['redirect_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['redirect_ids'] ) ) : array();
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			$this->add_notice( __( 'Please select at least one redirect.', 'bbh-redirection' ), 'error' );
			return;
		}

		$result = $this->base->delete_many( $ids );

		if ( $result ) {
			$this->add_notice( __( 'Redirects deleted successfully.', 'bbh-redirection' ), 'success' );
		} else {
			$this->add_notice( __( 'Failed to delete redirects.', 'bbh-redirection' ), 'error' );
		}
	}

	private function add_notice( $message, $type = 'success' ) {
		$notices = get_transient( 'bbhre_notices' );
		if ( ! $notices ) {
			$notices = array();
		}
		$notices[] = array( 'message' => $message, 'type' => $type );
		set_transient( 'bbhre_notices', $notices, 30 );
	}

	public function render_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 20;
		$offset = ( $paged - 1 ) * $per_page;

		$total = $this->base->get_count();
		$redirects = $this->base->get_all( $per_page, $offset );
		$total_pages = ceil( $total / $per_page );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$edit_redirect = $edit_id ? $this->base->get_by_id( $edit_id ) : null;

		$notices = get_transient( 'bbhre_notices' );
		delete_transient( 'bbhre_notices' );
		?>
		<div class="bbhredh-wrap">
			<div class="bbgredreportpagehead">
				<h1><?php esc_html_e( 'BBH Redirection', 'bbh-redirection' ); ?></h1>
				<p><?php esc_html_e( 'Manage your 301 redirects with ease.', 'bbh-redirection' ); ?></p>
			</div>
			<div class="bbhred-notices">
				<?php if ( $notices ) : ?>
					<?php foreach ( $notices as $notice ) : ?>
						<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
							<p><?php echo esc_html( $notice['message'] ); ?></p>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<?php if ( $edit_redirect ) : ?>
				<?php $this->render_edit_form( $edit_redirect ); ?>
			<?php else : ?>
				<?php $this->render_add_form(); ?>
				<?php $this->render_list( $redirects, $paged, $total_pages, $total ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_add_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$prefill_source = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
		?>
		<div class="bbhred-addnew">
			<h2 class="bbhred-htitle"><?php esc_html_e( 'Add New Redirect', 'bbh-redirection' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'bbhre_action', '_nonce' ); ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<table class="form-table">
					<tr>
						<th><label for="source_url"><?php esc_html_e( 'Source URL', 'bbh-redirection' ); ?></label></th>
						<td>
							<input type="text" name="source_url" id="source_url" class="regular-text" required placeholder="/old-page/" value="<?php echo esc_attr( $prefill_source ); ?>">
							<p class="description"><?php esc_html_e( 'Relative path starting with /', 'bbh-redirection' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="destination_url"><?php esc_html_e( 'Destination URL', 'bbh-redirection' ); ?></label></th>
						<td>
							<input type="url" name="destination_url" id="destination_url" class="regular-text" required placeholder="https://example.com/new-page/">
							<p class="description"><?php esc_html_e( 'Full URL with http:// or https://', 'bbh-redirection' ); ?></p>
						</td>
					</tr>
				</table>
				<p><?php submit_button( __( 'Add Redirect', 'bbh-redirection' ), 'primary', 'add_redirect', false ); ?></p>
			</form>
		</div>
		<?php
	}

	private function render_edit_form( $redirect ) {
		?>
		<div class="bbhred-edit">
			<h2 class="bbhred-htitle"><?php esc_html_e( 'Edit Redirect', 'bbh-redirection' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'bbhre_action', '_nonce' ); ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<input type="hidden" name="redirect_id" value="<?php echo esc_attr( $redirect->id ); ?>">
				<table class="form-table">
					<tr>
						<th><label for="source_url"><?php esc_html_e( 'Source URL', 'bbh-redirection' ); ?></label></th>
						<td><input type="text" name="source_url" id="source_url" class="regular-text" required value="<?php echo esc_attr( $redirect->source_url ); ?>"></td>
					</tr>
					<tr>
						<th><label for="destination_url"><?php esc_html_e( 'Destination URL', 'bbh-redirection' ); ?></label></th>
						<td><input type="url" name="destination_url" id="destination_url" class="regular-text" required value="<?php echo esc_attr( $redirect->destination_url ); ?>"></td>
					</tr>
				</table>
				<p>
					<?php submit_button( __( 'Save Changes', 'bbh-redirection' ), 'primary', 'edit_redirect', false ); ?>
					<a href="?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>" class="button button-secondary"><?php esc_html_e( 'Back', 'bbh-redirection' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	private function render_list( $redirects, $paged, $total_pages, $total ) {
		$base = '?page=' . self::PAGE_SLUG;
		?>
		
		<div class="bbhred-list">
			<h2 class="bbhred-htitle"><?php esc_html_e( 'Redirects', 'bbh-redirection' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'bbhre_action', '_nonce' ); ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<p><input type="submit" name="bulk_delete" value="<?php esc_attr_e( 'Delete Selected', 'bbh-redirection' ); ?>" class="button"></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" id="cb-select-all"></th>
							<th><?php esc_html_e( 'Source URL', 'bbh-redirection' ); ?></th>
							<th><?php esc_html_e( 'Destination URL', 'bbh-redirection' ); ?></th>
							<th><?php esc_html_e( 'Hits', 'bbh-redirection' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bbh-redirection' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $redirects ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No redirects yet.', 'bbh-redirection' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $redirects as $r ) : ?>
								<tr>
									<th class="check-column"><input type="checkbox" name="redirect_ids[]" value="<?php echo esc_attr( $r->id ); ?>"></th>
									<td><code><?php echo esc_html( $r->source_url ); ?></code></td>
									<td><code><?php echo esc_html( $r->destination_url ); ?></code></td>
									<td><?php echo esc_html( number_format_i18n( $r->hits_count ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( $base . '&edit=' . $r->id ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'bbh-redirection' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>
		</div>
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<span class="displaying-num">
					<?php printf(
						/* translators: 1: Current item start index, 2: Total number of items. */
						esc_html__( '%1$d of %2$d', 'bbh-redirection' ),
						absint(( $paged - 1 ) * 20 + 1 ),
						absint( $total )
					);
					?>
				</span>
				<?php
				$page_links = paginate_links( array(
					'base' => $base . '&paged=%#%',
					'format' => '',
					'prev_text' => __( '&laquo;', 'bbh-redirection' ),
					'next_text' => __( '&raquo;', 'bbh-redirection' ),
					'total' => $total_pages,
					'current' => $paged,
				) );
				if ( $page_links ) {
					echo '<span class="pagination-links">';
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wp_kses_post( $page_links );
					echo '</span>';
				}
				?>
			</div>
		<?php endif; ?>
		<?php
	}
}