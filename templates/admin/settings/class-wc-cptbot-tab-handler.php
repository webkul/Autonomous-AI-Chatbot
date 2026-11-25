<?php
/**
 * Tab handler class file.
 *
 * @package Chatgpt bot for WooCommerce
 * @version 1.0.0
 */

namespace WCAICHATBOT\Templates\Admin\Settings;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'Wc_Cptbot_Tab_Handler' ) ) {
	/**
	 * Tab handler class.
	 */
	class Wc_Cptbot_Tab_Handler {
		/**
		 * Constructor of this class.
		 */
		public function __construct() {
			$this->get_settings_template();
		}

		public function get_settings_template() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			$page        = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( isset( $page ) && ! empty( $page ) && 'chatgpt-bot' === $page ) {
				$tabs = apply_filters(
					'wcaichatbot_modify_tabs',
					array(
						'chatgpt-bot'   => esc_html__( 'Dashboard', 'autonomous-ai-chatbot' ),
						'history'       => esc_html__( 'Conversation History', 'autonomous-ai-chatbot' ),
						'content'       => esc_html__( 'Content Creation', 'autonomous-ai-chatbot' ),
						'training'             => esc_html__( 'Data Training', 'autonomous-ai-chatbot' ),
						'configuration'        => esc_html__( 'Settings', 'autonomous-ai-chatbot' ),
						'extensions'           => esc_html__( 'Extensions', 'autonomous-ai-chatbot' ),
						'support-and-services' => esc_html__( 'Support & Services', 'autonomous-ai-chatbot' ),

					)
				);

				if ( ! array_key_exists( $current_tab, $tabs ) ) {
					$current_tab = 'chatgpt-bot';
				}

				echo '<div class="wrap">';
				echo '<div class="wc-cptbot-admin-page-wrapper">';

				echo '<div class="wc-cptbot-main-header-wrapper">';
				echo '<img class="wc-cptbot-main-header-logo" src="' . esc_url( WC_CPTBOT_PLUGIN_URL . 'assets/images/chatbot-logo.png' ) . '" alt="' . esc_attr__( 'Autonomous AI Chatbot Logo', 'autonomous-ai-chatbot' ) . '">';
				echo '<h1 class="wc-cptbot-main-header">' . esc_html__( 'Autonomous AI Chatbot', 'autonomous-ai-chatbot' ) . ' ' . wp_kses_post( wcaichatbot_get_edition_badge_html() ) . '</h1>';
				echo '</div>';
				echo '<hr class="wp-header-end" style="display:none;">';

				// Start the main layout container (white background card)
				echo '<div class="wc-cptbot-admin-layout">';

				// Render tab navigation
				echo '<nav class="nav-tab-wrapper">';
				foreach ( $tabs as $tab_name => $tab_label ) {
					$tab_url = add_query_arg( 'tab', $tab_name, remove_query_arg( 'settings-updated' ) );
					$active  = ( $current_tab === $tab_name ) ? 'nav-tab-active' : '';
					echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab ' . esc_attr( $active ) . '">' . wp_kses_post( $tab_label ) . '</a>';
				}
				echo '</nav>';

				// Render tab content wrapper
				echo '<div class="wc-cptbot-admin-content-wrapper">';
				do_action( 'wcaichatbot_cptbot_chatgpt_bot_' . $current_tab, $this );
				echo '</div>';

				echo '</div>'; // End .wc-cptbot-admin-layout
				echo '</div>'; // End .wc-cptbot-admin-page-wrapper
				echo '</div>'; // End .wrap

			}
		}
	}
}
