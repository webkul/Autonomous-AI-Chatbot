<?php

/**
 * Front functions
 *
 * @package Chatgpt bot for WooCommerce
 * @version 1.0.0
 */

namespace WCAICHATBOT\Includes\Front;

defined('ABSPATH') || exit();

use WCAICHATBOT\Templates\Front as FrontTemplate;
use WCAICHATBOT\helper;
use WCAICHATBOT\Includes;
use HelgeSverre\Toon\Toon;

if (! class_exists('Wc_Cptbot_Front_Functions')) {
	class Wc_Cptbot_Front_Functions
	{
		protected $helper;

		protected $openai;

		protected $gemini;

		protected $pinecone;

		protected $message_formatter;

		public function __construct()
		{
			$this->helper   = new helper\Wc_Cptbot_Helper();
			$this->openai   = new helper\Wc_Cptbot_Openai();

			$vector_db = get_option('wc_cptbot_vector_db', 'pinecone');
			if ('chromadb' === $vector_db) {
				$this->pinecone = new helper\Wc_Cptbot_Chromadb();
			} else {
				$this->pinecone = new helper\Wc_Cptbot_Pinecone();
			}

			$model_type = get_option('cpt_bot_model_type', 'onnx');
			if ('gemini' === $model_type) {
				$this->gemini = new helper\Wc_Cptbot_Gemini();
			}
			if ('openai' === $model_type) {
				$this->openai = new helper\Wc_Cptbot_Openai();
			}

			if ( class_exists( 'WooCommerce' ) ) {
				add_action('woocommerce_update_product', array($this, 'wc_cptbot_clear_price_cache'));
				add_action('woocommerce_new_product', array($this, 'wc_cptbot_clear_price_cache'));
				add_action('woocommerce_delete_product', array($this, 'wc_cptbot_clear_price_cache'));
			}

			$this->message_formatter = new Wc_Cptbot_Message_Formatter();
		}

		public function wc_cptbot_clear_price_cache()
		{
			delete_transient('wc_cptbot_product_prices');
		}

		public function wc_cptbot_enqueue_pos_scripts()
		{
			$blocked = get_user_meta(get_current_user_id(), 'wc_cptbot_block_user', true);

			if (empty($blocked) || 'unblock' === $blocked) {
				$base_url = wp_upload_dir()['baseurl'];

				wp_enqueue_style('wc-cptbot-front-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/app.css', array(), WC_CPTBOT_SCRIPT_VERSION);

				wp_enqueue_style('wc_cptbot-animate-style', WC_CPTBOT_PLUGIN_URL . 'assets/css/animate.css', array(), WC_CPTBOT_SCRIPT_VERSION);

				wp_enqueue_script('wc_cptbot_ort_js', WC_CPTBOT_PLUGIN_URL . 'assets/js/lib/ort.min.js', array(), WC_CPTBOT_SCRIPT_VERSION, false);

				$asset_file = WC_CPTBOT_PLUGIN_FILE . 'assets/js/app.asset.php';
				$asset      = is_readable( $asset_file ) ? require $asset_file : array(
					'dependencies' => array( 'jquery', 'wp-element', 'wp-components', 'wp-i18n' ),
					'version'      => WC_CPTBOT_SCRIPT_VERSION,
				);
				$app_deps = array_unique( array_merge( $asset['dependencies'], array( 'jquery', 'wc_cptbot_ort_js' ) ) );

				wp_enqueue_script('wc-cptbot-front-script', WC_CPTBOT_PLUGIN_URL . 'assets/js/app.js', $app_deps, $asset['version'], true);
				wp_set_script_translations('wc-cptbot-front-script', 'autonomous-ai-chatbot', WC_CPTBOT_PLUGIN_FILE . 'languages');

				$translations = new Includes\Wc_Cptbot_Translations();

				wp_localize_script(
					'wc-cptbot-front-script',
					'wc_cptbotObject',
					array(
						'ajaxurl'                => admin_url('admin-ajax.php'),
						'nonce'                  => wp_create_nonce('wc_cptbot_pos_ajax_nonce'),
						'site_url'               => site_url(),
						'translation'            => $translations->wc_cpt_get_all_front_translations(),
						'blocked_status'         => empty($blocked) ? 'unblocked' : $blocked,
						'chatgpt_img'            => ! empty(get_option('chatbot_avatar_url')) ? $base_url . get_option('chatbot_avatar_url') : WC_CPTBOT_PLUGIN_URL . 'assets/images/chatbot-logo.png',
						'chat_bg_img'            => ! empty(get_option('chatbot_bg_image_path')) ? $base_url . get_option('chatbot_bg_image_path') : WC_CPTBOT_PLUGIN_URL . 'assets/images/bg.webp',
						'ai_logo'                => ! empty(get_option('chatbot_ai_logo_path')) ? $base_url . get_option('chatbot_ai_logo_path') : WC_CPTBOT_PLUGIN_URL . 'assets/images/chatbot-logo.png',
						'site_icon_url'          => get_site_icon_url(),
						'avatar_url'             => get_avatar_url(get_current_user_id()),
						'user'                   => wp_get_current_user(),
						'voice_assistance'       => ! empty(get_option('cpt_allow_voice_assistant') && 'on' === get_option('cpt_allow_voice_assistant')) ? true : false,
						'animation'              => ! empty(get_option('cpt_allow_animations') && 'on' === get_option('cpt_allow_animations')) ? true : false,
						'primary_color'          => ! empty(get_option('chatbot_primary_color')) ? get_option('chatbot_primary_color') : '#670000',
						'secondary_color'        => ! empty(get_option('chatbox_secondary_color')) ? get_option('chatbox_secondary_color') : '#ffffff',
						'chatbot_name'           => ! empty(get_option('chatbot_name')) ? get_option('chatbot_name') : esc_html__('Autonomous AI Chatbot', 'autonomous-ai-chatbot'),
						'cpt_bot_status'         => get_option( 'wc_cpt_bot_status', 'disable' ),
						'cpt_bot_position'       => ! empty(get_option('cpt_bot_position')) ? get_option('cpt_bot_position') : 'right',
						'cpt_bot_allow_bg_image' => ! empty(get_option('cpt_allow_admin_background')) && 'on' === get_option('cpt_allow_admin_background') ? true : false,
						'is_online'              => $this->helper->wc_cptbot_check_bot_online(),
						'modal_type'             => get_option('cpt_bot_model_type', 'onnx'),
						'onnx_e5small_modal' => WC_CPTBOT_PLUGIN_URL . 'assets/models/e5-small.onnx',
						'onnx_modal_dir'     => WC_CPTBOT_PLUGIN_URL . 'assets/models',
						'model_worker_url'   => WC_CPTBOT_PLUGIN_URL . 'assets/js/modelWorker.js',
					)
				);
			}
		}


		public function wc_cptbot_add_custom_handle($handles)
		{
			$handles[] = 'wc-cptbot-front-style';
			return $handles;
		}

		public function wc_cptbot_chatbox_template()
		{
			new FrontTemplate\Wc_Cptbot_Chatbox_Template();
		}





		/**
		 * Get client query prompt.
		 *
		 * @return void
		 */
		public function wc_cptbot_get_client_query_prompt()
		{

			$response = array(
				'success' => false,
				'message' => esc_html__('Something went wrong, please try again!', 'autonomous-ai-chatbot'),
				'data'    => array()
			);

			$post         = ! empty($_POST) ? wcaichatbot_clean($_POST) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$client_query = ! empty($post['query']) ? $post['query'] : array();

			$history_content = ! empty($post['history_content']) ? $post['history_content'] : array();
			$history_content = json_decode(stripslashes($history_content));


			if (! empty($client_query)) {
				$wc_cpt_chatgpt_msg_history[] = $client_query;
				$prompt = implode(', ', $wc_cpt_chatgpt_msg_history);
				$this->wc_cptbot_saved_conversational_history($client_query, 'user', 0);

				$rag_required = false;

				// Intent classification: First try TypeSafe Jev if configured.
				$ai_start_time = microtime( true );
				$chat_data     = $this->helper->wc_cptbot_get_intent_from_jev( $client_query, $history_content );
				if ( isset( $this->helper ) && method_exists( $this->helper, 'wc_cptbot_record_ai_latency' ) ) {
					$this->helper->wc_cptbot_record_ai_latency( microtime( true ) - $ai_start_time );
				}

				// If Jev is not configured or returned no confident decision, fall back to standard LLM agent prompt.
				if ( empty( $chat_data ) ) {
					$agent_msg = $this->helper->wc_cptbot_get_agent_prompt( $history_content );
					$wk_ai_agent_request = array(
						'messages' => $agent_msg,
					);
					$llm_response = $this->openai->chat( $wk_ai_agent_request );
					$clean_response = preg_replace( '/```(json)?/i', '', $llm_response );
					$clean_response = trim( $clean_response );
					$chat_data = json_decode( $clean_response, true );
					$chat_data = ! empty( $chat_data ) ? $chat_data : array();
					if ( ! empty( $chat_data ) ) {
						$chat_data['_intent_source'] = 'llm_fallback';
					} else {
						// Local rule-based fallback
						$normalized = strtolower( trim( wp_strip_all_tags( $client_query ) ) );
						if ( preg_match( '/\b(hi|hello|hey|thanks|thank you|good morning|good afternoon|good evening)\b/i', $normalized ) ) {
							$chat_data = array(
								'answer'         => $this->helper->wc_cptbot_get_casual_intent_answer( $client_query ),
								'_intent_source' => 'casual_fallback',
							);
						} elseif ( preg_match( '/\b(cheapest|lowest price|cheap|inexpensive|low to high)\b/i', $normalized ) ) {
							$chat_data = array(
								'quality_type'   => 'min',
								'_intent_source' => 'rule_fallback',
							);
						} elseif ( preg_match( '/\b(expensive|highest price|premium|high to low)\b/i', $normalized ) ) {
							$chat_data = array(
								'quality_type'   => 'max',
								'_intent_source' => 'rule_fallback',
							);
						} elseif ( preg_match( '/\b(categories|category|departments)\b/i', $normalized ) ) {
							$chat_data = array(
								'get_categories' => true,
								'_intent_source' => 'rule_fallback',
							);
						} else {
							// General search / question fallback: route to RAG
							$chat_data = array(
								'rag_doc_required' => true,
								'doc_query'        => $client_query,
								'_intent_source'   => 'rag_fallback',
							);
						}
					}
				}

				$intent_debug = array(
					'source'     => ! empty( $chat_data['_intent_source'] ) ? $chat_data['_intent_source'] : 'llm_fallback',
					'intent'     => ! empty( $chat_data['_intent'] ) ? $chat_data['_intent'] : 'not_classified',
					'confidence' => isset( $chat_data['_intent_confidence'] ) ? $chat_data['_intent_confidence'] : null,
					'using_jev'  => ! empty( $chat_data['_intent_source'] ) && 'jev' === $chat_data['_intent_source'],
				);

				$response['intent_debug'] = $intent_debug;

				// Filter hook for WooCommerce PRO Addon to intercept commerce intents.
				$handled = apply_filters( 'wcaichatbot_handle_commerce_intent', null, $chat_data, $history_content, $client_query );
				if ( ! empty( $handled ) ) {
					if ( isset( $handled['data'] ) ) {
						$this->wc_cptbot_saved_conversational_history( $handled['data'] );
					}
					if ( empty( $handled['intent_debug'] ) ) {
						$handled['intent_debug'] = $intent_debug;
					}
					wp_send_json( $handled );
					return;
				}

				// If PRO Addon is not active and a commerce action is attempted, show upgrade message.
				if ( ! function_exists( 'wcaichatbot_is_pro_active' ) || ! wcaichatbot_is_pro_active() ) {
					if ( ! empty( $chat_data['add_cart'] ) || ! empty( $chat_data['remove_cart'] ) || ! empty( $chat_data['view_cart'] ) || ! empty( $chat_data['checkout'] ) || ! empty( $chat_data['apply_coupon'] ) || ! empty( $chat_data['shipping'] ) || ! empty( $chat_data['coupon'] ) || ! empty( $chat_data['quality_type'] ) || ! empty( $chat_data['sku'] ) || ! empty( $chat_data['get_categories'] ) ) {
						$upgrade_url = function_exists( 'wcaichatbot_get_pro_upgrade_url' ) ? wcaichatbot_get_pro_upgrade_url() : '#';
						$pro_msg     = sprintf(
							/* translators: %s: upgrade link */
							esc_html__( 'Shopping cart, coupon, shipping, and checkout features require the WooCommerce PRO addon. %s', 'autonomous-ai-chatbot' ),
							'<a href="' . esc_url( $upgrade_url ) . '" class="wcaichatbot-chat-upgrade-link">' . esc_html__( 'Upgrade to PRO', 'autonomous-ai-chatbot' ) . '</a>'
						);
						$response = array(
							'success'            => true,
							'data'               => $pro_msg,
							'message'            => esc_html__( 'PRO Addon Required', 'autonomous-ai-chatbot' ),
							'is_direct_response' => true,
							'intent_debug'       => $intent_debug,
						);
						$this->wc_cptbot_saved_conversational_history( $pro_msg );
						wp_send_json( $response );
						return;
					}
				}

				do_action( 'wcaichatbot_after_commerce_intent_check', $chat_data, $history_content, $client_query );










				if (isset($chat_data['answer']) && !empty($chat_data['answer'])) {
					$response['data'] = $chat_data['answer'];
					$response['success'] = true;
					$response['is_direct_response'] = true;
					$response['message'] = esc_html__('Answer fetched successfully.', 'autonomous-ai-chatbot');


					$this->wc_cptbot_saved_conversational_history($chat_data['answer']);


					wp_send_json($response);
					return;
				}





				if (! empty($chat_data['rag_doc_required'])) {
					$rag_value = $chat_data['rag_doc_required'];
					$rag_required = ($rag_value === true || $rag_value === 'true' || $rag_value === 'True');
					if ($rag_required) {
						$prompt = ! empty($chat_data['doc_query']) ? wp_json_encode($chat_data['doc_query']) : $prompt;
					}
				}


				$where_filter = null;
				if (isset($chat_data['chromadb_where'])) {
					$where_filter = $chat_data['chromadb_where'];
				}

				$response['data'] = $prompt;
				$response['chromadb_where'] = $where_filter;
				$response['needs_history'] = true;
				$response['rag_required'] = $rag_required;
				$response['success'] = true;
				$response['message'] = esc_html__('User prompt loaded successfully.', 'autonomous-ai-chatbot');

				wp_send_json($response);
			}
		}



		/**
		 * Process RAG query.
		 *
		 * @return void
		 */
		public function wc_cptbot_process_rag_query()
		{
			$post = ! empty($_POST) ? wcaichatbot_clean($_POST) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$history_content = ! empty($post['history_content']) ? $post['history_content'] : array();
			$history_content = json_decode(stripslashes($history_content));
			$embedding_data = ! empty($post['embedding_data']) ? (array) json_decode(stripslashes($post['embedding_data'])) : array();

			$model_type = get_option('cpt_bot_model_type', 'onnx');

			$user_query = '';
			if (isset($post['query']) && ! empty($post['query'])) {
				$user_query = $post['query'];
			} elseif (isset($post['message']) && ! empty($post['message'])) {
				$user_query = $post['message'];
			}

			if ($model_type === 'gemini'){
				$embedding_data = $this->gemini->wc_cptbot_create_embedding($user_query);
				$embedding_data = $embedding_data['data'][0]['embedding'];
			}

			if ($model_type === 'openai') {
				$embedding_data = $this->openai->wc_cptbot_create_embedding($user_query,384);
				$embedding_data = $embedding_data['data'][0]['embedding'];
			}

			if (! isset($product_data)) {
				$product_data = array();
			}

			$rag_required = isset($post['rag_required']) ? ($post['rag_required'] === 'true' || $post['rag_required'] === true) : true;
			$chroma_where_filter = (array) json_decode(stripslashes($post['chromadb_where'])) ?? null;


			if (! empty($embedding_data) || ! $rag_required) {
				$product_data = array();

				if (! empty($embedding_data)) {
					$vector_db = get_option('wc_cptbot_vector_db', 'pinecone');

					if ($vector_db === 'pinecone') {
						$match_info = $this->pinecone->query($embedding_data, 5, ! empty($chroma_where_filter) ? $chroma_where_filter : null);
					} else {

						$match_info = $this->pinecone->query($embedding_data, 5, null, null, ! empty($chroma_where_filter) ? $chroma_where_filter : null);
					}

					$blocked = get_user_meta(get_current_user_id(), 'wc_cptbot_block_user', true);

					$product_data    = array();
					$knowledge_posts = array();

					if (isset($match_info['data']) && is_array($match_info['data']) && isset($match_info['data'][0]) && (empty($blocked) || 'unblock' === $blocked)) {
						$all_matches = $match_info['data'];
						$current_user = get_current_user_id();
						$new_products_added = 0;

						foreach ($all_matches as $key => $value) {
							$trained_db = ! empty($value['metadata']['trained_db']) ? $value['metadata']['trained_db'] : 'pinecone';
							if ($trained_db === $vector_db) {
								$meta = $value['metadata'];
								$meta['is_fallback']      = false;
								$meta['similarity_score'] = $value['score'];

								$p_type = ! empty( $meta['post_type'] ) ? $meta['post_type'] : '';
								if ( in_array( $p_type, array( 'post', 'page' ), true ) ) {
									$knowledge_posts[] = array(
										'id'      => $meta['ID'] ?? $meta['post_id'] ?? 0,
										'title'   => $meta['title'] ?? $meta['Name'] ?? '',
										'type'    => ucfirst( $p_type ),
										'url'     => $meta['url'] ?? '',
										'content' => $meta['content'] ?? $meta['excerpt'] ?? '',
									);
								} else {
									$product_data[] = $meta;
								}
								$new_products_added++;
							}
						}
					}

					$product_data = $this->optimize_product_data_for_llm($product_data);

					$expanded_products = array();
					foreach ($product_data as $product) {
						$product_type = $product['Type'] ?? $product['product_type'] ?? '';
						if ($product_type === 'variable') {
							$product_id = $product['ID'] ?? null;
							if ($product_id) {
								$variations = $this->wc_cptbot_get_variations_locally($product_id);
								if (! empty($variations)) {
									foreach ($variations as $variation_data) {
										$variation_product = $product;
										$variation_product['Name'] = $variation_data['name'];
										$variation_product['Price'] = $variation_data['formatted_price'];
										$variation_product['Stock'] = $variation_data['stock_status'];
										$variation_product['Quantity'] = ! empty( $variation_data['stock_quantity'] ) ? $variation_data['stock_quantity'] : (( "instock" === $variation_data['stock_status'] || "onbackorder" === $variation_data['stock_status']) ? 'Available' : 0 );
										$variation_product['Type'] = 'variation';
										$variation_product['variation_id'] = $variation_data['id'];

										// Add variant attributes in the format the AI expects
										if (! empty($variation_data['attributes']) && is_array($variation_data['attributes'])) {
											$variant_array = array();
											foreach ($variation_data['attributes'] as $attr_name => $attr_value) {
												// Clean up the attribute name (remove pa_ prefix if present)
												$clean_name = str_replace('pa_', '', $attr_name);
												$clean_name = str_replace('_', ' ', $clean_name);
												$clean_name = ucwords($clean_name);

												$variant_array[] = array(
													'name' => $clean_name,
													'values' => array($attr_value)
												);
											}
											$variation_product['VARIANTS'] = json_encode($variant_array);
										}

										$expanded_products[] = $variation_product;
									}
								}
							}
						} else {
							$expanded_products[] = $product;
						}
					}

					$unique_keys = array();
					$deduped = array();
					foreach ($expanded_products as $p) {
						$key = '';
						if (! empty($p['variation_id'])) {
							$key = 'v_' . $p['variation_id'];
						} elseif (! empty($p['ID'])) {
							$key = 'p_' . $p['ID'];
						} else {
							$name = isset($p['Name']) ? $p['Name'] : (isset($p['name']) ? $p['name'] : '');
							$key = 'n_' . md5(strtolower(trim($name)));
						}

						if (! isset($unique_keys[$key])) {
							$unique_keys[$key] = true;
							$deduped[] = $p;
						}
					}

					$product_data = $deduped;

					if (! empty($product_data)) {
						do_action('wcaichatbot_record_product_inquiry', $product_data);
					}
				}

				$is_fallback_suggestion = false;

				$wc_cpt_chatgpt_msg = $this->wc_cptbot_get_system_prompt($product_data, $is_fallback_suggestion, !empty($knowledge_posts) ? $knowledge_posts : array());

				$openai_messages = $this->helper->convert_history_to_openai_messages($history_content, $wc_cpt_chatgpt_msg, 500, 5);

				$wk_ai_data_request = array(
					'messages'    => $openai_messages,
				);

				$ai_start_time = microtime( true );
				$chat_data = $this->openai->chat($wk_ai_data_request);
				if ( isset( $this->helper ) && method_exists( $this->helper, 'wc_cptbot_record_ai_latency' ) ) {
					$this->helper->wc_cptbot_record_ai_latency( microtime( true ) - $ai_start_time );
				}
				$chat_data = $this->helper->convert_link_to_anchor($chat_data);

				$this->wc_cptbot_saved_conversational_history($chat_data);

				wp_send_json($chat_data);
			} else {
				wp_send_json( esc_html__('Error: No embedding data received', 'autonomous-ai-chatbot') );
			}
		}

		/**
		 * Legacy alias for wc_cptbot_process_rag_query.
		 */
		public function wc_cptbot_client_query() {
			$this->wc_cptbot_process_rag_query();
		}

		/**
		 * Legacy alias for wc_cptbot_process_rag_query.
		 */
		public function wc_cptbot_client_query_for_onnx() {
			$this->wc_cptbot_process_rag_query();
		}


		/**
		 * Get system prompt.
		 *
		 * @param array $product_data The product data.
		 * @param bool  $is_fallback_suggestion Whether to include fallback suggestion.
		 * @param array $knowledge_data Post and page knowledge base data.
		 * @return string The system prompt.
		 */
		public function wc_cptbot_get_system_prompt($product_data, $is_fallback_suggestion = false, $knowledge_data = array())
		{


			$currency_code = $this->helper->wc_cptbot_get_currency_type();
			$currency_symbol = wcaichatbot_get_currency_symbol( $currency_code );

			$content = "You are a helpful e-commerce shopping and website assistant.\n"
				. "Use a helpful, polite, and sales-friendly tone, like a real online store assistant.\n\n"
				. "### STRICT DATA USAGE RULES:\n"
				. "- Use ONLY the supplied product catalog and website knowledge data to generate your response.\n"
				. "- Do NOT use general external knowledge, assumptions, or invent information.\n"
				. "- Do NOT invent prices, availability, features, or policies.\n"
				. "- If the required information is missing, clearly say it is not available.\n\n"
				. "### WHAT YOU SHOULD ANSWER:\n"
				. "- Product name, description, specifications, variants, size, color, sku, product link from PRODUCT CATALOG.\n"
				. "- " . $currency_symbol . " + Price, discounts, availability, stock status (only if present in product data).\n"
				. "- Store policies (shipping, returns, refunds, terms, FAQs), company information, and blog articles from the WEBSITE KNOWLEDGE.\n"
				. "- When answering from WEBSITE KNOWLEDGE, include direct URLs/links when available.\n"
				. "- Product comparisons when multiple product records are provided.\n\n"
				. "### WHAT YOU SHOULD NOT ANSWER:\n"
				. "- Questions unrelated to the provided product or website data.\n"
				. "- Speculative or estimated information.\n\n"
				. "### RESPONSE STYLE:\n"
				. "- Be clear, concise, and customer-friendly.\n"
				. "- Use bullet points when listing features or specifications.\n"
				. "- Do not mention internal systems, RAG, databases, or documents.\n"
				. "- Do not use table in multiple products. Instead use bullet points.\n"
				. "- **IMPORTANT**: Avoid generic closing phrases like 'Feel free to ask if you need anything else' or 'Let me know if you have more questions'.\n"
				. "- **PIPELINE GUIDANCE**: Suggest the next logical step in the journey (e.g., viewing details, adding to cart, or exploring related guides).\n"
				. "\n## OUTPUT FORMAT: MD\n";

			if (!empty($knowledge_data)) {
				$content .= "\n### WEBSITE KNOWLEDGE & POLICIES:\n";
				foreach ($knowledge_data as $k_item) {
					$content .= "- **" . esc_html($k_item['title']) . "** (" . esc_html($k_item['type']) . ")\n";
					if (!empty($k_item['url'])) {
						$content .= "  URL: " . esc_url($k_item['url']) . "\n";
					}
					if (!empty($k_item['content'])) {
						$content .= "  Details: " . $k_item['content'] . "\n";
					}
				}
				$content .= "\n";
			}

			if (!empty($product_data)) {
				$content .= "\n### PRODUCT CATALOG:\n";
				$content .= json_encode($product_data, JSON_PRETTY_PRINT);
			} elseif (empty($knowledge_data)) {
				$content .= "No products or knowledge articles available.";
			}

			return $content;
		}

		/**
		 * Query structure.
		 *
		 * @param string $client_query The client query.
		 * @param bool $history Whether to include chat history.
		 * @param int $post_count The number of posts to include.
		 * @return array The query structure.
		 */
		public function wc_cptbot_query_structure($client_query, $history = false, $post_count = 0)
		{
			$count              = (0 === $post_count) ? 1 : $post_count;
			$wc_cpt_chatgpt_msg = array();
			$msg_history        = array();
			$wk_ai_conversation_end_messages = $client_query;

			// Prepare the user query message to be included in the AI conversation.
			$wc_cpt_chatgpt_msg[] = array(
				'role'    => 'user',
				'content' => $wk_ai_conversation_end_messages,
			);

			if ($history) {
				$todays_chat_history = $this->helper->wc_chatgpt_get_chat_history($count);

				$todays_chat_history = array_reverse($todays_chat_history);

				if (! empty($todays_chat_history)) {
					foreach ($todays_chat_history as $value) {
						$msg_history[] = $value['message'];
					}
					$wc_cpt_chatgpt_msg[0]['content'] = implode(' ', $msg_history) . ' ' . $client_query;
				}
			}

			return $wc_cpt_chatgpt_msg;
		}

		/**
		 * Get query history.
		 *
		 * @param int $post_count The number of posts to include.
		 * @return array The query history.
		 */
		public function wc_chatbot_get_query_history($post_count = 0)
		{
			$count = (0 === $post_count) ? 1 : $post_count;
			$todays_chat_history = $this->helper->wc_chatgpt_get_chat_history($count);
			$todays_chat_history = array_reverse($todays_chat_history);

			$wc_cpt_chatgpt_msg[] = array(
				'role'    => 'user',
				'content' => '',
			);

			if (! empty($todays_chat_history)) {
				foreach ($todays_chat_history as $value) {
					$msg_history[] = $value['message'];
				}

				$wc_cpt_chatgpt_msg[0]['content'] = implode(' ', $msg_history);
			}

			return $wc_cpt_chatgpt_msg;
		}

		/**
		 * Get variations locally.
		 *
		 * @param int $product_id The product ID.
		 * @return array The variations data.
		 */
		private function wc_cptbot_get_variations_locally($product_id)
		{
			$variations_data = array();

			$wc_product = wc_get_product($product_id);

			if ($wc_product && $wc_product->is_type('variable')) {
				$variations = $wc_product->get_available_variations();
				$parent_manages_stock = $wc_product->managing_stock();

				foreach ($variations as $variation_info) {
					$variation_id = $variation_info['variation_id'];
					$variation = wc_get_product($variation_id);

					if ($variation) {
						$stock_quantity = $variation->get_stock_quantity();
						$stock_status = $variation->get_stock_status();

						if ($parent_manages_stock && $stock_quantity === 0) {
							$stock_status = 'outofstock';
						}

						if ($stock_quantity === null) {
							$stock_quantity = 0;
						}

						$attributes = $variation->get_attributes();
						foreach ( $wc_product->get_attributes() as $taxonomy => $attribute ) {
							if ( ! isset( $attributes[ $taxonomy ] ) || empty( $attributes[ $taxonomy ] ) ) {
								$terms = wc_get_product_terms(
									$product_id,
									$taxonomy,
									array( 'fields' => 'slugs' )
								);
								if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
									$attributes[ $taxonomy ] = 'Any: ' . implode( ', ', $terms );
								}
							}
						}

						$variations_data[] = array(
							'id' => $variation_id,
							'name' => $variation->get_name(),
							'price' => $variation->get_price(),
							'formatted_price' => wcaichatbot_get_currency_symbol() . $variation->get_price(),
							'stock_status' => $stock_status,
							'stock_quantity' => ! empty( $stock_quantity ) ? $stock_quantity : ( 'instock' === $stock_status ? 'Available' : 0 ),
							'attributes' => $attributes,
						);
					}
				}
			}

			return $variations_data;
		}

		/**
		 * Optimize product data for LLM.
		 *
		 * @param array $product_data The product data.
		 * @return array The optimized product data.
		 */
		private function optimize_product_data_for_llm($product_data)
		{
			$optimized = array();

			foreach ($product_data as $product) {

				$variants = $product['VARIANTS'] ?? null;
				if (empty($variants)) {
					$extracted_variants = array();
					foreach ($product as $key => $value) {
						if (strpos($key, 'variants_') === 0 && is_array($value)) {
							$name = ucfirst(str_replace('variants_', '', $key));
							$extracted_variants[] = array(
								'name'   => $name,
								'values' => $value
							);
						}
					}
					if (!empty($extracted_variants)) {
						$variants = $extracted_variants;
					}
				}

				$optimized[] = array(
					'ID' => $product['ID'],
					'Name' => $product['Name'],
					'SKU' => $product['SKU'] ?? 'sku_' . $product['ID'],
					'Price' => $product['Price'] ?? $product['Lowest Price'] ?? 'N/A',
					'Type' => $product['Type'] ?? $product['product_type'] ?? 'simple',
					'Stock' => $product['Stock'] ?? 'instock',
					'Quantity' => ! empty( $product['Quantity'] ) ? $product['Quantity'] : (( 'instock' === $product['Stock'] || 'onbackorder' === $product['Stock'] ) ? 'Available' : 0 ),
					'category_names' => $product['category_names'] ?? '',
					'link' => $product['Permalink'] ?? '',
					'VARIANTS' => $variants
				);
			}

			return $optimized;
		}

		/**
		 * Clean message for history.
		 *
		 * @param string $message The message to clean.
		 * @return string The cleaned message.
		 */
		private function wc_cptbot_clean_message_for_history($message) {
			if (empty($message)) {
				return '';
			}

			$message = str_replace('<!-- CATEGORY_LIST_RESPONSE -->', '', $message);
			$message = wp_strip_all_tags($message);

			return trim($message);
		}

		/**
		 * Save conversational history.
		 *
		 * @param string $conversation The conversation to save.
		 */
		private function wc_cptbot_saved_conversational_history($conversation, $message_type = 'ai', $message_status = 1) {
			$user_id = get_current_user_id();

			if ( 0 === $user_id ) {
				$enable_guest_history = get_option('cpt_enable_guest_history');
				if ( 'on' !== $enable_guest_history ) {
					return;
				}
			}

			$cleaned_conversation = $this->wc_cptbot_clean_message_for_history($conversation);

			$msgdata = array(
				'user_id'        => $user_id,
				'message_type'   => $message_type,
				'message'        => $cleaned_conversation,
				'chat_status'    => 1,
				'message_status' => $message_status,
			);
			$this->helper->wc_cptbot_save_chat_data($msgdata);
		}

		/**
		 * Get server side embedding.
		 *
		 * @return void
		 */
		public function wc_cptbot_get_server_side_embedding() {
			if ( check_ajax_referer('wc_cptbot_pos_ajax_nonce', 'nonce', false) ) {

				$text = !empty($_POST['text']) ? sanitize_text_field(wp_unslash($_POST['text'])) : '';
				$model_type = !empty($_POST['model_type']) ? sanitize_text_field(wp_unslash($_POST['model_type'])) : get_option('cpt_bot_model_type', 'onnx');

				if (empty($text)) {
					wp_send_json_error('Text is required');
				}

				$embedding = array();

				if ('gemini' === $model_type) {
					if (!class_exists('WCAICHATBOT\Helper\Wc_Cptbot_Gemini')) {
						require_once WC_CPTBOT_PLUGIN_FILE . 'helper/class-wc-cptbot-gemini.php';
					}
					$gemini_helper = new helper\Wc_Cptbot_Gemini();
					$result = $gemini_helper->wc_cptbot_create_embedding($text);
					if (!empty($result['data'][0]['embedding'])) {
						$embedding = $result['data'][0]['embedding'];
					}
				} elseif ('openai' === $model_type) {
					$result = $this->openai->wc_cptbot_create_embedding($text);
					if (!empty($result['data'][0]['embedding'])) {
						$embedding = $result['data'][0]['embedding'];
					}
				}

				if (!empty($embedding)) {
					wp_send_json_success($embedding);
				} else {
					wp_send_json_error('Failed to generate embedding');
				}
			}
		}

		/**
		* Add custom directories to the list of ignored directories for the plugin.
		*
		* This function appends additional directories ('modules' and 'bin')
		* to the default ignore list used by the WordPress plugin system.
		*
		* @param array $default_ignore_directories The existing list of directories to ignore.
		* @return array The updated list of directories to ignore.
		*/
		public function wc_cptbot_wp_plugin_check_ignore_directories( $default_ignore_directories )
		{
			$default_ignore_directories[] = 'bin';

			return $default_ignore_directories;
		}

		/**
		 * Add custom files to the list of ignored files for the plugin.
		 *
		 * This function appends additional files ('.env.example')
		 * to the default ignore list used by the WordPress plugin system.
		 *
		 * @param array $default_ignore_files The existing list of files to ignore.
		 * @return array The updated list of files to ignore.
		 */
		public function wc_cptbot_wp_plugin_check_ignore_files( $default_ignore_files ) {

			$default_ignore_files[] = '.gitignore';
			$default_ignore_files[] = '.gitmodules';

			return $default_ignore_files;
		}






	}
}
