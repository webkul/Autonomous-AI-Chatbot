<?php

/**
 * Admin hooks
 *
 * @package Chatgpt bot for WooCommerce
 * @version 1.0.0
 */

namespace WCAICHATBOT\Includes\Admin;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'Wc_Cptbot_Admin_Hooks' ) ) {
	/**
	 * Admin hooks class
	 */
	class Wc_Cptbot_Admin_Hooks {

		/**
		 * Construct.
		 */
		public function __construct() {

			$function_handler = new Wc_Cptbot_Admin_Functions();

			// Initial Hooks.
			add_action( 'admin_menu', array( $function_handler, 'wc_cptbot_add_menu' ), 99 );
			add_action( 'admin_enqueue_scripts', array( $function_handler, 'wc_cptbot_admin_enqueue_scripts' ) );
			add_action( 'admin_init', array( $function_handler, 'wc_cptbot_register_settings' ) );
			add_filter( 'plugin_action_links_' . WC_CPTBOT_PLUGIN_BASENAME, array( $function_handler, 'wc_cptbot_plugin_configuration_link' ) );
			add_filter( 'plugin_row_meta', array( $function_handler, 'wc_cptbot_plugin_row_meta' ), 10, 2 );

			// Ajax Hooks.
			add_action( 'wp_ajax_wc_cptbot_create_pinecone_index', array( $function_handler, 'wc_cptbot_create_pinecone_index' ) );
			add_action( 'wp_ajax_wc_cptbot_get_users_list', array( $function_handler, 'wc_cptbot_get_users_list' ) );
			add_action( 'wp_ajax_wc_cptbot_get_filtered_chat', array( $function_handler, 'wc_cptbot_get_filtered_chat' ) );
			add_action( 'wp_ajax_wc_cptbot_get_few_days_chat', array( $function_handler, 'wc_cptbot_get_few_days_chat' ) );
			add_action( 'wp_ajax_wc_cptbot_get_today_user', array( $function_handler, 'wc_cptbot_get_today_user' ) );
			add_action( 'wp_ajax_wc_cptbot_get_user_by_id', array( $function_handler, 'wc_cptbot_get_user_by_id' ) );
			add_action( 'wp_ajax_wc_cptbot_get_most_searched_products', array( $function_handler, 'wc_cptbot_get_most_searched_products' ) );
			add_action( 'wp_ajax_wc_cptbot_get_dashboard_kpis', array( $function_handler, 'wc_cptbot_get_dashboard_kpis' ) );
			add_action( 'wp_ajax_wc_cptbot_block_user', array( $function_handler, 'wc_cptbot_block_user' ) );
			add_action( 'wp_ajax_wc_cptbot_create_custom_data', array( $function_handler, 'wc_cptbot_create_custom_data' ) );
			add_action( 'wp_ajax_wc_cptbot_save_content_openai_setting', array( $function_handler, 'wc_cptbot_save_content_openai_setting' ) );
			add_action( 'wp_ajax_wc_cptbot_speech_to_openai', array( $function_handler, 'wc_cptbot_speech_to_openai' ) );
			add_action( 'wp_ajax_wc_cptbot_create_new_post', array( $function_handler, 'wc_cptbot_create_new_post' ) );
			add_action( 'wp_ajax_wc_cptbot_save_vector_db', array( $function_handler, 'wc_cptbot_save_vector_db' ) );

			add_filter( 'media_buttons', array( $function_handler, 'wdm_add_mce_fontoptions' ) );

			// Data Training.
			add_action( 'wcaichatbot_product_training', array( $function_handler, 'wc_cptbot_product_training' ) );
			add_action( 'wc_cptbot_category_training', array( $function_handler, 'wc_cptbot_category_training' ) );
			add_action( 'wc_cptbot_customdata_training', array( $function_handler, 'wc_cptbot_customdata_training' ) );

			// Tabs content
			add_action( 'wcaichatbot_cptbot_chatgpt_bot_chatgpt-bot', array( $function_handler, 'wc_chatgpt_menu_data' ) );
			add_action( 'wcaichatbot_cptbot_chatgpt_bot_history', array( $function_handler, 'wc_chatgpt_history' ) );
			add_action( 'wcaichatbot_cptbot_chatgpt_bot_content', array( $function_handler, 'wc_chatgpt_content_writing' ) );
			add_action( 'wcaichatbot_cptbot_chatgpt_bot_training', array( $function_handler, 'wc_chatgpt_data_training' ) );
			add_action( 'wcaichatbot_cptbot_chatgpt_bot_configuration', array( $function_handler, 'wc_chatgpt_configuration' ) );
			add_action( 'wcaichatbot_cptbot_chatgpt_bot_extensions', array( $function_handler, 'wc_chatgpt_extensions' ) );
			add_action( 'wcaichatbot_cptbot_chatgpt_bot_support-and-services', array( $function_handler, 'wc_chatgpt_support_services' ) );

			add_action( 'wp_ajax_wc_cptbot_log_js_data', array( $function_handler, 'wc_cptbot_log_js_data' ) );

			$modal_type = get_option( 'cpt_bot_model_type', 'onnx' );
			if ( 'onnx' === $modal_type || 'ternlight' === $modal_type ) {
				add_action( 'admin_bar_menu', array( $function_handler, 'wc_chatgpt_in_admin_header' ), 100 );
			}

			add_action( 'wp_ajax_wc_cptbot_download_model', array( $function_handler, 'wc_cptbot_download_model' ) );

			// WordPress Posts & Pages Training & Admin Columns (Unlocked in Lite)
			add_filter( 'manage_post_posts_columns', array( $function_handler, 'wc_cptbot_add_posts_pages_sync_column' ) );
			add_filter( 'manage_page_posts_columns', array( $function_handler, 'wc_cptbot_add_posts_pages_sync_column' ) );
			add_action( 'manage_post_posts_custom_column', array( $function_handler, 'wc_cptbot_render_posts_pages_sync_column' ), 10, 2 );
			add_action( 'manage_page_posts_custom_column', array( $function_handler, 'wc_cptbot_render_posts_pages_sync_column' ), 10, 2 );
			add_filter( 'bulk_actions-edit-post', array( $function_handler, 'wc_cptbot_register_bulk_sync' ) );
			add_filter( 'bulk_actions-edit-page', array( $function_handler, 'wc_cptbot_register_bulk_sync' ) );
			add_filter( 'handle_bulk_actions-edit-post', array( $function_handler, 'wc_cptbot_handle_bulk_sync' ), 10, 3 );
			add_filter( 'handle_bulk_actions-edit-page', array( $function_handler, 'wc_cptbot_handle_bulk_sync' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( $function_handler, 'wc_cptbot_enqueue_edit_posts_scripts' ) );

			// Post Lifecycle Hooks
			add_action( 'save_post_post', array( $function_handler, 'wc_cptbot_handle_post_save' ), 10, 3 );
			add_action( 'save_post_page', array( $function_handler, 'wc_cptbot_handle_post_save' ), 10, 3 );
			add_action( 'before_delete_post', array( $function_handler, 'wc_cptbot_handle_post_delete' ) );

			// Posts & Pages AJAX Endpoints
			add_action( 'wp_ajax_wc_cptbot_get_posts_pages_data', array( $function_handler, 'wc_cptbot_get_posts_pages_data' ) );
			add_action( 'wp_ajax_wc_cptbot_upsert_post_page_vector', array( $function_handler, 'wc_cptbot_upsert_post_page_vector' ) );
			add_action( 'wp_ajax_wc_cptbot_sync_single_post', array( $function_handler, 'wc_cptbot_sync_single_post' ) );
			add_action( 'wp_ajax_wc_cptbot_sync_posts_pages', array( $function_handler, 'wc_cptbot_sync_posts_pages' ) );
			add_action( 'wp_ajax_wc_cptbot_reset_posts_pages_sync', array( $function_handler, 'wc_cptbot_reset_posts_pages_sync' ) );
		}
	}
}
