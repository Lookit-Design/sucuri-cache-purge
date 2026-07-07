<?php
defined( 'ABSPATH' ) || exit;

class Lookit_Sucuri_Purge_Sucuri_Api {

	const API_ENDPOINT = 'https://waf.sucuri.net/api?v2';

	/**
	 * Purge a single URL from Sucuri's edge cache.
	 *
	 * Sucuri's API takes a path (not a full URL) in the `file` parameter and
	 * concatenates "<domain>/<file>" naively. We strip the scheme, host, and
	 * leading slash before sending.
	 */
	public static function purge_url( string $url ): array {

		if ( ! Lookit_Sucuri_Purge_Settings::is_configured() ) {
			return array(
				'success' => false,
				'message' => 'Sucuri credentials are not configured.',
			);
		}

		$path = self::url_to_sucuri_path( $url );

		if ( '' === $path ) {
			return array(
				'success' => false,
				'message' => 'Unable to extract a valid path from the URL.',
			);
		}

		list( $k, $s ) = Lookit_Sucuri_Purge_Settings::get_api_credentials();

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 20,
				'body'    => array(
					'k'    => $k,
					's'    => $s,
					'a'    => 'clear_cache',
					'file' => $path,
				),
			)
		);

		return self::parse_response( $response, 'Purge sent for: ' . $url );
	}

	/**
	 * Purge the entire site cache from Sucuri.
	 */
	public static function purge_all(): array {

		if ( ! Lookit_Sucuri_Purge_Settings::is_configured() ) {
			return array(
				'success' => false,
				'message' => 'Sucuri credentials are not configured.',
			);
		}

		list( $k, $s ) = Lookit_Sucuri_Purge_Settings::get_api_credentials();

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 20,
				'body'    => array(
					'k' => $k,
					's' => $s,
					'a' => 'clear_cache',
				),
			)
		);

		return self::parse_response( $response, 'Full site purge sent to Sucuri.' );
	}

	/**
	 * Convert a full URL to the path-only format Sucuri's API expects.
	 *
	 * Example: "https://example.com/about/" -> "about/"
	 */
	private static function url_to_sucuri_path( string $url ): string {

		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			return '';
		}

		$path  = $parts['path'] ?? '/';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		// Strip leading slash so Sucuri constructs "domain.com/path/" cleanly.
		return ltrim( $path . $query, '/' );
	}

	/**
	 * Parse a wp_remote_post response into a normalized result array.
	 *
	 * Returns:
	 *   [
	 *     'success'      => bool,
	 *     'message'      => string,
	 *     'rate_limited' => bool (optional),
	 *     'retry_after'  => int  (optional, seconds),
	 *   ]
	 */
	private static function parse_response( $response, string $success_message ): array {

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Request failed: ' . $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_raw    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $body_raw, true );

		// HTTP 429: Sucuri's formal rate limiter
		if ( 429 === $status_code ) {
			$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			if ( $retry_after <= 0 && is_array( $body ) && isset( $body['rate_limit']['reset_time'] ) ) {
				$retry_after = max( 0, (int) $body['rate_limit']['reset_time'] - time() );
			}
			return array(
				'success'      => false,
				'message'      => sprintf( 'Sucuri rate limited — wait %d seconds before trying again.', max( 1, $retry_after ) ),
				'rate_limited' => true,
				'retry_after'  => $retry_after,
			);
		}

		// Unparseable response
		if ( ! is_array( $body ) ) {
			return array(
				'success' => false,
				'message' => 'Unexpected response from Sucuri (HTTP ' . $status_code . ').',
			);
		}

		// Sucuri's own status field: 1 = success, 0 = failure
		if ( ! empty( $body['status'] ) && 1 === (int) $body['status'] ) {
			return array(
				'success' => true,
				'message' => $success_message,
			);
		}

		// Extract error message(s). Sucuri's `messages` field is sometimes a string, sometimes an array.
		$error_text = 'Unknown error.';
		if ( isset( $body['messages'] ) ) {
			if ( is_array( $body['messages'] ) ) {
				$error_text = implode( ' | ', $body['messages'] );
			} elseif ( is_string( $body['messages'] ) ) {
				$error_text = $body['messages'];
			}
		}

		// "Internal error" from Sucuri almost always means auth failure.
		if ( false !== stripos( $error_text, 'internal error' ) ) {
			$error_text = 'Authentication failed. Check your API Key in Settings → Sucuri Cache Purge.';
		}

		return array(
			'success' => false,
			'message' => 'Sucuri API error: ' . $error_text,
		);
	}
}
