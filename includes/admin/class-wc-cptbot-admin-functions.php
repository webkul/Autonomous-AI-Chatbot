<?php

/**
 * Admin functions
 *
 * @package Chatgpt bot for WooCommerce
 * @version 1.0.0
 */

namespace WCAICHATBOT\Includes\Admin;

use WCAICHATBOT\Includes;
use WCAICHATBOT\helper;
use WCAICHATBOT\Templates\Admin;
use WCAICHATBOT\Templates\Admin\Settings;
use WCAICHATBOT\Templates\Admin\Training;

defined('ABSPATH') || exit();

if (!class_exists('Wc_Cptbot_Front_Functions')) {
	/**
	 * Admin functions class
	 */
	class Wc_Cptbot_Admin_Functions
	{

		/**
		 * Helper instance.
		 *
		 * @var object $helper
		 */
		protected $helper;

		/**
		 * OpenAI instance.
		 *
		 * @var object $openai
		 */
		protected $openai;

		/**
		 * Pinecone instance.
		 *
		 * @var object $pinecone
		 */
		protected $pinecone;

		/**
		 * Offset for pagination.
		 *
		 * @var int $offset
		 */
		protected $offset;

		/**
		 * Limit for pagination.
		 *
		 * @var int $limit
		 */
		protected $limit;


		/**
		 * Class constructor.
		 * Initializes the object with necessary dependencies.
		 */
		public function __construct()
		{
			$this->helper = new helper\Wc_Cptbot_Helper(); // Instantiate the helper class for various utility functions.
			$this->openai = new helper\Wc_Cptbot_Openai(); // Instantiate the OpenAI class for interacting with the OpenAI API.
			// Instantiate vector DB client based on configured option (pinecone or chromadb).
			$vector_db = get_option('wc_cptbot_vector_db', 'pinecone');
			if ('chromadb' === $vector_db) {
				$this->pinecone = new helper\Wc_Cptbot_Chromadb(); // ChromaDB-compatible client
			} else {
				$this->pinecone = new helper\Wc_Cptbot_Pinecone(); // Default Pinecone client
			}
			$this->offset = 0; // Set the initial offset for pagination to 0.
			$this->limit = WC_CPTBOT_PRODUCTS_PER_BATCH; // Set the limit for pagination to the configured value for products per batch.
		}


		/**
		 * Adds the menu and submenus for the Autonomous AI Chatbot plugin.
		 */
		public function wc_cptbot_add_menu()
		{
			$capability = apply_filters('wcaichatbot_dashboard_menu_capability', 'manage_options');

			add_menu_page(
				esc_html__('Autonomous AI Chatbot', 'autonomous-ai-chatbot'),
				esc_html__('Autonomous AI Chatbot', 'autonomous-ai-chatbot'),
				$capability,
				'chatgpt-bot',
				array($this, 'wc_chatgpt_menu_output'),
				'dashicons-format-chat',
				56
			);

			do_action('wcaichatbot_admin_menu_action');
		}


		/**
		 * Setting tabs to create.Support & Services
		 */
		public function wc_chatgpt_menu_output()
		{
			new Settings\Wc_Cptbot_Tab_Handler();
		}

		/**
		 * Adds custom row meta links to the plugin listing in the WordPress admin.
		 * This function is hooked into the plugin_row_meta filter and adds custom links
		 * for community support to the Autonomous AI Chatbot plugin.
		 *
		 * @param  array  $links An array of existing plugin row meta links.
		 * @param  string $file  The plugin's main file.
		 * @return array The modified array of plugin row meta links.
		 */
		public function wc_cptbot_plugin_row_meta($links, $file)
		{
			if (WC_CPTBOT_PLUGIN_BASENAME !== $file) {
				return $links;
			}

			$row_meta = array(
				'support' => '<a href="' . esc_url(apply_filters('wcaichatbot_community_support_url', 'https://webkul.uvdesk.com/')) . '" aria-label="' . esc_attr__('Visit Webkul community forums', 'autonomous-ai-chatbot') . '">' . esc_html__('Community support', 'autonomous-ai-chatbot') . '</a>',
			);

			return array_merge($links, $row_meta);
		}

		/**
		 * Displays the menu data for the ChatGPT menu page.
		 */
		public function wc_chatgpt_menu_data()
		{
			new Admin\Wc_Cptbot_Template();
		}

		/**
		 * Displays the content writing for the ChatGPT menu page.
		 */
		public function wc_chatgpt_content_writing()
		{
			echo '<div id="wc_cptbot_content_writing"></div>';
		}

		/**
		 * Displays the data training page for the ChatGPT menu.
		 */
		public function wc_chatgpt_data_training()
		{
			new Training\Wc_Cptbot_Training();
		}

		/**
		 * Displays the conversation history section in the ChatGPT menu.
		 */
		public function wc_chatgpt_history()
		{
			echo '<div id="wc_cptbot_history"></div>';
		}

		/**
		 * Displays the configuration page in the ChatGPT menu.
		 */
		public function wc_chatgpt_configuration()
		{
			new Settings\Wc_Cptbot_Configuration_Ui();
		}

		/**
		 * Displays the Extensions page in the ChatGPT menu.
		 */
		public function wc_chatgpt_extensions()
		{
			?>
			<webkul-extensions></webkul-extensions>
			<?php
		}

		/**
		 * Displays the Support & Services page in the ChatGPT menu.
		 */
		public function wc_chatgpt_support_services()
		{
			?>
			<wk-area></wk-area>
			<?php
		}

		/**
		 * Plugin action links (Dashboard, Settings, Activate Pro / Get Pro)
		 *
		 * @param  array $links Links Array.
		 * @return array $links
		 */
		public function wc_cptbot_plugin_configuration_link($links)
		{
			$extra = array(
				'<a href="' . esc_url(admin_url('admin.php?page=chatgpt-bot')) . '">' . esc_html__('Dashboard', 'autonomous-ai-chatbot') . '</a>',
				'<a href="' . esc_url(admin_url('admin.php?page=chatgpt-bot&tab=configuration')) . '">' . esc_html__('Settings', 'autonomous-ai-chatbot') . '</a>',
			);

			$activate = function_exists('wcaichatbot_pro_activate_url') ? wcaichatbot_pro_activate_url() : '';
			if ($activate) {
				$extra[] = '<a href="' . esc_url($activate) . '" style="font-weight:600;color:#00a1e0">' . esc_html__('Activate Pro', 'autonomous-ai-chatbot') . '</a>';
			} elseif (!function_exists('wcaichatbot_is_pro_active') || !wcaichatbot_is_pro_active()) {
				$upgrade_url = function_exists('wcaichatbot_get_pro_upgrade_url') ? wcaichatbot_get_pro_upgrade_url() : '#';
				$extra[] = '<a href="' . esc_url($upgrade_url) . '" target="_blank" rel="noopener" style="font-weight:600;color:#00a1e0">' . esc_html__('Get Pro', 'autonomous-ai-chatbot') . '</a>';
			}

			return array_merge($extra, $links);
		}

		/**
		 * Enqueue a built script bundle using its .asset.php manifest.
		 *
		 * @param string $handle Script handle.
		 * @param string $entry  Entry name in assets/js/.
		 * @param array  $deps   Extra dependencies.
		 * @param bool   $in_footer Whether to enqueue in footer.
		 * @return array
		 */
		public function wc_cptbot_enqueue_bundle($handle, $entry, array $deps = array(), $in_footer = false)
		{
			$asset_file = WC_CPTBOT_PLUGIN_FILE . 'assets/js/' . $entry . '.asset.php';
			$asset      = is_readable($asset_file) ? require $asset_file : array(
				'dependencies' => array('jquery', 'wp-element', 'wp-components', 'wp-i18n'),
				'version'      => WC_CPTBOT_SCRIPT_VERSION,
			);

			$all_deps = array_unique(array_merge($asset['dependencies'], $deps));

			wp_enqueue_script(
				$handle,
				WC_CPTBOT_PLUGIN_URL . 'assets/js/' . $entry . '.js',
				$all_deps,
				$asset['version'],
				$in_footer
			);

			wp_set_script_translations($handle, 'autonomous-ai-chatbot', WC_CPTBOT_PLUGIN_FILE . 'languages');

			return $asset;
		}

		/**
		 * Enqueues scripts and styles for the admin area.
		 *
		 * @param string $hook The current admin page hook.
		 */
		public function wc_cptbot_admin_enqueue_scripts($hook)
		{
			$translations = new Includes\Wc_Cptbot_Translations();

			// Enqueue WordPress core components styling.
			wp_enqueue_style('wp-components');

			// Enqueue animate.css style.
			wp_enqueue_style('wc_cptbot-animate-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/animate.css', array(), WC_CPTBOT_SCRIPT_VERSION);

			if ('post.php' === $hook) {
				wp_enqueue_script('wp-editor');

				// Enqueue admin_post.js script.
				wp_enqueue_script('wc_cptbot-post-script', WC_CPTBOT_PLUGIN_URL . 'assets/js/admin_post.js', array('jquery'), WC_CPTBOT_SCRIPT_VERSION, false);

				// Localize admin_post.js script.
				wp_localize_script(
					'wc_cptbot-post-script',
					'wc_cptbot_post',
					array(
						'admin_ajax' => admin_url('admin-ajax.php'),
						'nonce' => wp_create_nonce('ajaxnonce'),
						'processing' => esc_html__('Processing', 'autonomous-ai-chatbot') . '...',
						'complete' => esc_html__('Completed', 'autonomous-ai-chatbot'),
					)
				);

				// Enqueue admin.js for ONNX/Ternlight sync if model is ONNX or Ternlight
				$modal_type = get_option('cpt_bot_model_type', 'onnx');
				if ('onnx' === $modal_type || 'ternlight' === $modal_type) {
					$base_url = wp_upload_dir()['baseurl'];
					wp_enqueue_script('wc_cptbot_ort_js', WC_CPTBOT_PLUGIN_URL . 'assets/js/lib/ort.min.js', array('jquery'), WC_CPTBOT_SCRIPT_VERSION, true);
					wp_enqueue_script('wc_cptbot-script', WC_CPTBOT_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wc_cptbot_ort_js'), WC_CPTBOT_SCRIPT_VERSION, false);

					wp_localize_script(
						'wc_cptbot-script',
						'wc_cptbot',
						array(
							'admin_ajax' => admin_url('admin-ajax.php'),
							'nonce' => wp_create_nonce('ajaxnonce'),
							'modal_type' => $modal_type,
							'is_model_load' => true,
							'onnx_e5small_modal' => WC_CPTBOT_PLUGIN_URL . 'assets/models/e5-small.onnx',
							'onnx_modal_dir' => WC_CPTBOT_PLUGIN_URL . 'assets/models',
							'model_worker_url' => WC_CPTBOT_PLUGIN_URL . 'assets/js/modelWorker.js',
							'translation' => $translations->wc_cpt_get_all_admin_translations(),
							// Dummy values to prevent errors in admin.js
							'trained_count' => 0,
							'product_count' => 0,
							'limit' => 10,
							'chatgpt_img' => '',
							'chat_bg_img' => '',
							'ai_logo' => '',
							'site_icon_url' => '',
							'avatar_url' => '',
							'user' => wp_get_current_user(),
							'voice_assistance' => false,
							'admin_background' => false,
							'animation' => false,
							'primary_color' => '',
							'secondary_color' => '',
							'chatbot_name' => '',
							'not_found' => '',
						)
					);
				}
			}

			$submenu_page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

			if (!empty($submenu_page) && 'chatgpt-bot' === $submenu_page) {

				$tab = filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

				// Enqueue media scripts and styles.
				wp_enqueue_media();
				$base_url = wp_upload_dir()['baseurl'];

				// Enqueue animate.css style.
				wp_enqueue_style('wc_cptbot-animate-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/animate.css', array(), WC_CPTBOT_SCRIPT_VERSION);

				// Enqueue admin.css style.
				wp_enqueue_style('wc_cptbot-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/admin.css', array(), WC_CPTBOT_SCRIPT_VERSION);
				wp_enqueue_style('wcaichatbot-pro-banner-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/pro-banner.css', array(), WC_CPTBOT_SCRIPT_VERSION);

				wp_enqueue_script('wc_cptbot_ort_js', WC_CPTBOT_PLUGIN_URL . 'assets/js/lib/ort.min.js', array('jquery'), WC_CPTBOT_SCRIPT_VERSION, true);

				// Enqueue admin.js script.
				$this->wc_cptbot_enqueue_bundle('wc_cptbot-script', 'admin', array('jquery', 'wc_cptbot_ort_js'), false);

				if ('model' === $tab) {
					wp_enqueue_style('wc_cptbot-model-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/model.css', array('wp-components'), WC_CPTBOT_SCRIPT_VERSION);

					$this->wc_cptbot_enqueue_bundle('wc_cptbot-model-script', 'model', array('jquery'), false);
				}

				if ('history' === $tab) {
					if (class_exists('WooCommerce')) {
						// Enqueue select2.min.js script.
						wp_enqueue_script('wc-cptbot-select2-js', plugins_url() . '/woocommerce/assets/js/select2/select2.min.js', array('jquery'), WC_CPTBOT_SCRIPT_VERSION, true);

						// Enqueue select2.css style.
						wp_enqueue_style('wc-cptbot-select2-style', plugins_url() . '/woocommerce/assets/css/select2.css', array(), WC_CPTBOT_SCRIPT_VERSION);
					}

					// Enqueue history.css style.
					wp_enqueue_style('wc_cptbot-history-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/history.css', array('wp-components'), WC_CPTBOT_SCRIPT_VERSION);

					// Enqueue history.js script.
					$history_deps = class_exists('WooCommerce') ? array('jquery', 'wc-cptbot-select2-js') : array('jquery');
					$this->wc_cptbot_enqueue_bundle('wc_cptbot-history-script', 'history', $history_deps, false);
				}
				if (empty($tab) || 'chatgpt-bot' === $tab) {
					// Enqueue dashboard.css style.
					wp_enqueue_style('wc_cptbot-dashboard-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/dashboard.css', array('wp-components'), WC_CPTBOT_SCRIPT_VERSION);

					// Enqueue dashboard.js script.
					$this->wc_cptbot_enqueue_bundle('wc_cptbot-dashboard-script', 'dashboard', array('jquery'), false);
				}
				if ('extensions' === $tab) {
					wp_register_script(
						'wk-ext-script',
						'https://wpdemo.webkul.com/wk-extensions/client/wk.ext.js',
						array(),
						WC_CPTBOT_SCRIPT_VERSION,
						true
					);
					wp_enqueue_script('wk-ext-script');
				}
				if ('support-and-services' === $tab) {
					wp_register_script(
						'wk-service-support-script',
						'https://webkul.com/common/modules/wksas.bundle.js',
						array(),
						WC_CPTBOT_SCRIPT_VERSION,
						true
					);
					wp_enqueue_script('wk-service-support-script');
				}
				if ('content' === $tab) {
					// Enqueue content.css style.
					wp_enqueue_style('wc_cptbot-content-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/content.css', array('wp-components'), WC_CPTBOT_SCRIPT_VERSION);

					// Enqueue content.js script.
					$this->wc_cptbot_enqueue_bundle('wc_cptbot-content-script', 'content', array('jquery'), false);

					// Localize content.js script.
					wp_localize_script(
						'wc_cptbot-content-script',
						'wc_cptbot_content',
						array(
							'admin_ajax' => admin_url('admin-ajax.php'),
							'nonce' => wp_create_nonce('ajaxnonce'),
							'tabs' => array(
								'content' => array(
									'title' => esc_html__('Custom Content', 'autonomous-ai-chatbot'),
									'icon' => 'dashicons dashicons-welcome-write-blog',
								),
								'voice' => array(
									'title' => esc_html__('Voice Content', 'autonomous-ai-chatbot'),
									'icon' => 'dashicons dashicons-microphone',
								),
							),
							'count' => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10),
							'openai_settings' => $translations->wc_cptbot_openai_settings(),
							'posttypes' => array(
								'post' => esc_html__('Post', 'autonomous-ai-chatbot'),
								'page' => esc_html__('Page', 'autonomous-ai-chatbot'),
								'product' => esc_html__('Product', 'autonomous-ai-chatbot'),
							),
							'is_pro_active' => function_exists('wcaichatbot_is_pro_active') && wcaichatbot_is_pro_active(),
							'pro_upgrade_url' => (function_exists('wcaichatbot_is_pro_installed') && wcaichatbot_is_pro_installed() && current_user_can('activate_plugins')) ? wcaichatbot_get_pro_activation_url() : wcaichatbot_get_pro_upgrade_url(),
							'pro_btn_text' => (function_exists('wcaichatbot_is_pro_installed') && wcaichatbot_is_pro_installed() && current_user_can('activate_plugins')) ? esc_html__('Activate PRO Plugin', 'autonomous-ai-chatbot') : esc_html__('Upgrade to PRO', 'autonomous-ai-chatbot'),
							'pro_banner_desc' => (function_exists('wcaichatbot_is_pro_installed') && wcaichatbot_is_pro_installed() && current_user_can('activate_plugins')) ? esc_html__('Activate the PRO plugin to automatically generate complete WooCommerce product listings directly with AI.', 'autonomous-ai-chatbot') : esc_html__('Upgrade to Autonomous AI Chatbot Pro to automatically draft WooCommerce products, detailed specifications, and short descriptions directly into your store catalog.', 'autonomous-ai-chatbot'),
							'crown_icon' => WC_CPTBOT_PLUGIN_URL . 'assets/images/crown.svg',
							'content_translations' => array(
								'editorbox' => array(
									'label' => esc_html__('Topic', 'autonomous-ai-chatbot'),
									'btn_label' => esc_html__('Suggest Topic', 'autonomous-ai-chatbot'),
									'head2_label' => esc_html__('Custom Topic', 'autonomous-ai-chatbot'),
									'bplaceholder' => esc_html__('Example: Mobile phone', 'autonomous-ai-chatbot'),
								),
								'sectionbox' => array(
									'label' => esc_html__('Sections', 'autonomous-ai-chatbot'),
									'btn_label' => esc_html__('Create Sections', 'autonomous-ai-chatbot'),
								),
								'contentbox' => array(
									'label' => esc_html__('Content', 'autonomous-ai-chatbot'),
									'btn_label' => esc_html__('Create Content', 'autonomous-ai-chatbot'),
								),
								'excerptbox' => array(
									'label' => esc_html__('Excerpt', 'autonomous-ai-chatbot'),
									'btn_label' => esc_html__('Create Excerpt', 'autonomous-ai-chatbot'),
								),
								'metabox' => array(
									'label' => esc_html__('Meta Description', 'autonomous-ai-chatbot'),
									'btn_label' => esc_html__('Create Meta', 'autonomous-ai-chatbot'),
								),
								'general' => $translations->wc_cpt_get_all_admin_content_translations(),
								'admin' => $translations->wc_cpt_get_all_front_translations(),
							),
						)
					);
				}

				// Localize admin.js script.
				wp_localize_script(
					'wc_cptbot-script',
					'wc_cptbot',
					array(
						'admin_ajax' => admin_url('admin-ajax.php'),
						'nonce' => wp_create_nonce('ajaxnonce'),
						'admin_url' => admin_url(),
						'translation' => $translations->wc_cpt_get_all_admin_translations(),
						'product_count' => apply_filters('wcaichatbot_woo_product_count', 0),
						'trained_count' => !empty(get_option('wc_cptbot_scheduler_trained_count')) ? intval(get_option('wc_cptbot_scheduler_trained_count')) : 0,
						'posts_pages_trained_count' => (int) $this->helper->wc_cptbot_get_posts_pages_count()['synced'],
						'has_trained_data' => (!empty(get_option('wc_cptbot_scheduler_trained_count')) && intval(get_option('wc_cptbot_scheduler_trained_count')) > 0) || ((int) $this->helper->wc_cptbot_get_posts_pages_count()['synced'] > 0),
						'pro_upgrade_url' => wcaichatbot_get_pro_upgrade_url(),
						'is_pro_active' => wcaichatbot_is_pro_active(),
						'is_pro' => wcaichatbot_is_pro_active(),
						'crown_icon' => WC_CPTBOT_PLUGIN_URL . 'assets/images/crown.svg',
						'limit' => WC_CPTBOT_PRODUCTS_PER_BATCH,
						'no_result' => WC_CPTBOT_PLUGIN_URL . 'assets/images/no-results.png',
						'chat_circle_img' => WC_CPTBOT_PLUGIN_URL . 'assets/images/9.png',
						'chatgpt_img' => !empty(get_option('chatbot_avatar_url')) ? $base_url . get_option('chatbot_avatar_url') : WC_CPTBOT_PLUGIN_URL . 'assets/images/chatbot-logo.png',
						'chat_bg_img' => !empty(get_option('chatbot_bg_image_path')) ? $base_url . get_option('chatbot_bg_image_path') : WC_CPTBOT_PLUGIN_URL . 'assets/images/bg.webp',
						'ai_logo' => !empty(get_option('chatbot_ai_logo_path')) ? $base_url . get_option('chatbot_ai_logo_path') : WC_CPTBOT_PLUGIN_URL . 'assets/images/chatbot-logo.png',
						'site_icon_url' => get_site_icon_url(),
						'avatar_url' => get_avatar_url(get_current_user_id()),
						'user' => wp_get_current_user(),
						'voice_assistance' => !empty(get_option('cpt_allow_voice_assistant') && 'on' === get_option('cpt_allow_voice_assistant')) ? true : false,
						'admin_background' => !empty(get_option('cpt_allow_admin_background') && 'on' === get_option('cpt_allow_admin_background')) ? true : false,
						'animation' => !empty(get_option('cpt_allow_animations') && 'on' === get_option('cpt_allow_animations')) ? true : false,
						'primary_color' => !empty(get_option('chatbot_primary_color')) ? get_option('chatbot_primary_color') : '#670000',
						'secondary_color' => !empty(get_option('chatbox_secondary_color')) ? get_option('chatbox_secondary_color') : '#ffffff',
						'chatbot_name' => !empty(get_option('chatbot_name')) ? get_option('chatbot_name') : esc_html__('Autonomous AI Chatbot', 'autonomous-ai-chatbot'),
						'not_found' => WC_CPTBOT_PLUGIN_URL . 'assets/images/no-results.png',
						'is_model_load' => $this->helper->wc_chatgpt_allowed_pages_to_load_model(),
						'modal_type' => get_option('cpt_bot_model_type', 'onnx'),
						'onnx_e5small_modal' => WC_CPTBOT_PLUGIN_URL . 'assets/models/e5-small.onnx',
						'onnx_modal_dir' => WC_CPTBOT_PLUGIN_URL . 'assets/models',
						'model_worker_url' => WC_CPTBOT_PLUGIN_URL . 'assets/js/modelWorker.js',
					)
				);
			}

			$bot_status = get_option('wc_cpt_bot_status', 'disable');
			if ('enable' === $bot_status) {
				wp_enqueue_style(
					'wc-cptbot-download-modal-style',
					WC_CPTBOT_PLUGIN_URL . 'assets/css/custom_notice.css',
					array(),
					WC_CPTBOT_SCRIPT_VERSION
				);

				$this->wc_cptbot_enqueue_bundle(
					'wc-cptbot-download-modal',
					'download_modal',
					array(),
					false
				);

				$model_path = WC_CPTBOT_PLUGIN_FILE . 'assets/models/e5-small.onnx';

				$download_status = get_option('wc_cptbot_model_downloaded', '');

				$is_download_completed = (
					'downloaded' === $download_status
					&& file_exists($model_path)
				) ? 'yes' : 'no';

				wp_localize_script(
					'wc-cptbot-download-modal',
					'wc_cptbot_download_modal',
					array(
						'ajax_url' => admin_url('admin-ajax.php'),
						'nonce' => wp_create_nonce('wc_cptbot_nonce'),
						'is_download_completed' => $is_download_completed,
						'start_download' => ('no' === $is_download_completed),
						'translation' => array(
							'downloading' => esc_html__('Model is downloading...', 'autonomous-ai-chatbot'),
							'download_failed' => esc_html__('Download failed. Please try again.', 'autonomous-ai-chatbot'),
							'already_done' => esc_html__('Model downloaded successfully.', 'autonomous-ai-chatbot'),
							'waiting' => esc_html__('Please wait, We are preparing to download the ai model.', 'autonomous-ai-chatbot'),
						),
					)
				);
			}
		}

		/**
		 * Creates or retrieves the Pinecone index for ChatGPT.
		 *
		 * @return void
		 */
		public function wc_cptbot_create_pinecone_index()
		{
			try {
				if ('pinecone' !== get_option('wc_cptbot_vector_db', 'pinecone')) {
					wp_send_json_error(esc_html__('Pinecone index operations are only available when Pinecone is selected as the Vector DB.', 'autonomous-ai-chatbot'));
					return;
				}
				$list = $this->pinecone->indexlisting();
				$listing = json_decode($list['data']);
				if (200 === $list['code']) {
					if (!empty($listing) && !empty($listing->indexes)) {
						$description = $this->pinecone->describe_listing();
						$description = is_array($description) ? json_decode($description['response']) : json_decode($description);
					} else {
						$this->pinecone->create_index();
						$description = $this->pinecone->describe_listing();
						$description = is_array($description) ? json_decode($description['response']) : json_decode($description);
					}
					if (!empty($description)) {
						$host = $description->host;
						update_option('wc_cptbot_pinecone_index_host', $host);
					}
					$data = $description;
					wp_send_json_success($data);
				} else {
					$data = $list['data'];
					wp_send_json_error($data);
				}
			} catch (\Throwable $th) {
				$data = $th;
			}
		}

		/**
		 * Register settings for WC CPTBot plugin.
		 *
		 * @return void
		 */
		public function wc_cptbot_register_settings()
		{

			$group = 'wc-cptbot-settings-group';

			/* ---------------- Pinecone Settings ---------------- */
			register_setting(
				$group,
				'wc_cptbot_pinecone_key',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_pinecone_env',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_pinecone_index_host',
				array(
					'sanitize_callback' => array( $this, 'wc_cptbot_sanitize_pinecone_host' ),
				)
			);

			register_setting(
				$group,
				'wc_cptbot_pinecone_host',
				array(
					'sanitize_callback' => array( $this, 'wc_cptbot_sanitize_pinecone_host' ),
				)
			);

			register_setting(
				$group,
				'wc_cptbot_pinecone_indexname',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			/* ---------------- Vector DB & Chroma ---------------- */
			register_setting(
				$group,
				'wc_cptbot_vector_db',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_chroma_base_url',
				array(
					'sanitize_callback' => 'esc_url_raw',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_chroma_collection',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_chroma_api_key',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			/* ---------------- OpenAI Compatible ---------------- */
			register_setting(
				$group,
				'wc_cptbot_api_key',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_api_base_url',
				array(
					'sanitize_callback' => 'esc_url_raw',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_model_name',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_typesafe_api_key',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_typesafe_model',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_typesafe_confidence_threshold',
				array(
					'sanitize_callback' => 'floatval',
				)
			);

			/* ---------------- Stripe MCP ---------------- */
			register_setting(
				$group,
				'wc_cptbot_stripe_enabled',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_stripe_publishable_key',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_stripe_secret_key',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			/* ---------------- Chatbox Settings ---------------- */
			register_setting(
				$group,
				'cpt_allow_voice_assistant',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'cpt_allow_animations',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'chatbot_primary_color',
				array(
					'sanitize_callback' => 'sanitize_hex_color',
				)
			);

			register_setting(
				$group,
				'chatbox_secondary_color',
				array(
					'sanitize_callback' => 'sanitize_hex_color',
				)
			);

			register_setting(
				$group,
				'chatbot_name',
				array(
					'sanitize_callback' => array($this, 'wc_cptbot_validate_name'),
				)
			);

			register_setting(
				$group,
				'chatbot_avatar_url',
				array(
					'sanitize_callback' => 'esc_url_raw',
				)
			);

			register_setting(
				$group,
				'chatbot_bg_image_path',
				array(
					'sanitize_callback' => 'esc_url_raw',
				)
			);

			register_setting(
				$group,
				'chatbot_ai_logo_path',
				array(
					'sanitize_callback' => 'esc_url_raw',
				)
			);

			register_setting(
				$group,
				'cpt_allow_admin_background',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'cpt_bot_position',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'cpt_bot_model_type',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_gemini_api_key',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_gemini_embedding_model',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_openai_embedding_api_key',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cptbot_openai_embedding_model',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'cpt_enable_guest_history',
				array(
					'sanitize_callback' => 'sanitize_text_field',
				)
			);

			register_setting(
				$group,
				'wc_cpt_bot_status',
				array(
					'sanitize_callback' => 'sanitize_text_field',
					'default' => 'disable',
				)
			);

			/* ---------------- Custom Extension Hook ---------------- */
			do_action('wcaichatbot_setting_action', $group);
		}

		/**
		 * Sanitize Pinecone host.
		 *
		 * @param string $host The host URL or domain.
		 * @return string Sanitized host.
		 */
		public function wc_cptbot_sanitize_pinecone_host($host)
		{
			if (empty($host)) {
				return '';
			}

			$host = sanitize_text_field($host);
			$host = preg_replace('#^https?://#i', '', $host);

			return trim(rtrim($host, '/'));
		}

		/**
		 * Validate and sanitize the chat name.
		 *
		 * @param string $value The value to be validated and sanitized.
		 *
		 * @return string The validated and sanitized value.
		 */
		public function wc_cptbot_validate_name($value)
		{
			$value = esc_html($value);

			// Check if the value is empty.
			if (empty($value)) {
				// If the value is empty, add a settings error.
				add_settings_error('chatbot_name', 'chatbot_name', esc_html__('Chat Name is Empty', 'autonomous-ai-chatbot'));
			}

			// Return the sanitized value.
			return $value;
		}


		/**
		 * Adds MCE font options for the editor.
		 *
		 * @param  string $editor_id The ID of the editor.
		 * @return string The modified editor ID.
		 */
		public function wdm_add_mce_fontoptions($editor_id)
		{
			$id = get_the_ID();
			$screen = get_current_screen();
			if (!$screen) {
				return $editor_id;
			}

			$supported_types = apply_filters('wcaichatbot_editor_description_post_types', array('post', 'page'));

			if (!in_array($screen->post_type, $supported_types, true)) {
				return $editor_id;
			}

			switch ($editor_id) {
				case 'content':
					echo '<button class="button chatgpt_desc" data-type="content" data-length="500" data-id="' . esc_attr($id) . '">' . esc_html__('Generate Description With AI', 'autonomous-ai-chatbot') . '</button>';
					break;
			}
			return $editor_id;
		}

		/**
		 * Retrieves a list of users using WC Cptbot helper.
		 */
		public function wc_cptbot_get_users_list()
		{
			// Verify the nonce for security.
			if (!empty($_POST['security']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'ajaxnonce')) {
				// Get users using WC Cptbot helper.
				$users = $this->helper->wc_cptbot_get_user(wcaichatbot_clean($_POST));

				// Send JSON response with the users list.
				wp_send_json($users);
			}
		}

		/**
		 * Retrieves filtered chat data based on the provided data.
		 */
		public function wc_cptbot_get_filtered_chat()
		{
			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				// Get chat data using WC Cptbot helper.
				$chat = $this->helper->wc_cptbot_get_user_chat($post);

				// Get the block status of the user.
				$user_id_raw = $post['cptbot_users_list'];
				if ('guest' === $user_id_raw) {
					$block = 'unblock'; // Guests cannot be blocked by ID 0 currently
				} else {
					$block = get_user_meta((int) $user_id_raw, 'wc_cptbot_block_user', true);
					$block = (empty($block) || 'unblock' === $block) ? 'unblock' : 'block';
				}

				// Prepare data for JSON response.
				$data = array(
					'chat' => $chat,
					'block' => $block,
				);

				// Send JSON response with the filtered chat data.
				wp_send_json($data);
			}
		}


		/**
		 * Retrieves the chat data for the last few days.
		 */
		public function wc_cptbot_get_few_days_chat()
		{

			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				$last_days = array();
				$lastdates = array();

				// Get chat data using WC Cptbot helper.
				$chat = $this->helper->wc_cptbot_get_chat_data($post);
				// Calculate the count of chat entries for each day.
				foreach ($chat as $value) {
					$last_days[$value['entry_date']] = (int) $value['entry_count'];
				}

				$date = new \DateTime(); // Get current date.
				$days = (int) $post['days'] + 1;
				// Generate an array of dates for the last 7 days.
				for ($i = 0; $i < $days; $i++) {
					$lastdates[$date->format('Y-m-d')] = 0;
					$date->modify('-1 day'); // Subtract one day from the current date
				}

				// Merge the arrays of last 7 days and chat entry counts.
				$arr = array_merge($lastdates, $last_days);

				// Send JSON response with the reversed array values.
				wp_send_json(array_values(array_reverse($arr)));
			}
		}


		/**
		 * Retrieve and send today's visited user data via AJAX.
		 *
		 * This function retrieves data about users who have visited today and sends it as a JSON response.
		 *
		 * @param array $post The POST data containing a nonce for security.
		 */
		public function wc_cptbot_get_today_user($post)
		{

			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				// Get today's chat data for visited users using WC Cptbot helper.
				$todays_chat = $this->helper->wc_cptbot_get_visited_user($post);
				// Get additional user data for each chat entry.
				$todays_chat = array_map(
					function ($data) {
						if (0 === (int) $data['user_id']) {
							$data['user'] = (object) array(
								'ID' => 'guest',
								'data' => (object) array(
									'user_nicename' => esc_html__('Guest', 'autonomous-ai-chatbot'),
									'user_email' => esc_html__('Guest', 'autonomous-ai-chatbot'),
								),
							);
						} else {
							$data['user'] = get_user_by('ID', (int) $data['user_id']);
						}
						return $data;
					},
					$todays_chat
				);

				// Send JSON response with the list of today's visited users.
				wp_send_json($todays_chat);
			}
		}


		/**
		 * Retrieves user data by ID.
		 *
		 * @param array $post The POST data containing a nonce for security.
		 */
		public function wc_cptbot_get_user_by_id($post)
		{
			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				// Retrieve user data by ID.
				$user_id_raw = $post['user_id'];
				if ('guest' === $user_id_raw || 0 === (int) $user_id_raw) {
					$user = (object) array(
						'ID' => 'guest',
						'data' => (object) array(
							'user_nicename' => esc_html__('Guest', 'autonomous-ai-chatbot'),
							'user_email' => esc_html__('Guest', 'autonomous-ai-chatbot'),
						),
					);
					wp_send_json($user);
				} else {
					wp_send_json(get_user_by('ID', (int) $user_id_raw));
				}
			}
		}



		/**
		 * Retrieves the most searched products.
		 */
		public function wc_cptbot_get_most_searched_products()
		{

			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {

				// Check if PRO addon is active.
				if (!function_exists('wcaichatbot_is_pro_active') || !wcaichatbot_is_pro_active()) {
					$is_installed = function_exists('wcaichatbot_is_pro_installed') && wcaichatbot_is_pro_installed();
					$can_activate = current_user_can('activate_plugins');

					if ($is_installed && $can_activate) {
						$btn_text = __('Activate PRO Plugin', 'autonomous-ai-chatbot');
						$banner_text = __("Activate the PRO plugin to view shopping insights, analytics, and your store's top 5 most searched products.", 'autonomous-ai-chatbot');
						$action_url = function_exists('wcaichatbot_get_pro_activation_url') ? wcaichatbot_get_pro_activation_url() : admin_url('plugins.php');
					} else {
						$btn_text = __('Upgrade to PRO', 'autonomous-ai-chatbot');
						$banner_text = __("Upgrade to the PRO addon to view shopping insights, analytics, and your store's top 5 most searched products.", 'autonomous-ai-chatbot');
						$action_url = function_exists('wcaichatbot_get_pro_upgrade_url') ? wcaichatbot_get_pro_upgrade_url() : '#';
					}

					wp_send_json(
						array(
							'is_pro' => false,
							'is_installed' => $is_installed,
							'btn_text' => $btn_text,
							'title' => __('Top Searched Products', 'autonomous-ai-chatbot'),
							'banner' => $banner_text,
							'upgrade_url' => $action_url,
							'label' => array(),
							'series' => array(),
						)
					);
					return;
				}

				$pro_data = apply_filters('wcaichatbot_top_searched_products_data', null);
				if (null !== $pro_data) {
					wp_send_json($pro_data);
					return;
				}

				wp_send_json(
					array(
						'is_pro' => false,
						'label' => array(),
						'series' => array(),
					)
				);
			}
		}


		/**
		 * Retrieves aggregated KPI statistics for the dashboard.
		 */
		public function wc_cptbot_get_dashboard_kpis()
		{
			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$days = !empty($_POST['days']) ? max(1, intval($_POST['days'])) : 7;

				// Separate cache keys for PRO active vs Lite so deactivating PRO never serves PRO cache.
				$is_pro = function_exists('wcaichatbot_is_pro_active') && wcaichatbot_is_pro_active();
				$pro_suffix = $is_pro ? '_pro' : '_lite';
				$cache_key = 'wcaichatbot_kpis_v4_' . $days . $pro_suffix;
				$kpis = get_transient($cache_key);

				if (false === $kpis) {
					$kpis = $this->helper->wc_cptbot_get_kpi_metrics($days);
					set_transient($cache_key, $kpis, 5 * MINUTE_IN_SECONDS);
				}

				wp_send_json_success(array_values($kpis));
			}

			wp_send_json_error(array('message' => esc_html__('Invalid security token', 'autonomous-ai-chatbot')));
		}


		/**
		 * Blocks or unblocks a user.
		 */
		public function wc_cptbot_block_user()
		{

			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				$user_id = $post['user_id'];

				// Get the current block status of the user.
				$block = get_user_meta((int) $user_id, 'wc_cptbot_block_user', true);
				$block = empty($block) ? 'unblock' : $block;

				$data = array();
				if ('unblock' === $block) {
					// Block the user.
					$update = update_user_meta((int) $user_id, 'wc_cptbot_block_user', 'block');
					$data = array(
						'msg' => 'Blocked',
						'status' => $update,
					);
				} else {
					// Unblock the user.
					$update = update_user_meta((int) $user_id, 'wc_cptbot_block_user', 'unblock');
					$data = array(
						'msg' => 'Unblock',
						'status' => $update,
					);
				}

				// Send JSON response with the block status.
				wp_send_json($data);
			}
		}

		/**
		 * Initiates the product training process.
		 */
		public function wc_cptbot_product_training()
		{
			if (!function_exists('wcaichatbot_is_pro_active') || !wcaichatbot_is_pro_active()) {
				echo '<div class="wc-pos-container" style="padding-left: 23px;padding-top: 4px;">';
				echo '<h2>' . esc_html__('Product Training', 'autonomous-ai-chatbot') . ' ' . wp_kses_post( wcaichatbot_get_crown_icon_html() ) . '</h2>';

				$ghost_table = '<div style="background:#fff; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden; padding: 12px 16px;">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid #f1f5f9;">
						<ul class="subsubsub" style="margin:0;">
							<li><a href="#" class="current">' . esc_html__('All', 'autonomous-ai-chatbot') . ' <span class="count">(124)</span></a> |</li>
							<li><a href="#">' . esc_html__('Synced', 'autonomous-ai-chatbot') . ' <span class="count">(112)</span></a> |</li>
							<li><a href="#">' . esc_html__('Unsynced', 'autonomous-ai-chatbot') . ' <span class="count">(12)</span></a></li>
						</ul>
						<div style="display:flex; gap:8px;">
							<input type="search" placeholder="' . esc_attr__('Search products…', 'autonomous-ai-chatbot') . '" style="height:30px; font-size:12px;" disabled />
							<button class="button" disabled>' . esc_html__('Search', 'autonomous-ai-chatbot') . '</button>
						</div>
					</div>
					<table class="wcaichatbot-ghost-table">
						<thead>
							<tr>
								<th>' . esc_html__('Product ID', 'autonomous-ai-chatbot') . '</th>
								<th>' . esc_html__('Product Name', 'autonomous-ai-chatbot') . '</th>
								<th>' . esc_html__('Type', 'autonomous-ai-chatbot') . '</th>
								<th>' . esc_html__('SKU', 'autonomous-ai-chatbot') . '</th>
								<th>' . esc_html__('Category', 'autonomous-ai-chatbot') . '</th>
								<th>' . esc_html__('Vector Sync Status', 'autonomous-ai-chatbot') . '</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>#1042</td>
								<td><strong>Wireless Noise-Cancelling Headphones</strong></td>
								<td>Variable</td>
								<td>WNCH-01</td>
								<td>Audio & Electronics</td>
								<td><span class="wcaichatbot-ghost-pill">' . esc_html__('Synced', 'autonomous-ai-chatbot') . '</span></td>
							</tr>
							<tr>
								<td>#1043</td>
								<td><strong>Ergonomic Office Mesh Chair</strong></td>
								<td>Simple</td>
								<td>EOMC-88</td>
								<td>Office Furniture</td>
								<td><span class="wcaichatbot-ghost-pill">' . esc_html__('Synced', 'autonomous-ai-chatbot') . '</span></td>
							</tr>
							<tr>
								<td>#1044</td>
								<td><strong>Smart Fitness Tracker Pro</strong></td>
								<td>Variable</td>
								<td>SFTP-09</td>
								<td>Wearables</td>
								<td><span class="wcaichatbot-ghost-pill">' . esc_html__('Synced', 'autonomous-ai-chatbot') . '</span></td>
							</tr>
							<tr>
								<td>#1045</td>
								<td><strong>Leather Minimalist Wallet</strong></td>
								<td>Simple</td>
								<td>LMW-22</td>
								<td>Accessories</td>
								<td><span class="wcaichatbot-ghost-pill">' . esc_html__('Synced', 'autonomous-ai-chatbot') . '</span></td>
							</tr>
							<tr>
								<td>#1046</td>
								<td><strong>Stainless Steel Thermal Flask</strong></td>
								<td>Simple</td>
								<td>SSTF-50</td>
								<td>Kitchen & Dining</td>
								<td><span class="wcaichatbot-ghost-pill">' . esc_html__('Synced', 'autonomous-ai-chatbot') . '</span></td>
							</tr>
							<tr>
								<td>#1047</td>
								<td><strong>Mechanical Gaming Keyboard RGB</strong></td>
								<td>Variable</td>
								<td>MGK-RGB</td>
								<td>Gaming</td>
								<td><span class="wcaichatbot-ghost-pill">' . esc_html__('Synced', 'autonomous-ai-chatbot') . '</span></td>
							</tr>
							<tr>
								<td>#1048</td>
								<td><strong>Organic Cotton Classic Hoodie</strong></td>
								<td>Variable</td>
								<td>OCCH-04</td>
								<td>Apparel</td>
								<td><span class="wcaichatbot-ghost-pill">' . esc_html__('Synced', 'autonomous-ai-chatbot') . '</span></td>
							</tr>
							<tr>
								<td>#1049</td>
								<td><strong>Ultra-Wide Curved Gaming Monitor</strong></td>
								<td>Simple</td>
								<td>UWCM-34</td>
								<td>Displays</td>
								<td><span class="wcaichatbot-ghost-pill">' . esc_html__('Synced', 'autonomous-ai-chatbot') . '</span></td>
							</tr>
						</tbody>
					</table>
					<div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; padding-top:10px; border-top:1px solid #f1f5f9; color:#64748b; font-size:12px;">
						<span>124 items</span>
						<span>« ‹ 1 of 13 › »</span>
					</div>
				</div>';

				if (function_exists('wcaichatbot_get_pro_lock_html')) {
					echo wcaichatbot_get_pro_lock_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html__('WooCommerce Product Training', 'autonomous-ai-chatbot'),
						esc_html__('Train and synchronize your WooCommerce products (Single, Variable, Grouped), stock status, and attributes with Pinecone, ChromaDB, or ONNX vector embeddings.', 'autonomous-ai-chatbot'),
						$ghost_table,
						'',
						null,
						false,
						true
					);
				} else {
					echo wcaichatbot_get_pro_banner_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						esc_html__('WooCommerce Product Training', 'autonomous-ai-chatbot'),
						esc_html__('Upgrade to the PRO addon to automatically vectorize and synchronize your WooCommerce products (Single, Variable, Grouped), stock status, categories, and attributes with Pinecone, ChromaDB, or ONNX.', 'autonomous-ai-chatbot')
					);
				}
				echo '</div>';
				return;
			}

			do_action('wcaichatbot_render_pro_product_training');
		}

		/**
		 * Create custom data using OpenAI language model based on user input.
		 */
		public function wc_cptbot_create_custom_data()
		{
			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				// Convert the JSON string 'formentry' into an associative array $formdata.
				$formdata = (array) json_decode(stripcslashes($post['formentry']));

				// Use the configured global model or 'gpt-3.5-turbo' as the default model.
				$model = get_option('wc_cptbot_model_name', 'gpt-3.5-turbo');

				// Determine the count for how many outputs to generate or set it to 1 by default.
				$selected_count = !empty($post['select']) ? (array) json_decode(stripcslashes($post['select'])) : 1;
				$count = 1;
				if (!empty($selected_count)) {
					$count = is_array($selected_count) ? (isset($selected_count[$post['slug']]) ? $selected_count[$post['slug']] : 1) : $selected_count;
				}

				// Set the initial prompt based on the value of 'slug' in the POST data.
				$prompt_key = 'open_ai_' . $post['slug'];
				if (!empty($formdata[$prompt_key])) {
					$prompt = $formdata[$prompt_key];
				} else {
					switch ($post['slug']) {
						case 'topic':
							$prompt = esc_html__('Suggest', 'autonomous-ai-chatbot') . '{count}' . esc_html__('title for an article about', 'autonomous-ai-chatbot') . '{topic}';
							break;
						case 'section':
							$prompt = esc_html__('Write', 'autonomous-ai-chatbot') . '{count}' . esc_html__('consecutive headings for an article about', 'autonomous-ai-chatbot') . '{topic}';
							break;
						case 'content':
							$prompt = esc_html__('Write a comprehensive article about', 'autonomous-ai-chatbot') . ' {topic},' . esc_html__('covering the following subtopics', 'autonomous-ai-chatbot') . '{sections}.' . esc_html__('Each subtopic should have at least', 'autonomous-ai-chatbot') . '{count}' . esc_html__('paragraphs.', 'autonomous-ai-chatbot') . esc_html__('Use a cohesive structure to ensure smooth transitions between ideas. Include relevant statistics, examples, and quotes to support your arguments and engage the reader.', 'autonomous-ai-chatbot');
							break;
						case 'excerpt':
							$prompt = esc_html__('Generate an excerpt for', 'autonomous-ai-chatbot') . ' {topic}. ' . esc_html__('Max: 55 words.', 'autonomous-ai-chatbot');
							break;
						case 'meta':
							$prompt = esc_html__('Write a meta description about', 'autonomous-ai-chatbot') . '{topic}.' . esc_html__('Max: 155 characters.', 'autonomous-ai-chatbot');
							break;
					}
				}
				// Replace placeholders in the prompt with actual values (e.g., {count}, {topic}, {sections}).
				$section = '';
				$topic = stripcslashes($post['data']);

				if (!empty($topic)) {
					if (is_object(json_decode($topic))) {
						$topic = (array) json_decode($topic);
					}
					$topic = is_array($topic) ? $topic[$post['slug']] : $topic;
					if ('content' === $post['slug']) {
						$topic = !is_object($topic) ? json_decode($topic) : $topic;
						$topic = isset($topic->content) ? (isset($topic->content->topic) ? $topic->content->topic : $topic->topic) : $topic->topic;
						$section = (string) $post['section'];
						if (strpos($prompt, '{sections}') !== false) {
							$prompt = str_replace('{sections}', $section, $prompt);
						}
					}
				}
				if (strpos($prompt, '{count}') !== false) {
					$prompt = str_replace('{count}', $count, $prompt);
				}

				if (strpos($prompt, '{topic}') !== false) {
					$prompt = str_replace('{topic}', $topic, $prompt);
				}
				// Get data from the OpenAI language model using the provided prompt and form data.
				$chat_data = $this->helper->wc_cptbot_getdata_from_model($model, $prompt, $formdata);

				// Send the JSON response with the chat data back to the client.
				wp_send_json($chat_data);
			}
		}


		/**
		 * Save OpenAI content settings to WordPress options.
		 */
		public function wc_cptbot_save_content_openai_setting()
		{
			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				// Remove unnecessary data from the $post array.
				unset($post['action']);
				unset($post['nonce']);
				// Update the WordPress option 'wc_cptbot_openai_content_setting' with the sanitized $post data.
				$setting = update_option('wc_cptbot_openai_content_setting', $post);

				// Prepare the response data to be sent back to the client.
				$data = array(
					'data' => $post,       // The sanitized content settings.
					'status' => $setting,    // The status of the option update (true or false).
				);

				// Send the JSON response with the saved data and status back to the client.
				wp_send_json($data);
			}
		}


		/**
		 * Convert speech input to data using the OpenAI language model.
		 */
		public function wc_cptbot_speech_to_openai()
		{
			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				// Extract the 'prompt' from the POST data.
				$prompt = $post['prompt'];

				// If the prompt is not empty, proceed with the OpenAI processing.
				if (!empty($prompt)) {
					// Get data from the OpenAI language model using the provided prompt.
					$chat_data = $this->helper->wc_cptbot_get_speech_data_from_model($prompt);

					// Send the JSON response with the chat data back to the client.
					wp_send_json($chat_data);
				}
			}
		}


		/**
		 * Create a new post using the data provided in the POST request.
		 */
		public function wc_cptbot_create_new_post()
		{
			// Verify the nonce for security.
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ajaxnonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				// Decode the JSON data sent in the 'data' field.
				$data = json_decode(stripslashes($post['data']), true);

				// Get the current user ID.
				$user_id = get_current_user_id();

				// Extract relevant data from the decoded JSON.
				$post_content = !empty($data['content']) ? $data['content'] : '';
				$post_content_filtered = !empty($data['section']) ? $data['section'] : '';
				$post_title = !empty($data['topic']) ? $data['topic'] : '';
				$post_excerpt = !empty($data['excerpt']) ? $data['excerpt'] : '';
				$posttype = !empty($post['posttype']) ? $post['posttype'] : 'post';

				// Ensure post type creation is permitted (PRO addon can hook additional post types).
				$allowed_post_types = apply_filters('wcaichatbot_content_post_types', array('post', 'page'));
				if (!in_array($posttype, $allowed_post_types, true)) {
					wp_send_json(
						array(
							'status' => 'error',
							'is_pro' => false,
							'message' => esc_html__('Creating this post type requires the Autonomous AI Chatbot Pro addon.', 'autonomous-ai-chatbot'),
						)
					);
					return;
				}

				// Prepare the post data array for inserting a new post.
				$post_data = array(
					'post_author' => $user_id,
					'post_content' => $post_content,
					'post_content_filtered' => $post_content_filtered,
					'post_title' => $post_title,
					'post_excerpt' => $post_excerpt,
					'post_status' => 'draft',
					'post_type' => $posttype,
					'post_parent' => 0,
					'post_date' => gmdate('Y-m-d'),
					'post_date_gmt' => gmdate('Y-m-d'),
				);

				// Check if a post with the same title and post type already exists.
				$topic_status = $this->helper->wc_cptbot_check_topic_exist($post_title, $posttype);

				// If the post with the same title does not exist, insert the new post.
				if (empty($topic_status)) {
					wp_insert_post($post_data, false, true);
					$data = array('status' => 'success');
				} else {
					$data = array('status' => 'error');
				}

				// Send the JSON response with the status back to the client.
				wp_send_json($data);
			}
		}

		/**
		 * Create a custom node (item) in the admin bar.
		 *
		 * @param object $wp_admin_bar The WP_Admin_Bar instance, passed by reference.
		 *
		 * @return void
		 */
		public function wc_chatgpt_in_admin_header($wp_admin_bar)
		{
			$model_type = get_option('cpt_bot_model_type', 'onnx');
			$model_label = '';
			switch ($model_type) {
				case 'onnx':
					$model_label = esc_html__('ONNX Model', 'autonomous-ai-chatbot');
					break;
				case 'ternlight':
					$model_label = esc_html__('Ternlight', 'autonomous-ai-chatbot');
					break;
				case 'gemini':
					$model_label = esc_html__('Gemini', 'autonomous-ai-chatbot');
					break;
				case 'openai':
					$model_label = esc_html__('OpenAI', 'autonomous-ai-chatbot');
					break;
				default:
					$model_label = ucfirst($model_type);
			}

			$args = array(
				'id' => 'wc_chatgpt_chatbot_bar',
				'title' => '<span class="wc_chatgpt_chatbot_bar" style="color:#fff;font-weight:bold;">⚡ ' . esc_html($model_label) . '</span>',
				'meta' => array(
					'html' => false,
				),
			);
			$wp_admin_bar->add_node($args);
		}



		/**
		 * Log JS data to PHP error log.
		 *
		 * @return void
		 */
		public function wc_cptbot_log_js_data()
		{
			if (check_ajax_referer('ajaxnonce', 'nonce', false)) {
				wp_send_json_success();
			}
			wp_send_json_error();
		}

		/**
		 * Save the selected vector DB and handle cleanup of previous DB data.
		 */
		public function wc_cptbot_save_vector_db()
		{
			if (!empty($_POST['nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wc_cptbot_vector_db_nonce')) {
				$post = !empty($_POST) ? wcaichatbot_clean($_POST) : array();

				$new_db = isset($post['vector_db']) ? sanitize_text_field($post['vector_db']) : '';
				$old_db = get_option('wc_cptbot_vector_db', 'pinecone');

				if (!empty($new_db) && $new_db !== $old_db) {
					// If switching AWAY from Pinecone, delete Pinecone data
					if ('pinecone' === $old_db) {
						// Ensure we have the Pinecone helper loaded
						if (!class_exists('\WCAICHATBOT\Helper\Wc_Cptbot_Pinecone')) {
							include_once WC_CPTBOT_PLUGIN_FILE . 'helper/class-wc-cptbot-pinecone.php';
						}

						// We can use $this->pinecone if it's already Pinecone, or create new instance to be safe
						$pinecone_helper = ($this->pinecone instanceof \WCAICHATBOT\Helper\Wc_Cptbot_Pinecone) ? $this->pinecone : new \WCAICHATBOT\Helper\Wc_Cptbot_Pinecone();

						if (method_exists($pinecone_helper, 'delete_all_vectors')) {
							$result = $pinecone_helper->delete_all_vectors();
						}
					}

					// Update the option
					update_option('wc_cptbot_vector_db', $new_db);

					// Reset training progress since DB changed
					delete_option('wc_cptbot_scheduler_offset');
					delete_option('wc_cptbot_scheduler_limit');
					delete_option('wc_cptbot_scheduler_trained_count');

					wp_send_json_success(array('message' => 'Vector DB updated and previous data cleaned up.'));
				} else {
					// Even if same DB, update option just in case
					update_option('wc_cptbot_vector_db', $new_db);
					wp_send_json_success(array('message' => 'Vector DB updated.'));
				}
			} else {
				wp_send_json_error(array('message' => 'Nonce verification failed'));
			}
		}

		/**
		 * Download onxx model.
		 */
		public function wc_cptbot_download_model()
		{

			$response = array(
				'success' => false,
				'message' => '',
				'data' => array(),
			);

			try {

				$nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

				if (!wp_verify_nonce($nonce, 'wc_cptbot_nonce')) {
					throw new \Exception('Security check failed', 'autonomous-ai-chatbot');
				}

				$result = $this->helper->wc_cptbot_download_model();

				if (!isset($result['success']) || !$result['success']) {
					$error_notice = !empty($result['message']) ? $result['message'] : esc_html__('Unable to download the onxx model, Please refresh the page to try again.', 'autonomous-ai-chatbot');

					throw new \Exception($error_notice);

				}

				$success_notice = !empty($result['message']) ? $result['message'] : esc_html__('Onxx model downloaded successfully.', 'autonomous-ai-chatbot');

				$response['success'] = true;
				$response['message'] = $success_notice;
				$response['data'] = !empty($result['data']) ? $result['data'] : array();

			} catch (\Exception $ex) {
				$response['message'] = $ex->getMessage();
			}

			wp_send_json($response);
		}

		/**
		 * Add Chatbot Sync column to Posts and Pages list tables.
		 *
		 * @param array $columns Existing columns.
		 * @return array
		 */
		public function wc_cptbot_add_posts_pages_sync_column($columns)
		{
			$new_columns = array();
			$inserted = false;

			foreach ($columns as $key => $title) {
				if ('date' === $key && !$inserted) {
					$new_columns['cptbot_sync'] = esc_html__('AI Chatbot Sync', 'autonomous-ai-chatbot');
					$inserted = true;
				}
				$new_columns[$key] = $title;
			}

			if (!$inserted) {
				$new_columns['cptbot_sync'] = esc_html__('AI Chatbot Sync', 'autonomous-ai-chatbot');
			}

			return $new_columns;
		}

		/**
		 * Render content for Chatbot Sync column on Posts and Pages.
		 *
		 * @param string $column  Column name.
		 * @param int    $post_id Post ID.
		 * @return void
		 */
		public function wc_cptbot_render_posts_pages_sync_column($column, $post_id)
		{
			if ('cptbot_sync' !== $column) {
				return;
			}

			$post = get_post($post_id);
			if (!$post || !in_array($post->post_type, array('post', 'page'), true)) {
				return;
			}

			$post_meta   = get_post_meta($post_id, 'cptbot_post_data', true);
			$is_synced   = !empty($post_meta);
			$meta_arr    = $is_synced ? maybe_unserialize($post_meta) : array();
			$last_synced = !empty($meta_arr['trained_at']) ? $meta_arr['trained_at'] : '';

			$is_outdated = false;
			if ($is_synced && !empty($last_synced)) {
				$mod_time  = strtotime($post->post_modified);
				$sync_time = strtotime($last_synced);
				if ($mod_time > ($sync_time + 60)) {
					$is_outdated = true;
				}
			}

			echo '<div class="cptbot-sync-cell" id="cptbot-sync-cell-' . esc_attr($post_id) . '" data-post-id="' . esc_attr($post_id) . '" data-post-type="' . esc_attr($post->post_type) . '">';

			if ($is_synced && !$is_outdated) {
				/* translators: %s: last synced date/time */
				$title_tooltip = $last_synced ? sprintf(esc_attr__('Synced on %s', 'autonomous-ai-chatbot'), esc_attr($last_synced)) : esc_attr__('Synced', 'autonomous-ai-chatbot');
				echo '<span class="cptbot-pill cptbot-pill-synced" title="' . esc_attr($title_tooltip) . '" style="display:inline-flex; align-items:center; gap:4px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">';
				echo '✓ ' . esc_html__('Synced', 'autonomous-ai-chatbot');
				echo '</span>';
				echo '<br/><button type="button" class="button-link cptbot-single-sync-btn" data-post-id="' . esc_attr($post_id) . '" style="font-size:11px; margin-top:4px; text-decoration:none; color:#0073aa;">';
				echo '🔄 ' . esc_html__('Re-sync', 'autonomous-ai-chatbot');
				echo '</button>';
			} elseif ($is_outdated) {
				echo '<span class="cptbot-pill cptbot-pill-outdated" title="' . esc_attr__('Modified since last sync', 'autonomous-ai-chatbot') . '" style="display:inline-flex; align-items:center; gap:4px; background:#fff3cd; color:#856404; border:1px solid #ffeeba; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">';
				echo '⚠ ' . esc_html__('Modified', 'autonomous-ai-chatbot');
				echo '</span>';
				echo '<br/><button type="button" class="button-link cptbot-single-sync-btn" data-post-id="' . esc_attr($post_id) . '" style="font-size:11px; margin-top:4px; text-decoration:none; color:#0073aa;">';
				echo '🔄 ' . esc_html__('Re-sync', 'autonomous-ai-chatbot');
				echo '</button>';
			} else {
				echo '<span class="cptbot-pill cptbot-pill-unsynced" style="display:inline-flex; align-items:center; gap:4px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">';
				echo '• ' . esc_html__('Not Synced', 'autonomous-ai-chatbot');
				echo '</span>';
				echo '<br/><button type="button" class="button-link cptbot-single-sync-btn" data-post-id="' . esc_attr($post_id) . '" style="font-size:11px; margin-top:4px; text-decoration:none; font-weight:600; color:#0073aa;">';
				echo '⚡ ' . esc_html__('Sync Now', 'autonomous-ai-chatbot');
				echo '</button>';
			}

			echo '</div>';
		}

		/**
		 * Register bulk action for posts and pages.
		 *
		 * @param array $bulk_actions Existing bulk actions.
		 * @return array
		 */
		public function wc_cptbot_register_bulk_sync($bulk_actions)
		{
			$bulk_actions['cptbot_bulk_sync'] = esc_html__('Sync with AI Chatbot', 'autonomous-ai-chatbot');
			return $bulk_actions;
		}

		/**
		 * Handle bulk action sync for posts and pages.
		 *
		 * @param string $redirect_to Redirect URL.
		 * @param string $action      Current action.
		 * @param array  $post_ids    Selected post IDs.
		 * @return string
		 */
		public function wc_cptbot_handle_bulk_sync($redirect_to, $action, $post_ids)
		{
			if ('cptbot_bulk_sync' !== $action || empty($post_ids) || !is_array($post_ids)) {
				return $redirect_to;
			}

			$synced_count = 0;
			$model_type   = get_option('cpt_bot_model_type', 'onnx');

			foreach ($post_ids as $post_id) {
				$post_id = (int) $post_id;
				$post    = get_post($post_id);
				if (!$post || !in_array($post->post_type, array('post', 'page'), true)) {
					continue;
				}

				$sync_result = $this->wc_cptbot_sync_single_post_internal($post_id);
				if (!empty($sync_result['success'])) {
					$synced_count++;
				}
			}

			$redirect_to = add_query_arg('cptbot_bulk_synced', $synced_count, $redirect_to);
			return $redirect_to;
		}

		/**
		 * Internal helper to sync a single post or page with configured vector DB.
		 *
		 * @param int $post_id Post ID.
		 * @param bool $force Force sync even if already synced.
		 * @return array
		 */
		public function wc_cptbot_sync_single_post_internal($post_id, $force = true)
		{
			$post = get_post((int) $post_id);
			if (!$post || !in_array($post->post_type, array('post', 'page'), true)) {
				return array('success' => false, 'message' => esc_html__('Invalid post or page.', 'autonomous-ai-chatbot'));
			}

			$model_type = get_option('cpt_bot_model_type', 'onnx');
			$vector_db  = get_option('wc_cptbot_vector_db', 'pinecone');

			// Prepare clean text and metadata
			$training_text = $this->helper->wc_cptbot_get_post_page_data_for_training($post);
			$meta_data     = $this->helper->wc_cptbot_get_post_page_meta_for_bot($post);

			if (empty($training_text)) {
				return array('success' => false, 'message' => esc_html__('Empty content, nothing to vectorize.', 'autonomous-ai-chatbot'));
			}

			$embedding_data = array();

			// Generate embeddings if server-side model is configured
			if ('gemini' === $model_type) {
				if (!class_exists('\WCAICHATBOT\Helper\Wc_Cptbot_Gemini')) {
					include_once WC_CPTBOT_PLUGIN_FILE . 'helper/class-wc-cptbot-gemini.php';
				}
				$gemini_helper = new \WCAICHATBOT\Helper\Wc_Cptbot_Gemini();
				$embed_resp    = $gemini_helper->wc_cptbot_create_embedding($training_text);
				$embedding_data = !empty($embed_resp['data'][0]['embedding']) ? $embed_resp['data'][0]['embedding'] : array();
			} elseif ('openai' === $model_type) {
				$embed_resp     = $this->openai->wc_cptbot_create_embedding($training_text, 384);
				$embedding_data = !empty($embed_resp['data'][0]['embedding']) ? $embed_resp['data'][0]['embedding'] : array();
			}

			// If server-side embedding was generated, upsert now
			if (!empty($embedding_data)) {
				$train_resp = $this->pinecone->upsert($embedding_data, $post->post_type, $post->ID, $meta_data, $force);

				if ($train_resp) {
					$cptbot_data = array(
						'content'    => wp_json_encode($meta_data),
						'type'       => $post->post_type,
						'trained_at' => current_time('mysql'),
						'trained_db' => $vector_db,
						'model_type' => $model_type,
					);
					update_post_meta($post->ID, 'cptbot_post_data', maybe_serialize($cptbot_data));

					// Track in scheduled actions table under posts_pages
					$this->helper->wc_cptbot_save_scheduled_actions(
						array(array('ID' => $post->ID)),
						array('status' => 'success'),
						'posts_pages'
					);

					return array(
						'success'    => true,
						'post_id'    => $post->ID,
						'message'    => esc_html__('Post synchronized successfully.', 'autonomous-ai-chatbot'),
						'trained_at' => current_time('mysql'),
					);
				}

				return array('success' => false, 'message' => esc_html__('Vector DB upsert failed.', 'autonomous-ai-chatbot'));
			}

			// If server-side embedding generation failed (e.g. missing API key or API error)
			if (in_array($model_type, array('gemini', 'openai'), true)) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: model name */
						esc_html__('Failed to generate embeddings using %s. Please verify your API key and settings.', 'autonomous-ai-chatbot'),
						strtoupper($model_type)
					),
				);
			}

			// For client-side ONNX / Ternlight, return the text payload for client computation
			return array(
				'success'       => true,
				'needs_onnx'    => true,
				'post_id'       => $post->ID,
				'post_type'     => $post->post_type,
				'training_text' => $training_text,
				'meta_data'     => $meta_data,
			);
		}

		/**
		 * AJAX handler: Fetch batch of posts and pages for training / ONNX embedding.
		 *
		 * @return void
		 */
		public function wc_cptbot_get_posts_pages_data()
		{
			if (!check_ajax_referer('ajaxnonce', 'nonce', false) || !current_user_can('manage_options')) {
				wp_send_json_error(array('message' => esc_html__('Security check failed.', 'autonomous-ai-chatbot')));
			}

			$limit     = !empty($_POST['limit']) ? intval($_POST['limit']) : 10;
			$offset    = !empty($_POST['offset']) ? intval($_POST['offset']) : 0;
			$post_type = !empty($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'all';

			$types = ('all' === $post_type) ? array('post', 'page') : array($post_type);

			$results     = $this->helper->wc_cptbot_get_all_posts_pages($limit, $offset, $types, 'all');
			$counts      = $this->helper->wc_cptbot_get_posts_pages_count($types);
			$total_count = $counts['total'];

			$data = array();
			if (!empty($results['items'])) {
				foreach ($results['items'] as $item) {
					$post_id = (int) $item['ID'];
					$text    = $this->helper->wc_cptbot_get_post_page_data_for_training($post_id);
					if (!empty($text)) {
						$data[$post_id] = $text;
					}
				}
			}

			$is_complete = empty($data) || ($offset + $limit >= $total_count);

			wp_send_json_success(
				array(
					'data'          => $data,
					'total_count'   => $total_count,
					'offset'        => $offset,
					'limit'         => $limit,
					'complete'      => $is_complete,
					'trained_count' => $counts['synced'],
				)
			);
		}

		/**
		 * AJAX handler: Upsert post/page vector received from client (ONNX) into Pinecone/ChromaDB.
		 *
		 * @return void
		 */
		public function wc_cptbot_upsert_post_page_vector()
		{
			if (!check_ajax_referer('ajaxnonce', 'nonce', false) || !current_user_can('manage_options')) {
				wp_send_json_error(array('message' => esc_html__('Security check failed.', 'autonomous-ai-chatbot')));
			}

			$post_id        = !empty($_POST['post_id']) ? intval($_POST['post_id']) : 0;
			$action_id      = !empty($_POST['action_id']) ? intval($_POST['action_id']) : 0;
			$force          = !empty($_POST['force']) ? true : false;
			$embedding_raw  = !empty($_POST['embedding']) ? sanitize_text_field(wp_unslash($_POST['embedding'])) : '';
			$embedding_data = !empty($embedding_raw) ? json_decode($embedding_raw, true) : array();

			if (empty($post_id) || empty($embedding_data) || !is_array($embedding_data)) {
				wp_send_json_error(array('message' => esc_html__('Invalid post ID or embedding vector.', 'autonomous-ai-chatbot')));
			}

			$post = get_post($post_id);
			if (!$post || !in_array($post->post_type, array('post', 'page'), true)) {
				wp_send_json_error(array('message' => esc_html__('Post or Page not found.', 'autonomous-ai-chatbot')));
			}

			$embedding_data = array_map('floatval', $embedding_data);
			$meta_data      = $this->helper->wc_cptbot_get_post_page_meta_for_bot($post);
			$vector_db      = get_option('wc_cptbot_vector_db', 'pinecone');

			$train_resp = $this->pinecone->upsert($embedding_data, $post->post_type, $post_id, $meta_data, $force);

			if ($train_resp) {
				$cptbot_data = array(
					'content'    => wp_json_encode($meta_data),
					'type'       => $post->post_type,
					'trained_at' => current_time('mysql'),
					'trained_db' => $vector_db,
					'model_type' => get_option('cpt_bot_model_type', 'onnx'),
				);
				update_post_meta($post_id, 'cptbot_post_data', maybe_serialize($cptbot_data));

				if (!empty($action_id)) {
					$this->helper->wc_cptbot_update_action_scheduler_status($action_id, 1, false);
				} else {
					$this->helper->wc_cptbot_save_scheduled_actions(
						array(array('ID' => $post_id)),
						array('status' => 'success'),
						'posts_pages'
					);
				}

				$counts = $this->helper->wc_cptbot_get_posts_pages_count();

				wp_send_json_success(
					array(
						'message'    => esc_html__('Vector saved successfully.', 'autonomous-ai-chatbot'),
						'post_id'    => $post_id,
						'trained_at' => current_time('mysql'),
						'counts'     => $counts,
					)
				);
			}

			wp_send_json_error(array('message' => esc_html__('Vector database upsert failed.', 'autonomous-ai-chatbot')));
		}

		/**
		 * AJAX handler: Synchronize a single post/page from the admin column button.
		 *
		 * @return void
		 */
		public function wc_cptbot_sync_single_post()
		{
			if (!check_ajax_referer('ajaxnonce', 'nonce', false) || !current_user_can('manage_options')) {
				wp_send_json_error(array('message' => esc_html__('Security check failed.', 'autonomous-ai-chatbot')));
			}

			$post_id = !empty($_POST['post_id']) ? intval($_POST['post_id']) : 0;
			if (empty($post_id)) {
				wp_send_json_error(array('message' => esc_html__('Missing post ID.', 'autonomous-ai-chatbot')));
			}

			$result = $this->wc_cptbot_sync_single_post_internal($post_id, true);

			if (!empty($result['success'])) {
				if (empty($result['needs_onnx'])) {
					$result['counts'] = $this->helper->wc_cptbot_get_posts_pages_count();
				}
				wp_send_json_success($result);
			} else {
				wp_send_json_error($result);
			}
		}

		/**
		 * AJAX handler: Batch sync posts & pages server-side (for Gemini/OpenAI).
		 *
		 * @return void
		 */
		public function wc_cptbot_sync_posts_pages()
		{
			if (!check_ajax_referer('ajaxnonce', 'nonce', false) || !current_user_can('manage_options')) {
				wp_send_json_error(array('message' => esc_html__('Security check failed.', 'autonomous-ai-chatbot')));
			}

			$limit     = !empty($_POST['limit']) ? intval($_POST['limit']) : 10;
			$offset    = !empty($_POST['offset']) ? intval($_POST['offset']) : 0;
			$post_type = !empty($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'all';
			$force     = !empty($_POST['force']) ? true : false;

			$types   = ('all' === $post_type) ? array('post', 'page') : array($post_type);
			$results = $this->helper->wc_cptbot_get_all_posts_pages($limit, $offset, $types, $force ? 'all' : 'unsynced');
			$counts  = $this->helper->wc_cptbot_get_posts_pages_count($types);

			$synced_now = 0;
			$failed_now = 0;

			if (!empty($results['items'])) {
				foreach ($results['items'] as $item) {
					$sync_res = $this->wc_cptbot_sync_single_post_internal($item['ID'], $force);
					if (!empty($sync_res['success']) && empty($sync_res['needs_onnx'])) {
						$synced_now++;
					} else {
						$failed_now++;
					}
				}
			}

			$updated_counts = $this->helper->wc_cptbot_get_posts_pages_count($types);

			wp_send_json_success(
				array(
					'synced_batch'   => $synced_now,
					'failed_batch'   => $failed_now,
					'total_count'    => $updated_counts['total'],
					'trained_count'  => $updated_counts['synced'],
					'unsynced_count' => $updated_counts['unsynced'],
					'percent'        => $updated_counts['percent'],
					'complete'       => empty($results['items']) || ($updated_counts['unsynced'] === 0),
				)
			);
		}

		/**
		 * AJAX handler: Reset posts & pages sync tracking.
		 *
		 * @return void
		 */
		public function wc_cptbot_reset_posts_pages_sync()
		{
			if (!check_ajax_referer('ajaxnonce', 'nonce', false) || !current_user_can('manage_options')) {
				wp_send_json_error(array('message' => esc_html__('Security check failed.', 'autonomous-ai-chatbot')));
			}

			// Delete cptbot_post_data meta from all posts and pages
			delete_post_meta_by_key('cptbot_post_data');

			wp_send_json_success(
				array(
					'message' => esc_html__('Posts & Pages sync reset successfully.', 'autonomous-ai-chatbot'),
				)
			);
		}

		/**
		 * Hook: Post saved or updated - mark or auto-sync if desired.
		 *
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 * @param bool     $update  Whether this is an existing post being updated.
		 * @return void
		 */
		public function wc_cptbot_handle_post_save($post_id, $post, $update)
		{
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}
			if (wp_is_post_revision($post_id)) {
				return;
			}
			if (!in_array($post->post_type, array('post', 'page'), true)) {
				return;
			}

			// If published and server-side model active, auto-sync
			$model_type = get_option('cpt_bot_model_type', 'onnx');
			if ('publish' === $post->post_status && in_array($model_type, array('gemini', 'openai'), true)) {
				$this->wc_cptbot_sync_single_post_internal($post_id, true);
			}
		}

		/**
		 * Hook: Post deleted - delete vector from Pinecone/ChromaDB.
		 *
		 * @param int $post_id Post ID.
		 * @return void
		 */
		public function wc_cptbot_handle_post_delete($post_id)
		{
			$post = get_post($post_id);
			if (!$post || !in_array($post->post_type, array('post', 'page'), true)) {
				return;
			}

			$vector_id = $post->post_type . '-' . $post_id;
			if (method_exists($this->pinecone, 'delete')) {
				try {
					$this->pinecone->delete(array($vector_id));
				} catch (\Exception $e) {
					// Silent catch
				}
			}
		}

		/**
		 * Enqueue admin script and styles for Posts and Pages list table inline sync.
		 *
		 * @param string $hook Screen hook.
		 * @return void
		 */
		public function wc_cptbot_enqueue_edit_posts_scripts($hook)
		{
			if ('edit.php' !== $hook) {
				return;
			}

			$screen = get_current_screen();
			if (!$screen || !in_array($screen->post_type, array('post', 'page'), true)) {
				return;
			}

			$modal_type = get_option('cpt_bot_model_type', 'onnx');
			if ('onnx' === $modal_type || 'ternlight' === $modal_type) {
				wp_enqueue_script('wc_cptbot_ort_js', WC_CPTBOT_PLUGIN_URL . 'assets/js/lib/ort.min.js', array('jquery'), WC_CPTBOT_SCRIPT_VERSION, true);
				$this->wc_cptbot_enqueue_bundle('wc_cptbot-script', 'admin', array('jquery', 'wc_cptbot_ort_js'), false);

				$translations = new Includes\Wc_Cptbot_Translations();
				$base_url     = wp_upload_dir()['baseurl'];
				wp_localize_script(
					'wc_cptbot-script',
					'wc_cptbot',
					array(
						'admin_ajax'         => admin_url('admin-ajax.php'),
						'nonce'              => wp_create_nonce('ajaxnonce'),
						'admin_url'          => admin_url(),
						'translation'        => $translations->wc_cpt_get_all_admin_translations(),
						'product_count'      => 0,
						'trained_count'      => 0,
						'limit'              => WC_CPTBOT_PRODUCTS_PER_BATCH,
						'is_model_load'      => true,
						'modal_type'         => $modal_type,
						'onnx_e5small_modal' => WC_CPTBOT_PLUGIN_URL . 'assets/models/e5-small.onnx',
						'onnx_modal_dir'     => WC_CPTBOT_PLUGIN_URL . 'assets/models',
						'model_worker_url'   => WC_CPTBOT_PLUGIN_URL . 'assets/js/modelWorker.js',
					)
				);
			}

			// Print inline styles and script for the 1-click sync column
			add_action('admin_footer', array($this, 'wc_cptbot_render_edit_posts_sync_inline_js'));
		}

		/**
		 * Render inline JS and CSS for edit.php sync column.
		 *
		 * @return void
		 */
		public function wc_cptbot_render_edit_posts_sync_inline_js()
		{
			$nonce      = wp_create_nonce('ajaxnonce');
			$ajax_url   = admin_url('admin-ajax.php');
			$model_type = get_option('cpt_bot_model_type', 'onnx');
			?>
			<style type="text/css">
				.cptbot-sync-cell { min-width: 120px; line-height: 1.5; }
				.cptbot-sync-spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid #ccc; border-top-color: #0073aa; border-radius: 50%; animation: cptbot-spin 0.8s linear infinite; vertical-align: middle; margin-right: 4px; }
				@keyframes cptbot-spin { to { transform: rotate(360deg); } }
			</style>
			<script type="text/javascript">
			(function($) {
				$(document).ready(function() {
					function showEditPostsNotice(message, type) {
						$('.cptbot-edit-posts-notice').remove();
						var notice = $('<div class="notice notice-' + (type || 'error') + ' is-dismissible cptbot-edit-posts-notice" style="margin: 15px 0;"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
						notice.find('.notice-dismiss').on('click', function() { $(this).closest('.notice').remove(); });
						if ($('hr.wp-header-end').length) {
							$('hr.wp-header-end').after(notice);
						} else {
							$('.wrap h1').first().after(notice);
						}
					}

					function ensureOnnxReady() {
						if (typeof window.wc_cptbot_upsert_post_page_onnx === 'function') {
							var sess = (typeof window.wc_cptbot_get_onnx_session === 'function') ? window.wc_cptbot_get_onnx_session() : null;
							if (sess) return Promise.resolve(true);
						}

						return new Promise(function(resolve) {
							var attempts = 0;
							var interval = setInterval(function() {
								attempts++;
								var sess = (typeof window.wc_cptbot_get_onnx_session === 'function') ? window.wc_cptbot_get_onnx_session() : null;
								if (sess && typeof window.wc_cptbot_upsert_post_page_onnx === 'function') {
									clearInterval(interval);
									resolve(true);
								} else if (attempts > 30) {
									clearInterval(interval);
									resolve(false);
								}
							}, 500);

							document.addEventListener('onOnnxModelReady', function() {
								clearInterval(interval);
								resolve(true);
							}, { once: true });
						});
					}

					$(document).on('click', '.cptbot-single-sync-btn', function(e) {
						e.preventDefault();
						var $btn = $(this);
						var postId = $btn.data('post-id');
						var $cell = $('#cptbot-sync-cell-' + postId);

						$btn.prop('disabled', true).html('<span class="cptbot-sync-spinner"></span> <?php echo esc_js(__('Syncing…', 'autonomous-ai-chatbot')); ?>');

						$.ajax({
							url: '<?php echo esc_url($ajax_url); ?>',
							type: 'POST',
							dataType: 'json',
							data: {
								action: 'wc_cptbot_sync_single_post',
								nonce: '<?php echo esc_js($nonce); ?>',
								post_id: postId
							},
							success: async function(res) {
								if (res && res.success) {
									var data = res.data;
									if (data && data.needs_onnx) {
										var curModel = '<?php echo esc_js($model_type); ?>';
										if (curModel === 'onnx') {
											$btn.html('<span class="cptbot-sync-spinner"></span> <?php echo esc_js(__('Loading AI…', 'autonomous-ai-chatbot')); ?>');
											var isReady = await ensureOnnxReady();
											if (!isReady) {
												showEditPostsNotice('<?php echo esc_js(__('ONNX AI model failed to initialize in browser. Please check console or refresh.', 'autonomous-ai-chatbot')); ?>', 'error');
												$btn.prop('disabled', false).html('🔄 <?php echo esc_js(__('Retry', 'autonomous-ai-chatbot')); ?>');
												return;
											}
										}

										$btn.html('<span class="cptbot-sync-spinner"></span> <?php echo esc_js(__('Embedding…', 'autonomous-ai-chatbot')); ?>');
										var onnxRes = await window.wc_cptbot_upsert_post_page_onnx(postId, data.training_text, true);

										if (onnxRes && (onnxRes.success || (onnxRes.data && onnxRes.data.success))) {
											showEditPostsNotice('<?php echo esc_js(__('Post synchronized successfully.', 'autonomous-ai-chatbot')); ?>', 'success');
											$cell.html(
												'<span class="cptbot-pill cptbot-pill-synced" style="display:inline-flex; align-items:center; gap:4px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">' +
												'✓ <?php echo esc_js(__('Synced', 'autonomous-ai-chatbot')); ?>' +
												'</span><br/>' +
												'<button type="button" class="button-link cptbot-single-sync-btn" data-post-id="' + postId + '" style="font-size:11px; margin-top:4px; text-decoration:none; color:#0073aa;">' +
												'🔄 <?php echo esc_js(__('Re-sync', 'autonomous-ai-chatbot')); ?>' +
												'</button>'
											);
										} else {
											var err = (onnxRes && onnxRes.error) ? onnxRes.error : ((onnxRes && onnxRes.data && onnxRes.data.message) ? onnxRes.data.message : '<?php echo esc_js(__('Vector upsert failed.', 'autonomous-ai-chatbot')); ?>');
											showEditPostsNotice(err, 'error');
											$btn.prop('disabled', false).html('🔄 <?php echo esc_js(__('Retry', 'autonomous-ai-chatbot')); ?>');
										}
									} else {
										// Server already synced (Gemini/OpenAI)
										showEditPostsNotice('<?php echo esc_js(__('Post synchronized successfully.', 'autonomous-ai-chatbot')); ?>', 'success');
										$cell.html(
											'<span class="cptbot-pill cptbot-pill-synced" style="display:inline-flex; align-items:center; gap:4px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">' +
											'✓ <?php echo esc_js(__('Synced', 'autonomous-ai-chatbot')); ?>' +
											'</span><br/>' +
											'<button type="button" class="button-link cptbot-single-sync-btn" data-post-id="' + postId + '" style="font-size:11px; margin-top:4px; text-decoration:none; color:#0073aa;">' +
											'🔄 <?php echo esc_js(__('Re-sync', 'autonomous-ai-chatbot')); ?>' +
											'</button>'
										);
									}
								} else {
									var msg = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js(__('Sync failed.', 'autonomous-ai-chatbot')); ?>';
									showEditPostsNotice(msg, 'error');
									$btn.prop('disabled', false).html('🔄 <?php echo esc_js(__('Retry', 'autonomous-ai-chatbot')); ?>');
								}
							},
							error: function(xhr, status, error) {
								showEditPostsNotice('<?php echo esc_js(__('AJAX error: ', 'autonomous-ai-chatbot')); ?>' + error, 'error');
								$btn.prop('disabled', false).html('🔄 <?php echo esc_js(__('Retry', 'autonomous-ai-chatbot')); ?>');
							}
						});
					});
				});
			})(jQuery);
			</script>
			<?php
		}
	}
}
