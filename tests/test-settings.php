<?php
/**
 * @package Lookit_Sucuri_Purge
 */

class Test_Lookit_Sucuri_Purge_Settings extends WP_UnitTestCase {

	const VALID_KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

	public function tear_down() {
		delete_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY );
		parent::tear_down();
	}

	public function test_sanitize_accepts_valid_combined_key() {
		$result = Lookit_Sucuri_Purge_Settings::sanitize_settings( array( 'api_key' => self::VALID_KEY ) );

		$this->assertSame( self::VALID_KEY, $result['api_key'] );
	}

	public function test_sanitize_trims_surrounding_whitespace() {
		$result = Lookit_Sucuri_Purge_Settings::sanitize_settings( array( 'api_key' => '  ' . self::VALID_KEY . '  ' ) );

		$this->assertSame( self::VALID_KEY, $result['api_key'] );
	}

	public function test_sanitize_blank_keeps_existing_key() {
		update_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY, array( 'api_key' => self::VALID_KEY ) );

		$result = Lookit_Sucuri_Purge_Settings::sanitize_settings( array( 'api_key' => '' ) );

		$this->assertSame( self::VALID_KEY, $result['api_key'] );
	}

	public function test_render_field_never_outputs_the_saved_key() {
		update_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY, array( 'api_key' => self::VALID_KEY ) );

		ob_start();
		Lookit_Sucuri_Purge_Settings::render_api_key_field();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( self::VALID_KEY, $html );
		$this->assertStringContainsString( 'value=""', $html );
	}

	public function test_sanitize_rejects_invalid_format_and_preserves_existing() {
		update_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY, array( 'api_key' => self::VALID_KEY ) );

		$result = Lookit_Sucuri_Purge_Settings::sanitize_settings( array( 'api_key' => 'not-a-valid-key' ) );

		$this->assertSame( self::VALID_KEY, $result['api_key'] );
	}

	public function test_get_api_credentials_splits_combined_key() {
		update_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY, array( 'api_key' => self::VALID_KEY ) );

		list( $key, $secret ) = Lookit_Sucuri_Purge_Settings::get_api_credentials();

		$this->assertSame( str_repeat( 'a', 32 ), $key );
		$this->assertSame( str_repeat( 'b', 32 ), $secret );
	}

	public function test_get_api_credentials_returns_nulls_when_unset() {
		$this->assertSame( array( null, null ), Lookit_Sucuri_Purge_Settings::get_api_credentials() );
	}

	public function test_is_configured_requires_both_parts() {
		$this->assertFalse( Lookit_Sucuri_Purge_Settings::is_configured() );

		update_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY, array( 'api_key' => 'no-slash-value' ) );
		$this->assertFalse( Lookit_Sucuri_Purge_Settings::is_configured() );

		update_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY, array( 'api_key' => self::VALID_KEY ) );
		$this->assertTrue( Lookit_Sucuri_Purge_Settings::is_configured() );
	}
}
