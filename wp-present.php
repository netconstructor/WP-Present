<?php
/*
Plugin Name: WP Present
Plugin URI: http://wppresent.org/
Description: Create beautiful slide presentations with the the power of WordPress and the elegance of reveal.js
Author: stevenkword
Version: 0.9.4
Author URI: http://stevenword.com/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2013 Steven K. Word

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define( 'WP_PRESENT_VERSION', 1 );

/**
 ** Loader Class
 **
 ** @since 0.9.4
 **/
class WP_Present_Loader {

	const OPTION_VERSION  = 'wp-present-version';
	private $version = false;

	// Define and register singleton
	private static $instance = false;
	public static function instance() {
		if( ! self::$instance ) {
			self::$instance = new WP_Present_Loader;
		}
		return self::$instance;
	}

	/**
	 * Clone
	 *
	 * @since 1.0.0
	 */
	private function __clone() { }

	/**
	 * Add actions and filters
	 *
	 * @uses add_action, add_filter
	 * @since 1.0.0
	 */
	function __construct() {

		// Version Check
		if( $version = get_option( self::OPTION_VERSION, false ) ) {
			$this->version = $version;
		} else {
			$this->version = WP_PRESENT_VERSION;
			add_option( self::OPTION_VERSION, $this->version );
		}

		// Load the plugin
		require( plugin_dir_path( __FILE__ ) . 'includes/class-wp-present-core.php' );

		// Check the things
		//if( isset( $_REQUEST[ 'tag_ID' ] ) && isset( $_GET['taxonomy'] ) && WP_Present_Core::instance()->taxonomy_slug == $_GET['taxonomy'] ) {
		if( is_admin() )
			require( plugin_dir_path( __FILE__ ) . 'includes/class-modal-customizer.php' );
		//}

		// On Activation
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// On Dactivations
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Perform updates if necessary
		add_action( 'init', array( $this, 'action_init_check_version' ) );
	}

	/**
	 * On plugin activation
	 *
	 * @uses flush_rewrite_rules()
	 * @return null
	 */
	public function activate() {
		WP_Present_Core::action_init_register_post_type();
		WP_Present_Core::action_init_register_taxonomy();
		flush_rewrite_rules();
	}

	/**
	 * On plugin deactivation
	 *
	 * @uses flush_rewrite_rules()
	 * @return null
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Version Check
	 *
	 * @since 1.0.0
	 */
	function action_init_check_version() {
		// Check if the version has changed and if so perform the necessary actions
		if ( ! isset( $this->version ) || $this->version <  WP_PRESENT_VERSION ) {

			// Perform updates if necessary

			// Update the version information in the database
			update_option( self::OPTION_VERSION, WP_PRESENT_VERSION );
		}
	}

} // Class
WP_Present_Loader::instance();