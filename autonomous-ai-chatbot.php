<?php
/**
 * Plugin Name:          Autonomous AI Chatbot
 * Description:          Autonomous AI Chatbot for your WordPress site.
 * Version:              1.0.0
 * Author:               Webkul
 * Author URI:           https://webkul.com/
 * Text Domain:          autonomous-ai-chatbot
 * Domain Path:          /languages
 * License:              GPLv3
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least:    6.5
 * Requires PHP:         7.4
 *
 * @package Autonomous AI Chatbot
 */

defined( 'ABSPATH' ) || exit();

use WCAICHATBOT\Includes;

// Define Constants.
defined( 'WC_CPTBOT_PLUGIN_FILE' ) || define( 'WC_CPTBOT_PLUGIN_FILE', plugin_dir_path( __FILE__ ) );
defined( 'WC_CPTBOT_PLUGIN_URL' ) || define( 'WC_CPTBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'WC_CPTBOT_SCRIPT_VERSION' ) || define( 'WC_CPTBOT_SCRIPT_VERSION', '1.0.0' );
defined( 'WC_CPTBOT_PRODUCTS_PER_BATCH' ) || define( 'WC_CPTBOT_PRODUCTS_PER_BATCH', 5 );
defined( 'WC_CPTBOT_PLUGIN_BASENAME' ) || define( 'WC_CPTBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
defined( 'WCAICHATBOT_BASE_ACTIVE' ) || define( 'WCAICHATBOT_BASE_ACTIVE', true );

final class WKChatGPT_AI_Bot {


	/**
	 * Initialize plugin.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_plugin' ) );
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Load plugin files.
	 */
	public static function load_plugin() {
		include_once WC_CPTBOT_PLUGIN_FILE . 'vendor/autoload.php';
		include_once WC_CPTBOT_PLUGIN_FILE . 'includes/autoload.php';
		include_once WC_CPTBOT_PLUGIN_FILE . 'includes/class-wc-cptbot-pro-banner.php';
		new Includes\Wc_Cptbot_File_Handler();

		/**
		 * Action hook fired after the core AI Chatbot plugin is completely loaded.
		 * Allows PRO addons and integrations to attach their hooks.
		 */
		do_action( 'wcaichatbot_loaded' );
	}

	/**
	 * Declare HPOS compatibility.
	 */
	public static function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
}

WKChatGPT_AI_Bot::init();

/*
-------------------------------------------------------------------------- */
/*
Activation Hook                                                            */
/* -------------------------------------------------------------------------- */

require_once WC_CPTBOT_PLUGIN_FILE . 'includes/class-wc-cptbot-table-install.php';

register_activation_hook(
	__FILE__,
	array( 'Wc_Cptbot_Table_Install', 'wc_cptbot_install_tables' )
);
