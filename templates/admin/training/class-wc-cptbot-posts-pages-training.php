<?php
/**
 * Posts and Pages Training Template and Table.
 *
 * @package Autonomous AI Chatbot
 * @version 1.0.0
 */

namespace WCAICHATBOT\Templates\Admin\Training;

use WP_List_Table;
use WCAICHATBOT\Helper\Wc_Cptbot_Helper;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'WCAICHATBOT\Templates\Admin\Training\Wc_Cptbot_Posts_Pages_Training_Table' ) ) {
	/**
	 * Posts & Pages List Table for Data Training.
	 */
	class Wc_Cptbot_Posts_Pages_Training_Table extends WP_List_Table {

		/**
		 * Constructor.
		 */
		public function __construct() {
			parent::__construct(
				array(
					'singular' => esc_html__( 'Post or Page', 'autonomous-ai-chatbot' ),
					'plural'   => esc_html__( 'Posts and Pages', 'autonomous-ai-chatbot' ),
					'ajax'     => false,
				)
			);
		}

		/**
		 * Prepare table items.
		 */
		public function prepare_items() {
			$columns  = $this->get_columns();
			$hidden   = array();
			$sortable = array();

			$filter_type   = filter_input( INPUT_GET, 'post_type_filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$filter_status = filter_input( INPUT_GET, 'sync_status_filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$paged         = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT );
			$paged         = ! empty( $paged ) ? (int) $paged : 1;
			$per_page      = 15;
			$offset        = ( $paged - 1 ) * $per_page;

			$types = array( 'post', 'page' );
			if ( in_array( $filter_type, array( 'post', 'page' ), true ) ) {
				$types = array( $filter_type );
			}

			$status = 'all';
			if ( in_array( $filter_status, array( 'synced', 'unsynced' ), true ) ) {
				$status = $filter_status;
			}

			$helper  = new Wc_Cptbot_Helper();
			$results = $helper->wc_cptbot_get_all_posts_pages( $per_page, $offset, $types, $status );
			$counts  = $helper->wc_cptbot_get_posts_pages_count( $types );

			$total_items = ( 'synced' === $status ) ? $counts['synced'] : ( ( 'unsynced' === $status ) ? $counts['unsynced'] : $counts['total'] );

			$this->_column_headers = array( $columns, $hidden, $sortable );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
					'total_pages' => ceil( $total_items / $per_page ),
				)
			);

			$this->items = ! empty( $results['items'] ) ? $results['items'] : array();
		}

		/**
		 * Define table columns.
		 *
		 * @return array
		 */
		public function get_columns() {
			return array(
				'title'     => esc_html__( 'Title', 'autonomous-ai-chatbot' ),
				'post_type' => esc_html__( 'Type', 'autonomous-ai-chatbot' ),
				'modified'  => esc_html__( 'Last Modified', 'autonomous-ai-chatbot' ),
				'status'    => esc_html__( 'Sync Status', 'autonomous-ai-chatbot' ),
				'action'    => esc_html__( 'Action', 'autonomous-ai-chatbot' ),
			);
		}

		/**
		 * Define default primary column name for responsive views.
		 *
		 * @return string
		 */
		protected function get_default_primary_column_name() {
			return 'title';
		}

		/**
		 * Default column renderer.
		 *
		 * @param array  $item Item row.
		 * @param string $column_name Column name.
		 * @return string
		 */
		public function column_default( $item, $column_name ) {
			$post_id = (int) $item['ID'];

			switch ( $column_name ) {
				case 'title':
					$edit_url = get_edit_post_link( $post_id );
					$view_url = $item['url'];
					$title    = esc_html( $item['title'] );

					$output  = '<strong><a class="row-title" href="' . esc_url( $edit_url ) . '">' . $title . '</a></strong>';
					$output .= '<div class="row-actions">';
					$output .= '<span class="edit"><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'autonomous-ai-chatbot' ) . '</a> | </span>';
					$output .= '<span class="view"><a href="' . esc_url( $view_url ) . '" target="_blank">' . esc_html__( 'View', 'autonomous-ai-chatbot' ) . '</a></span>';
					$output .= '</div>';
					return $output;

				case 'post_type':
					$badge_color = ( 'page' === $item['post_type'] ) ? '#2271b1' : '#135e96';
					return '<span style="display:inline-block; padding:2px 8px; border-radius:4px; background:#f0f6fc; color:' . esc_attr( $badge_color ) . '; font-weight:600; font-size:11px;">' . esc_html( ucfirst( $item['post_type'] ) ) . '</span>';

				case 'modified':
					return esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['modified'] ) ) );

				case 'status':
					if ( ! empty( $item['model_mismatch'] ) ) {
						return '<span class="cptbot-pill" style="display:inline-flex; align-items:center; gap:4px; background:#fff3cd; color:#856404; border:1px solid #ffeeba; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;" title="' . esc_attr__( 'Trained with a different embedding model. Re-sync required.', 'autonomous-ai-chatbot' ) . '">⚠ ' . esc_html__( 'Model Changed', 'autonomous-ai-chatbot' ) . '</span>';
					} elseif ( ! empty( $item['db_mismatch'] ) ) {
						return '<span class="cptbot-pill" style="display:inline-flex; align-items:center; gap:4px; background:#fff3cd; color:#856404; border:1px solid #ffeeba; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;" title="' . esc_attr__( 'Trained with a different vector database. Re-sync required.', 'autonomous-ai-chatbot' ) . '">⚠ ' . esc_html__( 'DB Changed', 'autonomous-ai-chatbot' ) . '</span>';
					} elseif ( ! empty( $item['is_synced'] ) && empty( $item['is_outdated'] ) ) {
						return '<span class="cptbot-pill" style="display:inline-flex; align-items:center; gap:4px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">✓ ' . esc_html__( 'Synced', 'autonomous-ai-chatbot' ) . '</span>';
					} elseif ( ! empty( $item['is_outdated'] ) ) {
						return '<span class="cptbot-pill" style="display:inline-flex; align-items:center; gap:4px; background:#fff3cd; color:#856404; border:1px solid #ffeeba; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">⚠ ' . esc_html__( 'Modified', 'autonomous-ai-chatbot' ) . '</span>';
					}
					return '<span class="cptbot-pill" style="display:inline-flex; align-items:center; gap:4px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">• ' . esc_html__( 'Not Synced', 'autonomous-ai-chatbot' ) . '</span>';

				case 'action':
					$label = ( ! empty( $item['is_synced'] ) ) ? esc_html__( 'Re-sync', 'autonomous-ai-chatbot' ) : esc_html__( 'Sync Now', 'autonomous-ai-chatbot' );
					return '<button type="button" class="button button-small cptbot-table-sync-single" data-post-id="' . esc_attr( $post_id ) . '">' . $label . '</button>';

				default:
					return '';
			}
		}

		/**
		 * Table extra navigation filters.
		 *
		 * @param string $which Top or bottom.
		 */
		public function extra_tablenav( $which ) {
			if ( 'top' !== $which ) {
				return;
			}

			$current_type   = filter_input( INPUT_GET, 'post_type_filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$current_status = filter_input( INPUT_GET, 'sync_status_filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			?>
			<div class="alignleft actions cptbot-training-filters">
				<select name="post_type_filter">
					<option value=""><?php esc_html_e( 'All Post Types', 'autonomous-ai-chatbot' ); ?></option>
					<option value="post" <?php selected( $current_type, 'post' ); ?>><?php esc_html_e( 'Posts Only', 'autonomous-ai-chatbot' ); ?></option>
					<option value="page" <?php selected( $current_type, 'page' ); ?>><?php esc_html_e( 'Pages Only', 'autonomous-ai-chatbot' ); ?></option>
				</select>
				<select name="sync_status_filter">
					<option value=""><?php esc_html_e( 'All Sync Statuses', 'autonomous-ai-chatbot' ); ?></option>
					<option value="synced" <?php selected( $current_status, 'synced' ); ?>><?php esc_html_e( 'Synced Only', 'autonomous-ai-chatbot' ); ?></option>
					<option value="unsynced" <?php selected( $current_status, 'unsynced' ); ?>><?php esc_html_e( 'Unsynced Only', 'autonomous-ai-chatbot' ); ?></option>
				</select>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'autonomous-ai-chatbot' ); ?>" />
			</div>
			<?php
		}
	}
}

$wcaichatbot_helper = new Wc_Cptbot_Helper();
$wcaichatbot_counts = $wcaichatbot_helper->wc_cptbot_get_posts_pages_count();

$wcaichatbot_model_type  = get_option( 'cpt_bot_model_type', 'onnx' );
$wcaichatbot_model_label = '';
switch ( $wcaichatbot_model_type ) {
	case 'onnx':
		$wcaichatbot_model_label = esc_html__( 'E₅ Small ONNX Model', 'autonomous-ai-chatbot' );
		break;
	case 'gemini':
		$wcaichatbot_model_label = esc_html__( 'Gemini Embedding Model', 'autonomous-ai-chatbot' );
		break;
	case 'openai':
		$wcaichatbot_model_label = esc_html__( 'OpenAI Embedding Model', 'autonomous-ai-chatbot' );
		break;
	default:
		$wcaichatbot_model_label = ucfirst( $wcaichatbot_model_type );
}

$wcaichatbot_vector_db = get_option( 'wc_cptbot_vector_db', 'pinecone' );
$wcaichatbot_db_configured = false;
$wcaichatbot_db_info_label = '';

if ( 'chromadb' === $wcaichatbot_vector_db ) {
	$wcaichatbot_chroma_base = get_option( 'wc_cptbot_chroma_base_url' );
	$wcaichatbot_chroma_col  = get_option( 'wc_cptbot_chroma_collection' );
	if ( ! empty( $wcaichatbot_chroma_base ) && ! empty( $wcaichatbot_chroma_col ) ) {
		$wcaichatbot_db_configured = true;
		$wcaichatbot_db_info_label = 'ChromaDB (' . esc_html( $wcaichatbot_chroma_col ) . ')';
	} else {
		$wcaichatbot_db_info_label = esc_html__( 'ChromaDB (Not Configured)', 'autonomous-ai-chatbot' );
	}
} else {
	$wcaichatbot_pinecone_key  = get_option( 'wc_cptbot_pinecone_key' );
	$wcaichatbot_pinecone_host = get_option( 'wc_cptbot_pinecone_index_host' );
	if ( empty( $wcaichatbot_pinecone_host ) ) {
		$wcaichatbot_pinecone_host = get_option( 'wc_cptbot_pinecone_host' );
	}
	if ( ! empty( $wcaichatbot_pinecone_key ) && ! empty( $wcaichatbot_pinecone_host ) ) {
		$wcaichatbot_index_name_label = get_option( 'wc_cptbot_pinecone_indexname' );
		if ( empty( $wcaichatbot_index_name_label ) ) {
			$wcaichatbot_index_name_label = get_option( 'wc_cptbot_pinecone_index_name', 'default' );
		}
		$wcaichatbot_db_configured = true;
		$wcaichatbot_db_info_label = 'Pinecone (' . esc_html( $wcaichatbot_index_name_label ) . ')';
	} else {
		$wcaichatbot_db_info_label = esc_html__( 'Pinecone (Not Configured)', 'autonomous-ai-chatbot' );
	}
}

$wcaichatbot_table = new Wc_Cptbot_Posts_Pages_Training_Table();
$wcaichatbot_table->prepare_items();
?>

<div class="wc-pos-wrapper cptbot-training-page-wrapper" style="margin-top: 15px;">
	<div class="wc-pos-container cptbot-training-container">

		<!-- Summary Metric Cards -->
		<div class="cptbot-training-stats-grid">
			<div class="cptbot-training-stat-card">
				<span class="cptbot-stat-title"><?php esc_html_e( 'Total Posts and Pages', 'autonomous-ai-chatbot' ); ?></span>
				<div class="cptbot-stat-val">
					<?php echo esc_html( $wcaichatbot_counts['total'] ); ?>
				</div>
				<span class="cptbot-stat-sub">
					<?php
					/* translators: 1: number of posts, 2: number of pages */
					printf( esc_html__( 'Posts: %1$d | Pages: %2$d', 'autonomous-ai-chatbot' ), (int) $wcaichatbot_counts['posts'], (int) $wcaichatbot_counts['pages'] );
					?>
				</span>
			</div>

			<div class="cptbot-training-stat-card">
				<span class="cptbot-stat-title"><?php esc_html_e( 'Synced to Vector DB', 'autonomous-ai-chatbot' ); ?></span>
				<div class="cptbot-stat-val cptbot-stat-val-green" id="cptbot-synced-stat">
					<?php echo esc_html( $wcaichatbot_counts['synced'] ); ?> <span class="cptbot-stat-pct">(<?php echo esc_html( $wcaichatbot_counts['percent'] ); ?>%)</span>
				</div>
				<span class="cptbot-stat-sub" id="cptbot-unsynced-stat">
					<?php
					/* translators: %d: number of pending items */
					printf( esc_html__( 'Pending: %d items', 'autonomous-ai-chatbot' ), (int) $wcaichatbot_counts['unsynced'] );
					?>
				</span>
			</div>

			<div class="cptbot-training-stat-card">
				<span class="cptbot-stat-title"><?php esc_html_e( 'Active Embedding Engine', 'autonomous-ai-chatbot' ); ?></span>
				<div class="cptbot-stat-val cptbot-stat-val-engine">
					<?php echo esc_html( $wcaichatbot_model_label ); ?>
				</div>
				<span class="cptbot-stat-sub">
					<?php esc_html_e( 'Configured in Settings', 'autonomous-ai-chatbot' ); ?>
				</span>
			</div>

			<div class="cptbot-training-stat-card">
				<span class="cptbot-stat-title"><?php esc_html_e( 'Vector Database', 'autonomous-ai-chatbot' ); ?></span>
				<div class="cptbot-stat-val <?php echo $wcaichatbot_db_configured ? 'cptbot-stat-val-green' : 'cptbot-stat-val-red'; ?>">
					<?php echo $wcaichatbot_db_configured ? '✓ ' : '✗ '; ?><?php echo esc_html( $wcaichatbot_db_info_label ); ?>
				</div>
				<span class="cptbot-stat-sub">
					<?php echo $wcaichatbot_db_configured ? esc_html__( 'Ready for synchronization', 'autonomous-ai-chatbot' ) : esc_html__( 'Please configure API keys', 'autonomous-ai-chatbot' ); ?>
				</span>
			</div>
		</div>

		<!-- Sync Controls Box -->
		<div class="cptbot-training-sync-box">
			<div class="cptbot-training-sync-header">
				<div class="cptbot-training-sync-info">
					<h3><?php esc_html_e( 'Synchronize Knowledge Base', 'autonomous-ai-chatbot' ); ?></h3>
					<p>
						<?php esc_html_e( 'Train the AI chatbot with your website store policies, about us page, contact info, and blog posts.', 'autonomous-ai-chatbot' ); ?>
					</p>
				</div>
				<div class="cptbot-training-sync-actions">
					<button type="button" class="button button-primary" id="cptbot-sync-posts-pages-btn" <?php echo ! $wcaichatbot_db_configured ? 'disabled' : ''; ?>>
						<span id="cptbot-posts-btn-text"><?php esc_html_e( 'Synchronize All Posts and Pages', 'autonomous-ai-chatbot' ); ?></span>
						<span class="cptbot-posts-loader" id="cptbot-posts-btn-spinner" style="display: none; width: 13px; height: 13px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: cptbot-spin 0.8s linear infinite; vertical-align: middle; margin-left: 6px;"></span>
					</button>
					<button type="button" class="button" id="cptbot-reset-posts-pages-btn" style="color: #d63638; border-color: #d63638;">
						<?php esc_html_e( 'Reset Sync', 'autonomous-ai-chatbot' ); ?>
					</button>
				</div>
			</div>

			<script type="text/javascript">
			(function() {
				try {
					var raw = localStorage.getItem('cptbot_posts_pages_sync_state') || sessionStorage.getItem('cptbot_posts_pages_sync_state');
					if (raw) {
						var st = JSON.parse(raw);
						if (st && st.isSyncing && (!st.timestamp || (Date.now() - st.timestamp < 7200000))) {
							var btn = document.getElementById('cptbot-sync-posts-pages-btn');
							if (btn) btn.disabled = true;
							var btnText = document.getElementById('cptbot-posts-btn-text');
							if (btnText) btnText.textContent = '<?php echo esc_js( __( 'Synchronizing…', 'autonomous-ai-chatbot' ) ); ?>';
							var btnSpin = document.getElementById('cptbot-posts-btn-spinner');
							if (btnSpin) btnSpin.style.display = 'inline-block';
						}
					}
				} catch (e) {}
			})();
			</script>

			<!-- Progress Bar (Hidden by default unless sync active) -->
			<div id="cptbot-posts-progress-wrapper" style="display: none; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0f0f1;">
				<div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; margin-bottom: 6px;">
					<div style="display: inline-flex; align-items: center; gap: 8px;">
						<span class="cptbot-posts-loader" id="cptbot-posts-spinner" style="display: inline-block; width: 14px; height: 14px; border: 2px solid #2271b1; border-top-color: transparent; border-radius: 50%; animation: cptbot-spin 0.8s linear infinite; vertical-align: middle;"></span>
						<span id="cptbot-posts-progress-status"><?php esc_html_e( 'Synchronizing…', 'autonomous-ai-chatbot' ); ?></span>
					</div>
					<span id="cptbot-posts-progress-percent" style="font-weight: 600; color: #2271b1;">0%</span>
				</div>
				<progress id="cptbot-posts-progressbar" value="0" max="100" style="width: 100%; height: 16px;"></progress>
			</div>

			<script type="text/javascript">
			(function() {
				try {
					var raw = localStorage.getItem('cptbot_posts_pages_sync_state') || sessionStorage.getItem('cptbot_posts_pages_sync_state');
					if (raw) {
						var st = JSON.parse(raw);
						if (st && st.isSyncing && (!st.timestamp || (Date.now() - st.timestamp < 7200000))) {
							var el = document.getElementById('cptbot-posts-progress-wrapper');
							if (el) el.style.display = 'block';
							var pBar = document.getElementById('cptbot-posts-progressbar');
							var pPct = document.getElementById('cptbot-posts-progress-percent');
							if (pBar && typeof st.percent !== 'undefined') pBar.value = st.percent;
							if (pPct && typeof st.percent !== 'undefined') pPct.textContent = st.percent + '%';
						}
					}
				} catch (e) {}
			})();
			</script>

			<div id="cptbot-posts-sync-msg" style="margin-top: 12px; display: none;"></div>
		</div>

		<!-- Posts and Pages Table -->
		<div class="cptbot-training-table-wrapper">
			<form method="get">
				<input type="hidden" name="page" value="chatgpt-bot" />
				<input type="hidden" name="tab" value="training" />
				<input type="hidden" name="section" value="posts_pages" />
				<?php
				$wcaichatbot_table->display();
				?>
			</form>
		</div>

	</div>
</div>

<style type="text/css">
@keyframes cptbot-spin {
	0% { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}
.cptbot-posts-loader {
	box-sizing: border-box;
}

/* Ensure posts and pages table stays horizontal and scrollable on small screens */
.cptbot-training-page-wrapper .cptbot-training-table-wrapper {
	overflow-x: auto;
	-webkit-overflow-scrolling: touch;
	width: 100%;
	max-width: 100%;
}
@media screen and (max-width: 782px) {
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper {
		overflow-x: auto !important;
		-webkit-overflow-scrolling: touch !important;
		margin-top: 10px;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table {
		display: table !important;
		width: 100% !important;
		min-width: 680px !important;
		table-layout: auto !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table thead,
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tfoot {
		display: table-header-group !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tfoot {
		display: table-footer-group !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tbody {
		display: table-row-group !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tr,
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tr:not(.inline-edit-row):not(.no-items) {
		display: table-row !important;
		position: static !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table thead th,
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tfoot th,
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tbody th,
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tbody td,
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tbody td:not(.column-primary):not(.check-column),
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tbody tr:not(.inline-edit-row):not(.no-items) td:not(.check-column) {
		display: table-cell !important;
		float: none !important;
		padding: 8px 10px !important;
		vertical-align: middle !important;
		width: auto !important;
		position: static !important;
		box-sizing: border-box !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table .column-primary ~ td:not(.check-column):before {
		display: none !important;
		content: none !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table .column-primary ~ td:not(.check-column) {
		padding: 8px 10px !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table .toggle-row {
		display: none !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tbody th.column-primary,
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table tbody td.column-primary {
		padding-right: 10px !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table .column-title {
		min-width: 180px !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table .column-post_type {
		min-width: 80px !important;
		width: 90px !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table .column-modified {
		min-width: 130px !important;
		width: 140px !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table .column-status {
		min-width: 130px !important;
		width: 140px !important;
		white-space: nowrap !important;
	}
	.cptbot-training-page-wrapper .cptbot-training-table-wrapper .wp-list-table .column-action {
		min-width: 100px !important;
		width: 110px !important;
		white-space: nowrap !important;
	}
}
</style>

<script type="text/javascript">
(function($) {
	$(document).ready(function() {
		var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		var nonce   = '<?php echo esc_js( wp_create_nonce( 'ajaxnonce' ) ); ?>';
		var isSyncing = false;
		var totalCount  = <?php echo (int) $wcaichatbot_counts['total']; ?>;
		var syncedCount = <?php echo (int) $wcaichatbot_counts['synced']; ?>;

		function updateSyncedStats(newCounts) {
			if (newCounts && typeof newCounts.synced !== 'undefined') {
				syncedCount = parseInt(newCounts.synced, 10);
				if (typeof newCounts.total !== 'undefined') {
					totalCount = parseInt(newCounts.total, 10);
				}
			} else {
				syncedCount++;
				if (syncedCount > totalCount) {
					syncedCount = totalCount;
				}
			}
			var pct = totalCount > 0 ? Math.min(100, Math.round((syncedCount / totalCount) * 100)) : 100;
			var unsynced = Math.max(0, totalCount - syncedCount);
			$('#cptbot-synced-stat').html(syncedCount + ' <span style="font-size: 16px; font-weight: normal; color: #646970;">(' + pct + '%)</span>');
			<?php
			/* translators: %d: number of pending items */
			$wcaichatbot_pending_text = __( 'Pending: %d items', 'autonomous-ai-chatbot' );
			?>
			var pendingTemplate = '<?php echo esc_js( $wcaichatbot_pending_text ); ?>';
			$('#cptbot-unsynced-stat').text(pendingTemplate.replace('%d', unsynced));
			if (window.wc_cptbot) {
				window.wc_cptbot.posts_pages_trained_count = syncedCount;
				window.wc_cptbot.has_trained_data = (syncedCount > 0);
			}
		}

		function showPostPageNotice(message, type) {
			type = type || 'error';
			var $msg = $('#cptbot-posts-sync-msg');
			var noticeHtml = '<div class="notice notice-' + type + ' is-dismissible" style="display:block; margin: 12px 0 6px 0; padding: 10px 14px;">' +
				'<p style="margin: 0.25em 0; font-size: 13px;">' + message + '</p>' +
				'<button type="button" class="notice-dismiss" style="position:relative; float:right; padding:9px; margin-top:-24px;"><span class="screen-reader-text"><?php echo esc_js( __( 'Dismiss this notice.', 'autonomous-ai-chatbot' ) ); ?></span></button>' +
				'</div>';
			$msg.html(noticeHtml).slideDown();
			$msg.find('.notice-dismiss').on('click', function(ev) {
				ev.preventDefault();
				$msg.slideUp();
			});
		}

		// Helper to ensure ONNX model session is ready
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

		// 1-Click Sync from list table
		$(document).on('click', '.cptbot-table-sync-single', function(e) {
			e.preventDefault();
			if (isSyncing) {
				showPostPageNotice('<?php echo esc_js( __( 'Batch synchronization is currently in progress. Please wait for it to finish.', 'autonomous-ai-chatbot' ) ); ?>', 'error');
				return;
			}
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var $row = $btn.closest('tr');
			var statusText = $row.find('td.column-status').text() || '';
			var wasAlreadySynced = statusText.indexOf('Synced') !== -1 && statusText.indexOf('Not') === -1 && statusText.indexOf('Changed') === -1 && statusText.indexOf('Modified') === -1;

			$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Syncing…', 'autonomous-ai-chatbot' ) ); ?>');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wc_cptbot_sync_single_post',
					nonce: nonce,
					post_id: postId
				},
				success: async function(res) {
					if (res && res.success) {
						var data = res.data;
						if (data && data.needs_onnx) {
							var curModel = '<?php echo esc_js( $wcaichatbot_model_type ); ?>';
							if (curModel === 'onnx') {
								$btn.text('<?php echo esc_js( __( 'Loading AI…', 'autonomous-ai-chatbot' ) ); ?>');
								var isReady = await ensureOnnxReady();
								if (!isReady) {
									showPostPageNotice('<?php echo esc_js( __( 'ONNX AI model failed to initialize in browser. Please check browser console or refresh.', 'autonomous-ai-chatbot' ) ); ?>', 'error');
									$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Retry', 'autonomous-ai-chatbot' ) ); ?>');
									return;
								}
							}

							$btn.text('<?php echo esc_js( __( 'Embedding…', 'autonomous-ai-chatbot' ) ); ?>');
							var onnxRes = await window.wc_cptbot_upsert_post_page_onnx(postId, data.training_text, true);

							if (onnxRes && (onnxRes.success || (onnxRes.data && onnxRes.data.success))) {
								$btn.text('<?php echo esc_js( __( '✓ Synced', 'autonomous-ai-chatbot' ) ); ?>');
								$row.find('td.column-status').html('<span class="cptbot-pill" style="display:inline-flex; align-items:center; gap:4px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">✓ <?php echo esc_js( __( 'Synced', 'autonomous-ai-chatbot' ) ); ?></span>');

								var freshCounts = (onnxRes.data && onnxRes.data.counts) ? onnxRes.data.counts : (onnxRes.counts ? onnxRes.counts : null);
								if (freshCounts) {
									updateSyncedStats(freshCounts);
								} else if (!wasAlreadySynced) {
									updateSyncedStats();
								}

								setTimeout(function() {
									$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Re-sync', 'autonomous-ai-chatbot' ) ); ?>');
								}, 800);
							} else {
								var err = (onnxRes && onnxRes.error) ? onnxRes.error : ((onnxRes && onnxRes.data && onnxRes.data.message) ? onnxRes.data.message : '<?php echo esc_js( __( 'Vector upsert failed.', 'autonomous-ai-chatbot' ) ); ?>');
								showPostPageNotice(err, 'error');
								$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Retry', 'autonomous-ai-chatbot' ) ); ?>');
							}
						} else {
							// Server handled sync (Gemini/OpenAI)
							$btn.text('<?php echo esc_js( __( '✓ Synced', 'autonomous-ai-chatbot' ) ); ?>');
							$row.find('td.column-status').html('<span class="cptbot-pill" style="display:inline-flex; align-items:center; gap:4px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">✓ <?php echo esc_js( __( 'Synced', 'autonomous-ai-chatbot' ) ); ?></span>');

							var freshCounts = (data && data.counts) ? data.counts : null;
							if (freshCounts) {
								updateSyncedStats(freshCounts);
							} else if (!wasAlreadySynced) {
								updateSyncedStats();
							}

							setTimeout(function() {
								$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Re-sync', 'autonomous-ai-chatbot' ) ); ?>');
							}, 800);
						}
					} else {
						var msg = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Sync failed', 'autonomous-ai-chatbot' ) ); ?>';
						showPostPageNotice(msg, 'error');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Retry', 'autonomous-ai-chatbot' ) ); ?>');
					}
				},
				error: function(xhr, status, error) {
					showPostPageNotice('<?php echo esc_js( __( 'AJAX error: ', 'autonomous-ai-chatbot' ) ); ?>' + error, 'error');
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Retry', 'autonomous-ai-chatbot' ) ); ?>');
				}
			});
		});

		var CHECKPOINT_KEY = 'cptbot_posts_pages_sync_state';

		function savePostsSyncCheckpoint(state) {
			try {
				var data = JSON.stringify(state);
				localStorage.setItem(CHECKPOINT_KEY, data);
				sessionStorage.setItem(CHECKPOINT_KEY, data);
			} catch (e) {}
		}

		function getPostsSyncCheckpoint() {
			try {
				var raw = localStorage.getItem(CHECKPOINT_KEY) || sessionStorage.getItem(CHECKPOINT_KEY);
				if (!raw) return null;
				var data = JSON.parse(raw);
				// Expire checkpoints older than 2 hours to avoid stale auto-starts
				if (data && data.timestamp && (Date.now() - data.timestamp > 7200000)) {
					clearPostsSyncCheckpoint();
					return null;
				}
				return data;
			} catch (e) {
				return null;
			}
		}

		function clearPostsSyncCheckpoint() {
			try {
				localStorage.removeItem(CHECKPOINT_KEY);
				sessionStorage.removeItem(CHECKPOINT_KEY);
			} catch (e) {}
		}

		function setSyncUIActive(active, buttonText) {
			var $btn = $('#cptbot-sync-posts-pages-btn');
			var $btnText = $('#cptbot-posts-btn-text');
			var $btnSpinner = $('#cptbot-posts-btn-spinner');
			var $barSpinner = $('#cptbot-posts-spinner');
			var $wrapper = $('#cptbot-posts-progress-wrapper');

			if (active) {
				isSyncing = true;
				$btn.prop('disabled', true);
				if (buttonText) {
					$btnText.text(buttonText);
				}
				$btnSpinner.show();
				$barSpinner.show();
				$wrapper.slideDown(200);
			} else {
				isSyncing = false;
				$btn.prop('disabled', <?php echo ! $wcaichatbot_db_configured ? 'true' : 'false'; ?>);
				$btnText.text('<?php echo esc_js( __( 'Synchronize All Posts and Pages', 'autonomous-ai-chatbot' ) ); ?>');
				$btnSpinner.hide();
				$barSpinner.hide();
			}
		}

		async function startBatchSync(isResume, startOffset) {
			if (isSyncing && !isResume) return;

			var modelType = '<?php echo esc_js( $wcaichatbot_model_type ); ?>';
			var limit     = 5;
			var offset    = typeof startOffset === 'number' ? startOffset : 0;
			totalCount    = <?php echo (int) $wcaichatbot_counts['total']; ?>;
			syncedCount   = <?php echo (int) $wcaichatbot_counts['synced']; ?>;

			setSyncUIActive(true, '<?php echo esc_js( __( 'Synchronizing…', 'autonomous-ai-chatbot' ) ); ?>');
			$('#cptbot-posts-sync-msg').hide().removeClass('notice notice-error notice-success');

			var initialPct = totalCount > 0 ? Math.min(100, Math.round((syncedCount / totalCount) * 100)) : 0;
			$('#cptbot-posts-progressbar').val(initialPct);
			$('#cptbot-posts-progress-percent').text(initialPct + '%');

			savePostsSyncCheckpoint({
				isSyncing: true,
				modelType: modelType,
				offset: offset,
				limit: limit,
				totalCount: totalCount,
				syncedCount: syncedCount,
				percent: initialPct,
				timestamp: Date.now()
			});

			if (totalCount === 0) {
				finishBatchSync();
				return;
			}

			if (modelType === 'onnx' || modelType === 'ternlight') {
				function runClientBatch() {
					if (!isSyncing) return;

					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'wc_cptbot_get_posts_pages_data',
							nonce: nonce,
							limit: limit,
							offset: offset,
							type: 'all'
						},
						success: async function(res) {
							if (!isSyncing) return;

							if (res && res.success && res.data) {
								var items = res.data.data;
								var postIds = Object.keys(items);

								if (postIds.length === 0) {
									finishBatchSync();
									return;
								}

								for (var i = 0; i < postIds.length; i++) {
									if (!isSyncing) return;

									var pId = postIds[i];
									var pText = items[pId];
									$('#cptbot-posts-progress-status').text(
										'<?php echo esc_js( __( 'Vectorizing Post/Page #', 'autonomous-ai-chatbot' ) ); ?>' + pId + ' (' + (offset + i + 1) + '/' + totalCount + ')'
									);

									var upsertRes = await window.wc_cptbot_upsert_post_page_onnx(pId, pText, true);
									if (upsertRes && (upsertRes.success || (upsertRes.data && upsertRes.data.success))) {
										var bCounts = (upsertRes.data && upsertRes.data.counts) ? upsertRes.data.counts : null;
										updateSyncedStats(bCounts);
										var pct = totalCount > 0 ? Math.min(100, Math.round((syncedCount / totalCount) * 100)) : 100;
										$('#cptbot-posts-progressbar').val(pct);
										$('#cptbot-posts-progress-percent').text(pct + '%');

										// Live update row pill in visible table if present
										var $rowBtn = $('.cptbot-table-sync-single[data-post-id="' + pId + '"]');
										if ($rowBtn.length) {
											$rowBtn.text('<?php echo esc_js( __( '✓ Synced', 'autonomous-ai-chatbot' ) ); ?>');
											$rowBtn.closest('tr').find('td.column-status').html('<span class="cptbot-pill" style="display:inline-flex; align-items:center; gap:4px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:600;">✓ <?php echo esc_js( __( 'Synced', 'autonomous-ai-chatbot' ) ); ?></span>');
										}

										// Update checkpoint
										savePostsSyncCheckpoint({
											isSyncing: true,
											modelType: modelType,
											offset: offset,
											limit: limit,
											totalCount: totalCount,
											syncedCount: syncedCount,
											percent: pct,
											timestamp: Date.now()
										});
									}
								}

								offset += limit;
								// Update checkpoint with new offset
								savePostsSyncCheckpoint({
									isSyncing: true,
									modelType: modelType,
									offset: offset,
									limit: limit,
									totalCount: totalCount,
									syncedCount: syncedCount,
									percent: totalCount > 0 ? Math.min(100, Math.round((syncedCount / totalCount) * 100)) : 100,
									timestamp: Date.now()
								});

								if (offset < totalCount && !res.data.complete) {
									runClientBatch();
								} else {
									finishBatchSync();
								}
							} else {
								finishBatchSync();
							}
						},
						error: function(xhr, status, error) {
							clearPostsSyncCheckpoint();
							setSyncUIActive(false);
							$('#cptbot-posts-sync-msg')
								.addClass('notice notice-error')
								.html('<p><?php echo esc_js( __( 'Batch fetch failed: ', 'autonomous-ai-chatbot' ) ); ?>' + error + '</p>')
								.show();
						}
					});
				}

				if (modelType === 'onnx') {
					$('#cptbot-posts-progress-status').text('<?php echo esc_js( __( 'Checking ONNX embedding engine…', 'autonomous-ai-chatbot' ) ); ?>');
					var isReady = await ensureOnnxReady();
					if (!isReady) {
						clearPostsSyncCheckpoint();
						setSyncUIActive(false);
						$('#cptbot-posts-sync-msg')
							.addClass('notice notice-error')
							.html('<p><?php echo esc_js( __( 'ONNX AI model could not be initialized in your browser. Please check browser console or try reloading.', 'autonomous-ai-chatbot' ) ); ?></p>')
							.show();
						return;
					}
					runClientBatch();
				} else {
					// Ternlight browser embedding
					$('#cptbot-posts-progress-status').text('<?php echo esc_js( __( 'Vectorizing with Ternlight…', 'autonomous-ai-chatbot' ) ); ?>');
					runClientBatch();
				}
			} else {
				// Server-side model (Gemini / OpenAI)
				function runServerBatch() {
					if (!isSyncing) return;

					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'wc_cptbot_sync_posts_pages',
							nonce: nonce,
							limit: limit,
							offset: offset,
							force: true
						},
						success: function(res) {
							if (!isSyncing) return;

							if (res && res.success) {
								var data = res.data;
								var pct = data.percent || 100;
								if (typeof data.trained_count !== 'undefined') {
									updateSyncedStats({ synced: data.trained_count, total: data.total_count });
								}
								$('#cptbot-posts-progressbar').val(pct);
								$('#cptbot-posts-progress-percent').text(pct + '%');
								$('#cptbot-posts-progress-status').text(
									'<?php echo esc_js( __( 'Synchronized', 'autonomous-ai-chatbot' ) ); ?> ' + data.trained_count + ' / ' + data.total_count
								);

								offset += limit;

								// Update checkpoint with new progress
								savePostsSyncCheckpoint({
									isSyncing: true,
									modelType: modelType,
									offset: offset,
									limit: limit,
									totalCount: data.total_count || totalCount,
									syncedCount: data.trained_count || syncedCount,
									percent: pct,
									timestamp: Date.now()
								});

								if (!data.complete && data.unsynced_count > 0 && offset < (data.total_count || totalCount)) {
									runServerBatch();
								} else {
									finishBatchSync();
								}
							} else {
								clearPostsSyncCheckpoint();
								setSyncUIActive(false);
								var err = (res && res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Batch synchronization error', 'autonomous-ai-chatbot' ) ); ?>';
								$('#cptbot-posts-sync-msg')
									.addClass('notice notice-error')
									.html('<p>' + err + '</p>')
									.show();
							}
						},
						error: function(xhr, status, error) {
							clearPostsSyncCheckpoint();
							setSyncUIActive(false);
							$('#cptbot-posts-sync-msg')
								.addClass('notice notice-error')
								.html('<p><?php echo esc_js( __( 'AJAX error: ', 'autonomous-ai-chatbot' ) ); ?>' + error + '</p>')
								.show();
						}
					});
				}

				runServerBatch();
			}
		}

		function finishBatchSync() {
			clearPostsSyncCheckpoint();
			setSyncUIActive(false);
			$('#cptbot-posts-progressbar').val(100);
			$('#cptbot-posts-progress-percent').text('100%');
			$('#cptbot-posts-progress-status').text('<?php echo esc_js( __( 'Synchronization Completed!', 'autonomous-ai-chatbot' ) ); ?>');
			showPostPageNotice('<?php echo esc_js( __( 'All Posts and Pages synchronized successfully!', 'autonomous-ai-chatbot' ) ); ?>', 'success');
			setTimeout(function() {
				location.reload();
			}, 1200);
		}

		// Auto-resume sync on page load / refresh if checkpoint exists
		var syncCheckpoint = getPostsSyncCheckpoint();
		if (syncCheckpoint && syncCheckpoint.isSyncing) {
			var isAlreadyComplete = (syncedCount >= totalCount && totalCount > 0);
			if (isAlreadyComplete) {
				clearPostsSyncCheckpoint();
			} else {
				var resumeOffset = typeof syncCheckpoint.offset === 'number' ? syncCheckpoint.offset : 0;
				if (resumeOffset >= totalCount && totalCount > 0) {
					resumeOffset = 0;
				}

				// Restore progress values immediately
				if (typeof syncCheckpoint.percent !== 'undefined') {
					$('#cptbot-posts-progressbar').val(syncCheckpoint.percent);
					$('#cptbot-posts-progress-percent').text(syncCheckpoint.percent + '%');
				}
				$('#cptbot-posts-progress-status').text(
					'<?php echo esc_js( __( 'Resuming synchronization…', 'autonomous-ai-chatbot' ) ); ?> ' + syncedCount + ' / ' + totalCount
				);

				setTimeout(function() {
					startBatchSync(true, resumeOffset);
				}, 600);
			}
		}

		// Batch Sync Posts & Pages button click
		$('#cptbot-sync-posts-pages-btn').on('click', function(e) {
			e.preventDefault();
			if (isSyncing) return;
			startBatchSync(false, 0);
		});

		// Reset Sync button with modal popup
		$('#cptbot-reset-posts-pages-btn').on('click', function(e) {
			e.preventDefault();

			var modalId = 'cptbot-reset-posts-pages-modal';
			$('#' + modalId).remove();

			var modalHtml = '<div id="' + modalId + '" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; display: flex; justify-content: center; align-items: center;">' +
				'<div style="background: #fff; padding: 30px; border-radius: 8px; max-width: 480px; width: 90%; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">' +
				'<h2 style="margin-top: 0; color: #d63638; font-size: 20px;">⚠️ <?php echo esc_js( __( 'Reset Confirmation', 'autonomous-ai-chatbot' ) ); ?></h2>' +
				'<p style="font-size: 15px; line-height: 1.5; margin: 18px 0; color: #3c434a;">' +
				'<?php echo esc_js( __( 'Are you sure you want to reset Posts and Pages synchronization data?', 'autonomous-ai-chatbot' ) ); ?>' +
				'</p>' +
				'<div style="margin-top: 25px; display: flex; justify-content: center; gap: 14px;">' +
				'<button type="button" id="cptbot-confirm-reset-posts-btn" class="button button-primary button-large" style="background-color: #d63638; border-color: #d63638;"><?php echo esc_js( __( 'Yes, Delete & Proceed', 'autonomous-ai-chatbot' ) ); ?></button>' +
				'<button type="button" id="cptbot-cancel-reset-posts-btn" class="button button-large"><?php echo esc_js( __( 'Cancel', 'autonomous-ai-chatbot' ) ); ?></button>' +
				'</div>' +
				'</div>' +
				'</div>';

			$('body').append(modalHtml);

			$('#cptbot-cancel-reset-posts-btn').on('click', function() {
				$('#' + modalId).remove();
			});

			$('#cptbot-confirm-reset-posts-btn').on('click', function() {
				var $confirmBtn = $(this);
				$confirmBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Processing…', 'autonomous-ai-chatbot' ) ); ?>');

				clearPostsSyncCheckpoint();

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'wc_cptbot_reset_posts_pages_sync',
						nonce: nonce
					},
					success: function(res) {
						$('#' + modalId).remove();
						showPostPageNotice('<?php echo esc_js( __( 'Posts and Pages sync reset successfully!', 'autonomous-ai-chatbot' ) ); ?>', 'success');
						setTimeout(function() {
							location.reload();
						}, 900);
					},
					error: function() {
						$('#' + modalId).remove();
						showPostPageNotice('<?php echo esc_js( __( 'Failed to reset sync. Please try again.', 'autonomous-ai-chatbot' ) ); ?>', 'error');
					}
				});
			});
		});
	});
})(jQuery);
</script>

