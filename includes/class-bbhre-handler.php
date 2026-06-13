<?php
/**
 * Handler class for Bbh Redirection frontend redirects.
 *
 * @package Bbhre_Redirection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBHRE_Handler {

	private static $instance = null;
	private $base;
	private $handled = false;

	private function __construct() {
		$this->base = BBHRE_Base::get_instance();
	}

	public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		add_action( 'template_redirect', array( self::$instance, 'handle_redirect' ), 1 );

		return self::$instance;
	}

	public function handle_redirect() {
		if ( $this->handled ) {
			return;
		}

		if ( is_admin() || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}

		$this->handled = true;

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( empty( $request_uri ) ) {
			return;
		}

		$parsed = wp_parse_url( $request_uri );

		if ( empty( $parsed['path'] ) || '/' === $parsed['path'] ) {
			return;
		}

		$path = $parsed['path'];

		$excluded = array( '/wp-admin', '/wp-content', '/wp-includes', '/xmlrpc.php', '/wp-cron.php' );

		foreach ( $excluded as $ex ) {
			if ( 0 === strpos( $path, $ex ) ) {
				return;
			}
		}

		$redirect = $this->base->get_by_source( $path );

		if ( ! $redirect || empty( $redirect->destination_url ) ) {
			return;
		}

		$this->base->increment_hits( $redirect->id );

		$destination = $redirect->destination_url;
		$status = 301;

		$parsed_dest = wp_parse_url( $destination );
		if ( ! empty( $parsed_dest['host'] ) ) {
			add_filter( 'allowed_redirect_hosts', array( $this, 'add_allowed_host' ) );
			$this->redirect_host = $parsed_dest['host'];
		}

		wp_safe_redirect( $destination, $status );
		exit;
	}

	public function add_allowed_host( $hosts ) {
		if ( ! empty( $this->redirect_host ) ) {
			$hosts[] = $this->redirect_host;
		}
		return $hosts;
	}

	private $redirect_host = null;
}