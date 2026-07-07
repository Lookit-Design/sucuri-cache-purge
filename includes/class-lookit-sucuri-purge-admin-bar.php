<?php
defined( 'ABSPATH' ) || exit;

class Lookit_Sucuri_Purge_Admin_Bar {

	const HANDLE = 'lookit-sucuri-purge-admin-bar';

	public static function init() {
		add_action( 'admin_bar_menu',        array( __CLASS__, 'add_purge_button' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts',    array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_purge_button( WP_Admin_Bar $wp_admin_bar ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_url = self::get_current_url();

		$wp_admin_bar->add_node( array(
			'id'    => 'lookit-sucuri-purge-group',
			'title' => '🛡 Sucuri Cache Purge',
			'href'  => false,
			'meta'  => array( 'class' => 'lookit-sucuri-purge-top-level' ),
		) );

		if ( $current_url ) {
			$wp_admin_bar->add_node( array(
				'id'     => 'lookit-sucuri-purge-url',
				'parent' => 'lookit-sucuri-purge-group',
				'title'  => 'Purge This URL',
				'href'   => 'javascript:void(0)',
				'meta'   => array( 'title' => $current_url ),
			) );
		}

		$wp_admin_bar->add_node( array(
			'id'     => 'lookit-sucuri-purge-all',
			'parent' => 'lookit-sucuri-purge-group',
			'title'  => 'Purge Entire Site',
			'href'   => 'javascript:void(0)',
		) );
	}

	private static function get_current_url(): string {

		if ( is_admin() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only use of post ID from URL, no form processing
			$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
			if ( $post_id ) {
				return (string) get_permalink( $post_id );
			}
			return '';
		}

		if ( is_singular() ) {
			return (string) get_permalink();
		}
		if ( is_tax() || is_category() || is_tag() ) {
			return (string) get_term_link( get_queried_object() );
		}
		if ( is_post_type_archive() ) {
			return (string) get_post_type_archive_link( get_queried_object()->name );
		}
		if ( is_author() ) {
			return (string) get_author_posts_url( get_queried_object_id() );
		}
		if ( is_home() || is_front_page() ) {
			return (string) home_url( '/' );
		}

		global $wp;
		return home_url( $wp->request ? '/' . $wp->request . '/' : '/' );
	}

	public static function enqueue_assets() {

		if ( ! is_admin_bar_showing() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			LOOKIT_SUCURI_PURGE_URL . 'assets/css/admin-bar.css',
			array( 'admin-bar' ),
			LOOKIT_SUCURI_PURGE_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			LOOKIT_SUCURI_PURGE_URL . 'assets/js/admin-bar.js',
			array(),
			LOOKIT_SUCURI_PURGE_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'LOOKIT_SUCURI',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonceUrl'   => wp_create_nonce( 'lookit_sucuri_purge' ),
				'nonceAll'   => wp_create_nonce( 'lookit_sucuri_purge_all' ),
				'currentUrl' => self::get_current_url(),
			)
		);
	}
}
