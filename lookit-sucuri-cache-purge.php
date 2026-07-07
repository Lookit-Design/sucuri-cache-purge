<?php
/**
 * Plugin Name:  Lookit Sucuri Cache Purge
 * Plugin URI:   https://lookitdesign.com/software/sucuri-cache-purge/
 * Description:  Adds a single-URL Sucuri cache purge button to the wp-admin admin bar.
 * Version:      1.0.0
 * Author:       Lookit Design
 * Author URI:   https://lookitdesign.com
 * License:      GPL-2.0+
 * Text Domain:  lookit-sucuri-cache-purge
 */

defined( 'ABSPATH' ) || exit;

define( 'LOOKIT_SUCURI_PURGE_VERSION', '1.0.0' );
define( 'LOOKIT_SUCURI_PURGE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'LOOKIT_SUCURI_PURGE_URL',     plugin_dir_url( __FILE__ ) );

require_once LOOKIT_SUCURI_PURGE_DIR . 'includes/class-settings.php';
require_once LOOKIT_SUCURI_PURGE_DIR . 'includes/class-sucuri-api.php';
require_once LOOKIT_SUCURI_PURGE_DIR . 'includes/class-admin-bar.php';
require_once LOOKIT_SUCURI_PURGE_DIR . 'includes/class-ajax-handler.php';

add_action( 'plugins_loaded', array( 'Lookit_Sucuri_Purge_Settings', 'init' ) );
add_action( 'plugins_loaded', array( 'Lookit_Sucuri_Purge_Admin_Bar', 'init' ) );
add_action( 'plugins_loaded', array( 'Lookit_Sucuri_Purge_Ajax_Handler', 'init' ) );
