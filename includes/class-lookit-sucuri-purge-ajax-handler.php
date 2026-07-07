<?php
defined( 'ABSPATH' ) || exit;

class Lookit_Sucuri_Purge_Ajax_Handler {

	/**
	 * Maximum successful purges per user in the trailing 60s window.
	 * Leaves headroom under Sucuri's 12/min hard ceiling in case multiple
	 * admins are purging simultaneously.
	 */
	const RATE_LIMIT_MAX     = 6;
	const RATE_LIMIT_WINDOW  = 60;  // seconds
	const TRANSIENT_LIFETIME = 120; // seconds (transient outlives the window)

	public static function init() {
		add_action( 'wp_ajax_lookit_sucuri_purge_url', array( __CLASS__, 'handle_purge_url' ) );
		add_action( 'wp_ajax_lookit_sucuri_purge_all', array( __CLASS__, 'handle_purge_all' ) );
	}

	public static function handle_purge_url() {

		self::check_auth( 'lookit_sucuri_purge' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in self::check_auth() above; the sniff cannot trace it across the helper-method call.
		$raw_url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$url     = esc_url_raw( $raw_url );

		if ( empty( $url ) || ! preg_match( '#^https?://#i', $url ) ) {
			wp_send_json_error( array( 'message' => 'Invalid URL provided.' ) );
		}

		// Must belong to this site
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$req_host  = wp_parse_url( $url, PHP_URL_HOST );

		if ( $site_host !== $req_host ) {
			wp_send_json_error( array( 'message' => 'URL does not belong to this site.' ) );
		}

		// Client-side rate limit check
		$rate_check = self::check_rate_limit();
		if ( ! $rate_check['allowed'] ) {
			wp_send_json_error( array(
				'message'      => sprintf(
					'Rate limit: %d purges per minute. Wait %d seconds before the next purge.',
					self::RATE_LIMIT_MAX,
					$rate_check['wait_seconds']
				),
				'rate_limited' => true,
			) );
		}

		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_url( $url );

		if ( $result['success'] ) {
			self::record_purge();
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( array(
				'message'      => $result['message'],
				'rate_limited' => ! empty( $result['rate_limited'] ),
			) );
		}
	}

	public static function handle_purge_all() {

		self::check_auth( 'lookit_sucuri_purge_all' );

		// Client-side rate limit check
		$rate_check = self::check_rate_limit();
		if ( ! $rate_check['allowed'] ) {
			wp_send_json_error( array(
				'message'      => sprintf(
					'Rate limit: %d purges per minute. Wait %d seconds before the next purge.',
					self::RATE_LIMIT_MAX,
					$rate_check['wait_seconds']
				),
				'rate_limited' => true,
			) );
		}

		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_all();

		if ( $result['success'] ) {
			self::record_purge();
			wp_send_json_success( $result['message'] );
		} else {
			wp_send_json_error( array(
				'message'      => $result['message'],
				'rate_limited' => ! empty( $result['rate_limited'] ),
			) );
		}
	}

	/**
	 * Verify user permissions and the action-specific nonce, or fail the request.
	 *
	 * @param string $nonce_action The nonce action tied to this AJAX endpoint.
	 */
	private static function check_auth( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Please refresh and try again.' ), 403 );
		}
	}

	/**
	 * Check whether the current user is within the 6/min rate limit.
	 *
	 * @return array{allowed:bool, wait_seconds:int}
	 */
	private static function check_rate_limit(): array {

		$key    = self::get_transient_key();
		$log    = get_transient( $key );
		$now    = time();
		$cutoff = $now - self::RATE_LIMIT_WINDOW;

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Drop timestamps older than the window
		$log = array_values( array_filter( $log, function( $ts ) use ( $cutoff ) {
			return (int) $ts > $cutoff;
		} ) );

		if ( count( $log ) >= self::RATE_LIMIT_MAX ) {
			// Oldest timestamp in the window tells us when the next slot opens up
			$oldest       = min( $log );
			$wait_seconds = max( 1, ( $oldest + self::RATE_LIMIT_WINDOW ) - $now );
			return array(
				'allowed'      => false,
				'wait_seconds' => $wait_seconds,
			);
		}

		return array(
			'allowed'      => true,
			'wait_seconds' => 0,
		);
	}

	/**
	 * Record a successful purge in the rate limit log.
	 */
	private static function record_purge() {

		$key    = self::get_transient_key();
		$log    = get_transient( $key );
		$now    = time();
		$cutoff = $now - self::RATE_LIMIT_WINDOW;

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		// Drop old entries
		$log = array_values( array_filter( $log, function( $ts ) use ( $cutoff ) {
			return (int) $ts > $cutoff;
		} ) );

		$log[] = $now;

		set_transient( $key, $log, self::TRANSIENT_LIFETIME );
	}

	private static function get_transient_key(): string {
		return 'lookit_sucuri_purge_log_' . get_current_user_id();
	}
}
