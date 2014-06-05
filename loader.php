<?php
/*
Plugin Name: Desire2Learn BuddyPress Integration
Description: Integrates selected D2L functionality with BuddyPress.
Author: r-a-y
Author URI: http://profiles.wordpres.org/r-a-y/
Version: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Desire2Learn BuddyPress Integration
 *
 * @package BP_D2L
 * @subpackage Loader
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Only load the component if BuddyPress is loaded and initialized.
 */
function bp_d2l_init() {
	// some pertinent defines
	define( 'BP_D2L_DIR', dirname( __FILE__ ) );
	define( 'BP_D2L_URL', plugin_dir_url( __FILE__ ) );

	require( BP_D2L_DIR . '/bp-d2l-core.php' );
}
add_action( 'bp_include', 'bp_d2l_init' );