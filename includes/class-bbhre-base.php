<?php
/**
 * Base class for Bbh Redirection plugin.
 *
 * @package Bbhre_Redirection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBHRE_Base {

	private static $instance = null;
	private $table_name = '';

	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . BBH_REDIRECTION_TABLE_NAME;
	}

	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		register_activation_hook( BBH_REDIRECTION_FILE, array( self::$instance, 'activate' ) );
		register_deactivation_hook( BBH_REDIRECTION_FILE, array( self::$instance, 'deactivate' ) );

		self::$instance->migrate_table();

		BBHRE_Admin::init();
		BBHRE_Handler::init();

		return self::$instance;
	}

	private function migrate_table() {
		global $wpdb;

		$old_table = $wpdb->prefix . 'bbhre_redirects';
		$new_table = $this->table_name;

		if ( $old_table === $new_table ) {
			return;
		}

		// Check existence
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table )
		);

		if ( ! $exists ) {
			return;
		}

		// 🔒 Hard validation (critical for security + satisfies PluginCheck reasoning)
		$old_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $old_table );
		$new_table = preg_replace( '/[^a-zA-Z0-9_]/', '', $new_table );

		$sql = "RENAME TABLE `$old_table` TO `$new_table`";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query( $sql );
    
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
		wp_cache_flush();
	}

	private function create_table() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			source_url varchar(2000) NOT NULL,
			destination_url varchar(2000) NOT NULL,
			options varchar(500) DEFAULT NULL,
			hits_count bigint(20) unsigned DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_source (source_url(200)),
			KEY idx_updated (updated_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function get_table() {
		return $this->table_name;
	}

	public function get_all( $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = $this->table_name;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d",
				absint( $limit ),
				absint( $offset )
			)
		);
	}

	public function get_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
	}

	public function get_by_id( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				absint( $id )
			)
		);
	}

	public function get_by_source( $source ) {
		global $wpdb;
		$normalized = $this->normalize_source( $source );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table_name} WHERE source_url = %s",
				$normalized
			)
		);
	}

	public function add( $source_url, $destination_url ) {
		global $wpdb;

		$source      = $this->normalize_source( $source_url );
		$destination = $this->normalize_destination( $destination_url );

		if ( ! $this->validate_source( $source ) ) {
			return new WP_Error( 'invalid_source', __( 'Invalid source URL format.', 'bbh-redirection' ) );
		}

		if ( ! $this->validate_destination( $destination ) ) {
			return new WP_Error( 'invalid_destination', __( 'Invalid destination URL format.', 'bbh-redirection' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table_name} WHERE source_url = %s",
				$source
			)
		);

		if ( $exists ) {
			return new WP_Error( 'duplicate', __( 'This source URL already has a redirect.', 'bbh-redirection' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$this->table_name,
			array(
				'source_url'      => $source,
				'destination_url' => $destination,
			),
			array( '%s', '%s' )
		);

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return new WP_Error( 'insert_failed', __( 'Failed to add redirect.', 'bbh-redirection' ) );
	}

	public function update( $id, $source_url, $destination_url ) {
		global $wpdb;

		$id         = absint( $id );
		$source     = $this->normalize_source( $source_url );
		$destination = $this->normalize_destination( $destination_url );

		if ( ! $this->validate_source( $source ) ) {
			return new WP_Error( 'invalid_source', __( 'Invalid source URL format.', 'bbh-redirection' ) );
		}

		if ( ! $this->validate_destination( $destination ) ) {
			return new WP_Error( 'invalid_destination', __( 'Invalid destination URL format.', 'bbh-redirection' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table_name} WHERE source_url = %s AND id != %d",
				$source,
				$id
			)
		);

		if ( $exists ) {
			return new WP_Error( 'duplicate', __( 'This source URL already has a redirect.', 'bbh-redirection' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table_name,
			array(
				'source_url'      => $source,
				'destination_url' => $destination,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			return true;
		}

		return new WP_Error( 'update_failed', __( 'Failed to update redirect.', 'bbh-redirection' ) );
	}

	public function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->delete(
			$this->table_name,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	public function delete_many( $ids ) {
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return false;
		}

		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return false;
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// Construct the raw SQL statement safely using placeholders
		$sql = "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)";

		// Pass the prepare() execution directly into the query execution
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->query( $wpdb->prepare( $sql, ...$ids ) );

	}

	public function increment_hits( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->table_name} SET hits_count = hits_count + 1 WHERE id = %d",
				absint( $id )
			)
		);
	}

	public function normalize_source( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		$url = trim( $url );
		$url = wp_unslash( $url );

		$parsed = wp_parse_url( $url );

		if ( isset( $parsed['path'] ) && ! empty( $parsed['path'] ) ) {
			$path = $parsed['path'];
		} else {
			$path = '/';
		}

		$path = strtolower( $path );
		$path = preg_replace( '#/+#', '/', $path );
		$path = untrailingslashit( $path );

		if ( empty( $path ) ) {
			$path = '/';
		}

		return $path;
	}

	public function normalize_destination( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		$url = trim( $url );
		$url = wp_unslash( $url );
		$url = esc_url_raw( $url, array( 'http', 'https' ) );

		return $url;
	}

	public function validate_source( $url ) {
		if ( empty( $url ) || '/' !== $url[0] ) {
			return false;
		}

		if ( strlen( $url ) > 2000 ) {
			return false;
		}

		if ( false !== strpos( $url, '..' ) ) {
			return false;
		}

		return true;
	}

	public function validate_destination( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		if ( strlen( $url ) > 2000 ) {
			return false;
		}

		return (bool) wp_http_validate_url( $url );
	}

	public function check_loop( $source, $destination ) {
		$source_normalized = $this->normalize_source( $source );
		$dest_normalized   = $this->normalize_destination( $destination );

		$parsed = wp_parse_url( $dest_normalized );
		if ( ! isset( $parsed['path'] ) ) {
			return false;
		}

		$dest_path = $this->normalize_source( $parsed['path'] );

		if ( $source_normalized === $dest_path ) {
			return true;
		}

		$existing = $this->get_by_source( $dest_path );
		if ( $existing ) {
			return true;
		}

		return false;
	}
}