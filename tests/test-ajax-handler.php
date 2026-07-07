<?php
/**
 * @package Lookit_Sucuri_Purge
 *
 * @group ajax
 */

class Test_Lookit_Sucuri_Purge_Ajax_Handler extends WP_Ajax_UnitTestCase {

	const VALID_KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

	public function set_up() {
		parent::set_up();
		update_option(
			Lookit_Sucuri_Purge_Settings::OPTION_KEY,
			array( 'api_key' => self::VALID_KEY )
		);
		add_filter( 'pre_http_request', array( $this, 'sucuri_success' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'sucuri_success' ), 10 );
		delete_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY );
		unset( $_POST['nonce'], $_POST['url'], $_REQUEST['nonce'], $_REQUEST['url'] );
		parent::tear_down();
	}

	public function sucuri_success() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array( 'status' => 1 ) ),
		);
	}

	private function set_nonce( $action ) {
		$nonce             = wp_create_nonce( $action );
		$_POST['nonce']    = $nonce;
		$_REQUEST['nonce'] = $nonce;
	}

	private function dispatch( $action ) {
		$died = false;
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			$died = true;
		} catch ( WPAjaxDieStopException $e ) {
			$died = true;
		}

		$this->assertTrue( $died, 'AJAX handler should terminate via wp_die.' );

		return json_decode( $this->_last_response, true );
	}

	public function test_purge_url_succeeds_for_admin_with_valid_same_site_url() {
		$this->_setRole( 'administrator' );
		$this->set_nonce( 'lookit_sucuri_purge' );
		$_POST['url'] = home_url( '/sample-page/' );

		$response = $this->dispatch( 'lookit_sucuri_purge_url' );

		$this->assertTrue( $response['success'] );
	}

	public function test_purge_url_rejects_invalid_url() {
		$this->_setRole( 'administrator' );
		$this->set_nonce( 'lookit_sucuri_purge' );
		$_POST['url'] = 'javascript:alert(1)';

		$response = $this->dispatch( 'lookit_sucuri_purge_url' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid URL', $response['data']['message'] );
	}

	public function test_purge_url_rejects_foreign_host() {
		$this->_setRole( 'administrator' );
		$this->set_nonce( 'lookit_sucuri_purge' );
		$_POST['url'] = 'https://evil.example.net/page/';

		$response = $this->dispatch( 'lookit_sucuri_purge_url' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'does not belong', $response['data']['message'] );
	}

	public function test_purge_url_rejects_bad_nonce() {
		$this->_setRole( 'administrator' );
		$_POST['nonce']    = 'invalid';
		$_REQUEST['nonce'] = 'invalid';
		$_POST['url']      = home_url( '/sample-page/' );

		$response = $this->dispatch( 'lookit_sucuri_purge_url' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Security check', $response['data']['message'] );
	}

	public function test_purge_url_denied_for_non_admin() {
		$this->_setRole( 'subscriber' );
		$this->set_nonce( 'lookit_sucuri_purge' );
		$_POST['url'] = home_url( '/sample-page/' );

		$response = $this->dispatch( 'lookit_sucuri_purge_url' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Permission denied', $response['data']['message'] );
	}

	public function test_purge_all_succeeds_for_admin() {
		$this->_setRole( 'administrator' );
		$this->set_nonce( 'lookit_sucuri_purge_all' );

		$response = $this->dispatch( 'lookit_sucuri_purge_all' );

		$this->assertTrue( $response['success'] );
	}

	public function test_purge_all_denied_for_non_admin() {
		$this->_setRole( 'subscriber' );
		$this->set_nonce( 'lookit_sucuri_purge_all' );

		$response = $this->dispatch( 'lookit_sucuri_purge_all' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Permission denied', $response['data']['message'] );
	}
}
