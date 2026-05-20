<?php
/**
 * Rollback Center admin page.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Rollback_Center_Page {

	/** @var SEO_Agent_AI_Fix_Executor */
	private $fix_executor;

	/** @var SEO_Agent_AI_Activity_Log */
	private $activity_log;

	public function __construct( SEO_Agent_AI_Fix_Executor $fix_executor, SEO_Agent_AI_Activity_Log $activity_log ) {
		$this->fix_executor = $fix_executor;
		$this->activity_log = $activity_log;
	}

	/**
	 * Handle rollback POST action.
	 */
	public function handle_rollback() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-agent-ai' ) );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'seo_agent_ai_rollback_' . $post_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'seo-agent-ai' ) );
		}

		$result = $this->fix_executor->rollback( $post_id );

		if ( is_wp_error( $result ) ) {
			$redirect = admin_url( 'admin.php?page=seo-agent-rollback&error=' . rawurlencode( $result->get_error_message() ) );
		} else {
			$redirect = admin_url( 'admin.php?page=seo-agent-rollback&rolled_back=' . $post_id );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'seo-agent-ai' ) );
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap sai-page">
			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow"><span class="sai-dot"></span><?php esc_html_e( 'SEO Agent AI', 'seo-agent-ai' ); ?></p>
					<h1 class="sai-header-title"><?php esc_html_e( 'Rollback Center', 'seo-agent-ai' ); ?></h1>
				</div>
			</div>

			<div class="sai-body">
				<?php if ( ! empty( $_GET['rolled_back'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					<?php $pid = (int) $_GET['rolled_back']; // phpcs:ignore WordPress.Security.NonceVerification ?>
					<div class="sai-notice n-success" style="margin-bottom:16px"><p><?php echo esc_html( sprintf( __( 'Post #%d rolled back successfully.', 'seo-agent-ai' ), $pid ) ); ?></p></div>
				<?php endif; ?>
				<?php if ( ! empty( $_GET['error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					<div class="sai-notice n-error" style="margin-bottom:16px"><p><?php echo esc_html( sanitize_text_field( urldecode( $_GET['error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
				<?php endif; ?>

				<div class="sai-notice n-warning" style="margin-bottom:20px">
					<p><strong><?php esc_html_e( 'Caution:', 'seo-agent-ai' ); ?></strong> <?php esc_html_e( 'Rolling back will restore a post\'s meta title and description to their last backup snapshot. This action cannot be undone.', 'seo-agent-ai' ); ?></p>
				</div>

				<form method="get" class="sai-filters" style="margin-bottom:16px">
					<input type="hidden" name="page" value="seo-agent-rollback">
					<div class="sai-search-wrap">
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by post title...', 'seo-agent-ai' ); ?>">
					</div>
					<button type="submit" class="sai-btn sai-btn-ghost sai-btn-sm"><span class="btn-label"><?php esc_html_e( 'Search', 'seo-agent-ai' ); ?></span></button>
				</form>

				<?php $this->render_activity_log( $search, $paged ); ?>
			</div>
		</div>
		<?php
	}

	private function render_activity_log( $search, $paged ) {
		$per_page = 20;

		// Build filters for the activity log. If searching by title, resolve post IDs first.
		$filters = array();
		if ( $search !== '' ) {
			$matching_posts = get_posts( array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'any',
				'posts_per_page' => 50,
				's'              => $search,
				'fields'         => 'ids',
			) );
			if ( empty( $matching_posts ) ) {
				echo '<div class="sai-empty"><p>' . esc_html__( 'No activity log entries found.', 'seo-agent-ai' ) . '</p></div>';
				return;
			}
			// Filter to first matching post ID (activity log supports singular post_id only).
			$filters['post_id'] = (int) $matching_posts[0];
		}

		$rows  = $this->activity_log->get_entries( $filters, $paged, $per_page );
		$total = $this->activity_log->get_count( $filters );

		if ( empty( $rows ) ) {
			echo '<div class="sai-empty">';
			echo '<div class="sai-empty-icon">&#128203;</div>';
			echo '<h3>' . esc_html__( 'No changes to roll back', 'seo-agent-ai' ) . '</h3>';
			echo '<p>' . esc_html__( 'No activity log entries found.', 'seo-agent-ai' ) . '</p>';
			echo '</div>';
			return;
		}

		// Track per-post backup availability.
		$backup_status = array();

		foreach ( $rows as $row ) {
			$post_id = (int) $row['post_id'];
			$post    = get_post( $post_id );
			$title   = $post instanceof WP_Post ? $post->post_title : "(#{$post_id})";

			// Check if rollback is available (backup exists).
			if ( ! isset( $backup_status[ $post_id ] ) ) {
				$preview                   = $this->fix_executor->rollback( $post_id, true );
				$backup_status[ $post_id ] = ! is_wp_error( $preview );
			}
			$has_backup = $backup_status[ $post_id ];

			echo '<div class="sai-rollback-item">';
			echo '<div class="sai-rollback-body">';
			echo '<div style="font-weight:600;margin-bottom:4px">';
			echo '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( $title ) . '</a>';
			echo ' <span class="sai-badge b-neutral">' . esc_html( $row['change_type'] ?? '' ) . '</span>';
			echo ' <span class="sai-badge b-primary">' . esc_html( $row['field_changed'] ?? '' ) . '</span>';
			echo '</div>';
			echo '<div style="font-size:12px;color:#787c82;margin-bottom:6px">' . esc_html( $row['created_at'] ?? '' ) . '</div>';

			// Diff.
			if ( ! empty( $row['value_before'] ) || ! empty( $row['value_after'] ) ) {
				echo '<div class="sai-timeline-diff">';
				echo '<div class="sai-diff-before"><span class="sai-diff-label">' . esc_html__( 'Before', 'seo-agent-ai' ) . '</span>' . esc_html( wp_trim_words( $row['value_before'] ?? '', 10 ) ) . '</div>';
				echo '<div class="sai-diff-after"><span class="sai-diff-label">' . esc_html__( 'After', 'seo-agent-ai' ) . '</span>' . esc_html( wp_trim_words( $row['value_after'] ?? '', 10 ) ) . '</div>';
				echo '</div>';
			}
			echo '</div>'; // .sai-rollback-body

			if ( $has_backup ) {
				$nonce = wp_create_nonce( 'seo_agent_ai_rollback_' . $post_id );
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Restore previous meta values for this post?', 'seo-agent-ai' ) ) . '\')">';
				echo '<input type="hidden" name="action" value="seo_agent_ai_rollback">';
				echo '<input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '">';
				echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">';
				echo '<button type="submit" class="sai-btn sai-btn-danger sai-btn-sm sai-rollback-btn"><span class="btn-label">' . esc_html__( 'Rollback', 'seo-agent-ai' ) . '</span></button>';
				echo '</form>';
			} else {
				echo '<span style="font-size:12px;color:#787c82">' . esc_html__( 'No backup', 'seo-agent-ai' ) . '</span>';
			}
			echo '</div>'; // .sai-rollback-item
		}

		// Pagination.
		$pages = (int) ceil( $total / $per_page );
		if ( $pages > 1 ) {
			echo '<div class="sai-pagination" style="margin-top:16px">';
			$paginate = paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $pages,
				'type'      => 'array',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			) );
			if ( is_array( $paginate ) ) {
				foreach ( $paginate as $link ) {
					echo wp_kses_post( '<span class="sai-page-btn">' . $link . '</span>' );
				}
			}
			echo '</div>';
		}
	}
}
