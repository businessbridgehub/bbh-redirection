<?php
/**
 * Plugin Name: BBH Redirection
 * Plugin URI: https://wordpress.org/plugins/bbh-redirection/
 * Version: 1.1.0
 * Description: A lightweight WordPress plugin that provides simple 301 redirect functionality with a clean admin UI.
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Jahid Shah
 * Author URI: https://jahidshah.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bbh-redirection
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'BBH_REDIRECTION_VERSION' ) ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

define(
	'BBH_REDIRECTION_VERSION',
	get_file_data(
		__FILE__,
		array(
			'Version' => 'Version',
		)
	)['Version']
);

define( 'BBH_REDIRECTION_FILE', __FILE__ );
define( 'BBH_REDIRECTION_TABLE_NAME', 'bbhre_redirects' );
define( 'BBH_REDIRECTION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once BBH_REDIRECTION_PLUGIN_DIR . '/includes/class-bbhre-base.php';
require_once BBH_REDIRECTION_PLUGIN_DIR . '/includes/class-bbhre-admin.php';
require_once BBH_REDIRECTION_PLUGIN_DIR . '/includes/class-bbhre-handler.php';
require_once BBH_REDIRECTION_PLUGIN_DIR . '/includes/class-bbhre-404-monitor.php';
require_once BBH_REDIRECTION_PLUGIN_DIR . '/includes/class-bbhre-documentation.php';

BBHRE_Base::init();
BBHRE_404_Monitor::init();