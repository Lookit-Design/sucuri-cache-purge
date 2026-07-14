<?php
/**
 * @package Lookit_Sucuri_Purge
 */

class Test_Lookit_Sucuri_Purge_Uninstall extends WP_UnitTestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_deletes_credentials_option() {
		update_option(
			Lookit_Sucuri_Purge_Settings::OPTION_KEY,
			array( 'api_key' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' )
		);

		define( 'WP_UNINSTALL_PLUGIN', 'lookit-cache-purge-for-sucuri/lookit-cache-purge-for-sucuri.php' );
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( Lookit_Sucuri_Purge_Settings::OPTION_KEY ) );
	}
}
