<?php
/**
 * @package Lookit_Sucuri_Purge
 */

class Test_Lookit_Sucuri_Purge_Sucuri_Api extends WP_UnitTestCase {

	const VALID_KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

	/**
	 * Captured outgoing request, populated by the pre_http_request filter.
	 *
	 * @var array
	 */
	private $captured = array();

	/**
	 * Canned response (array, WP_Error, or callable) the next request returns.
	 *
	 * @var mixed
	 */
	private $next_response = null;

	public function set_up() {
		parent::set_up();
		$this->captured      = array();
		$this->next_response = null;
		add_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10 );
		delete_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY );
		parent::tear_down();
	}

	public function intercept_http( $preempt, $args, $url ) {
		$this->captured = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( is_callable( $this->next_response ) ) {
			return call_user_func( $this->next_response, $args, $url );
		}

		return $this->next_response;
	}

	private function configure() {
		update_option(
			Lookit_Sucuri_Purge_Settings::OPTION_KEY,
			array( 'api_key' => self::VALID_KEY )
		);
	}

	private function sucuri_ok() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'status'   => 1,
					'messages' => array( 'OK' ),
				)
			),
		);
	}

	public function test_purge_url_short_circuits_when_not_configured() {
		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_url( 'https://example.com/page/' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'not configured', $result['message'] );
		$this->assertEmpty( $this->captured, 'No HTTP request should be made when unconfigured.' );
	}

	public function test_purge_url_sends_path_only_and_succeeds() {
		$this->configure();
		$this->next_response = $this->sucuri_ok();

		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_url( 'https://example.com/page/' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'https://waf.sucuri.net/api?v2', $this->captured['url'] );

		$body = $this->captured['args']['body'];
		$this->assertSame( str_repeat( 'a', 32 ), $body['k'] );
		$this->assertSame( str_repeat( 'b', 32 ), $body['s'] );
		$this->assertSame( 'clear_cache', $body['a'] );
		$this->assertSame( 'page/', $body['file'] );
	}

	public function test_purge_all_sends_clear_cache_without_file() {
		$this->configure();
		$this->next_response = $this->sucuri_ok();

		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_all();

		$this->assertTrue( $result['success'] );

		$body = $this->captured['args']['body'];
		$this->assertSame( 'clear_cache', $body['a'] );
		$this->assertArrayNotHasKey( 'file', $body );
	}

	public function test_error_response_reports_sucuri_message() {
		$this->configure();
		$this->next_response = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'status'   => 0,
					'messages' => array( 'Invalid API key' ),
				)
			),
		);

		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_url( 'https://example.com/page/' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Invalid API key', $result['message'] );
	}

	public function test_internal_error_maps_to_auth_message() {
		$this->configure();
		$this->next_response = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'status'   => 0,
					'messages' => array( 'Internal error' ),
				)
			),
		);

		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_url( 'https://example.com/page/' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Authentication failed', $result['message'] );
	}

	public function test_rate_limited_response_is_flagged() {
		$this->configure();
		$this->next_response = array(
			'response' => array( 'code' => 429 ),
			'headers'  => array( 'retry-after' => '30' ),
			'body'     => wp_json_encode( array( 'status' => 0 ) ),
		);

		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_url( 'https://example.com/page/' );

		$this->assertFalse( $result['success'] );
		$this->assertTrue( $result['rate_limited'] );
		$this->assertStringContainsString( 'rate limited', $result['message'] );
	}

	public function test_transport_error_is_reported() {
		$this->configure();
		$this->next_response = new WP_Error( 'http_request_failed', 'Could not resolve host' );

		$result = Lookit_Sucuri_Purge_Sucuri_Api::purge_url( 'https://example.com/page/' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Could not resolve host', $result['message'] );
	}
}
