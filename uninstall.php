<?php
/**
 * Uninstall BBH Redirection
 *
 * @package Bbhre_Redirection
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$bbhre_table_name = $wpdb->prefix . 'bbhre_redirects';

// Safely escape the table name manually since %i isn't supported in WP 5.2
$bbhre_safe_table_name = esc_sql( $bbhre_table_name );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$bbhre_safe_table_name}`" );