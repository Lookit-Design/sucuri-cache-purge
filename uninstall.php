<?php
/**
 * Uninstall routine for Lookit Cache Purge for Sucuri.
 *
 * Removes the stored Sucuri credentials so the API key does not linger
 * in the database after the plugin is deleted.
 *
 * @package Lookit_Sucuri_Purge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$lookit_sucuri_purge_option = 'lookit_sucuri_purge_settings';

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $lookit_sucuri_purge_site_id ) {
		switch_to_blog( $lookit_sucuri_purge_site_id );
		delete_option( $lookit_sucuri_purge_option );
		restore_current_blog();
	}
} else {
	delete_option( $lookit_sucuri_purge_option );
}
