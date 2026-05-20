<?php
/**
 * Activity & Debug Log admin page.
 *
 * Tab 1 — Activity Log: every SEO change recorded in the DB (who, what, when, status).
 * Tab 2 — Debug Log: last N lines of the file-based logger output.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Activity_Log_Page {

	/** @var SEO_Agent_AI_Activity_Log */
	private $activity_log;

	/** @var SEO_Agent_AI_Logger */
	private $logger;

	public function __construct(
		SEO_Agent_AI_Activity_Log $activity_log,
		SEO_Agent_AI_Logger $logger
	) {
		$this->activity_log = $activity_log;
		$this->logger       = $logger;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'activity'; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap sai-page">
			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow"><span class="sai-dot"></span><?php esc_html_e( 'SEO Agent AI', 'seo-agent-ai' ); ?></p>
					<h1 class="sai-header-title"><?php esc_html_e( 'Audit &amp; Debug Log', 'seo-agent-ai' ); ?></h1>
				</div>
				<div class="sai-header-actions">
					<nav class="sai-nav">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-log&tab=activity' ) ); ?>"
							class="sai-nav-tab<?php echo 'activity' === $tab ? ' active' : ''; ?>">
							<?php esc_html_e( 'Activity Log', 'seo-agent-ai' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-log&tab=debug' ) ); ?>"
							class="sai-nav-tab<?php echo 'debug' === $tab ? ' active' : ''; ?>">
							<?php esc_html_e( 'Debug Log', 'seo-agent-ai' ); ?>
						</a>
					</nav>
				</div>
			</div>

			<div class="sai-body">
				<?php
				if ( 'debug' === $tab ) {
					$this->render_debug_tab();
				} else {
					$this->render_activity_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------
	// Activity tab
	// -------------------------------------------------------------------

	private function render_activity_tab() {
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$per_page = 30;
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$trigger  = isset( $_GET['trigger'] ) ? sanitize_key( $_GET['trigger'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$filters = array();
		if ( $status !== '' ) {
			$filters['status'] = $status;
		}
		if ( $trigger !== '' ) {
			$filters['triggered_by'] = $trigger;
		}

		$entries = $this->activity_log->get_entries( $filters, $paged, $per_page );
		$total   = $this->activity_log->get_count( $filters );

		// Filter bar.
		echo '<form method="get" class="sai-filters" style="margin-bottom:16px">';
		echo '<input type="hidden" name="page" value="seo-agent-log">';
		echo '<input type="hidden" name="tab" value="activity">';

		echo '<label>';
		echo '<select name="status">';
		echo '<option value=""' . selected( $status, '', false ) . '>' . esc_html__( 'All Statuses', 'seo-agent-ai' ) . '</option>';
		echo '<option value="applied"' . selected( $status, 'applied', false ) . '>' . esc_html__( 'Applied', 'seo-agent-ai' ) . '</option>';
		echo '<option value="rolled_back"' . selected( $status, 'rolled_back', false ) . '>' . esc_html__( 'Rolled Back', 'seo-agent-ai' ) . '</option>';
		echo '<option value="skipped"' . selected( $status, 'skipped', false ) . '>' . esc_html__( 'Skipped', 'seo-agent-ai' ) . '</option>';
		echo '</select></label>';

		echo '<label>';
		echo '<select name="trigger">';
		echo '<option value=""' . selected( $trigger, '', false ) . '>' . esc_html__( 'All Triggers', 'seo-agent-ai' ) . '</option>';
		echo '<option value="autopilot"' . selected( $trigger, 'autopilot', false ) . '>' . esc_html__( 'Autopilot', 'seo-agent-ai' ) . '</option>';
		echo '<option value="manual"' . selected( $trigger, 'manual', false ) . '>' . esc_html__( 'Manual', 'seo-agent-ai' ) . '</option>';
		echo '<option value="rollback"' . selected( $trigger, 'rollback', false ) . '>' . esc_html__( 'Rollback', 'seo-agent-ai' ) . '</option>';
		echo '</select></label>';

		echo '<button type="submit" class="sai-btn sai-btn-ghost sai-btn-sm"><span class="btn-label">' . esc_html__( 'Filter', 'seo-agent-ai' ) . '</span></button>';
		echo '</form>';

		if ( empty( $entries ) ) {
			echo '<div class="sai-empty">';
			echo '<div class="sai-empty-icon">&#128203;</div>';
			echo '<h3>' . esc_html__( 'No activity yet', 'seo-agent-ai' ) . '</h3>';
			echo '<p>' . esc_html__( 'Changes made by SEO Agent AI will appear here.', 'seo-agent-ai' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<div class="sai-timeline">';
		foreach ( $entries as $e ) {
			$post_id    = (int) $e['post_id'];
			$post       = $post_id ? get_post( $post_id ) : null;
			$post_title = $post instanceof WP_Post ? $post->post_title : ( $post_id ? "(#{$post_id})" : __( 'System', 'seo-agent-ai' ) );
			$edit_url   = $post instanceof WP_Post ? get_edit_post_link( $post_id ) : '';

			$change_type = (string) $e['change_type'];
			$icon_class  = 'default';
			if ( false !== strpos( $change_type, 'meta' ) ) {
				$icon_class = 't-meta';
			} elseif ( false !== strpos( $change_type, 'link' ) ) {
				$icon_class = 't-link';
			} elseif ( false !== strpos( $change_type, 'schema' ) ) {
				$icon_class = 't-schema';
			} elseif ( false !== strpos( $change_type, 'image' ) ) {
				$icon_class = 't-image';
			}

			echo '<div class="sai-timeline-item">';
			echo '<div class="sai-timeline-icon ' . esc_attr( $icon_class ) . '"></div>';
			echo '<div class="sai-timeline-body">';

			echo '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px">';
			if ( $edit_url ) {
				echo '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $post_title ) . '</a></strong>';
			} else {
				echo '<strong>' . esc_html( $post_title ) . '</strong>';
			}
			echo '<code style="font-size:11px">' . esc_html( $change_type ) . '</code>';
			echo '<span class="sai-badge b-neutral">' . esc_html( $e['field_changed'] ) . '</span>';
			echo '<span class="sai-status s-' . esc_attr( $e['status'] ) . '">' . esc_html( $e['status'] ) . '</span>';
			echo '<span class="sai-badge b-primary">' . esc_html( $e['triggered_by'] ) . '</span>';
			echo '</div>';

			if ( '' !== $e['value_before'] || '' !== $e['value_after'] ) {
				echo '<div class="sai-timeline-diff">';
				echo '<div class="sai-diff-before"><span class="sai-diff-label">' . esc_html__( 'Before', 'seo-agent-ai' ) . '</span>' . esc_html( wp_trim_words( $e['value_before'], 12, '…' ) ) . '</div>';
				echo '<div class="sai-diff-after"><span class="sai-diff-label">' . esc_html__( 'After', 'seo-agent-ai' ) . '</span>' . esc_html( wp_trim_words( $e['value_after'], 12, '…' ) ) . '</div>';
				echo '</div>';
			}

			echo '<div class="sai-timeline-meta">' . esc_html( $e['created_at'] ) . '</div>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';

		// Pagination.
		$pages = (int) ceil( $total / $per_page );
		if ( $pages > 1 ) {
			echo '<div class="sai-pagination" style="margin-top:16px">';
			$paginate = paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'type'      => 'array',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)
			);
			if ( is_array( $paginate ) ) {
				foreach ( $paginate as $link ) {
					echo wp_kses_post( '<span class="sai-page-btn">' . $link . '</span>' );
				}
			}
			echo '</div>';
		}

		echo '<p style="color:#787c82;margin-top:8px;font-size:12px">' .
			esc_html(
				sprintf(
				/* translators: %d: total entries */
					__( '%d total entries', 'seo-agent-ai' ),
					$total
				)
			) . '</p>';
	}

	// -------------------------------------------------------------------
	// Debug log tab
	// -------------------------------------------------------------------

	private function render_debug_tab() {
		$lines       = max( 50, min( 500, (int) ( $_GET['lines'] ?? 100 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$level       = isset( $_GET['level'] ) ? sanitize_key( $_GET['level'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$log_path    = $this->logger->get_log_path();
		$log_entries = $this->logger->tail( $lines, $level );

		echo '<div class="sai-card" style="margin-bottom:16px">';
		echo '<div class="sai-card-body">';
		echo '<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		echo '<input type="hidden" name="page" value="seo-agent-log">';
		echo '<input type="hidden" name="tab" value="debug">';

		echo '<label>';
		echo '<select name="level">';
		echo '<option value=""' . selected( $level, '', false ) . '>' . esc_html__( 'All Levels', 'seo-agent-ai' ) . '</option>';
		foreach ( array( 'ERROR', 'WARNING', 'INFO', 'DEBUG' ) as $l ) {
			echo '<option value="' . esc_attr( strtolower( $l ) ) . '"' . selected( $level, strtolower( $l ), false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';

		echo '<label style="font-size:13px">' . esc_html__( 'Lines:', 'seo-agent-ai' ) . ' ';
		echo '<select name="lines">';
		foreach ( array( 50, 100, 250, 500 ) as $n ) {
			echo '<option value="' . esc_attr( $n ) . '"' . selected( $lines, $n, false ) . '>' . esc_html( $n ) . '</option>';
		}
		echo '</select></label>';

		echo '<button type="submit" class="sai-btn sai-btn-ghost sai-btn-sm"><span class="btn-label">' . esc_html__( 'Apply', 'seo-agent-ai' ) . '</span></button>';
		echo '</form>';
		echo '</div></div>';

		if ( ! file_exists( $log_path ) ) {
			echo '<div class="sai-notice n-info"><p>' .
				esc_html__( 'No debug log file yet — it will appear here once SEO Agent AI processes its first cron or analysis.', 'seo-agent-ai' ) .
				'</p></div>';
			return;
		}

		echo '<p style="color:#787c82;font-size:12px;margin:0 0 8px">' .
			esc_html(
				sprintf(
				/* translators: 1: line count, 2: file path */
					__( 'Showing last %1$d lines from %2$s', 'seo-agent-ai' ),
					$lines,
					$log_path
				)
			) . '</p>';

		if ( empty( $log_entries ) ) {
			echo '<div class="sai-empty"><p>' . esc_html__( 'No log entries match the current filter.', 'seo-agent-ai' ) . '</p></div>';
			return;
		}

		echo '<div class="sai-card">';
		echo '<div class="sai-card-body">';
		echo '<pre class="sai-code" style="background:#1d2327;color:#c3c4c7;border-radius:4px;padding:12px 16px;overflow-x:auto;max-height:600px;overflow-y:auto;margin:0;font-size:12px;line-height:1.6;white-space:pre-wrap">';

		foreach ( array_reverse( $log_entries ) as $line ) {
			$line  = esc_html( $line );
			$color = '#c3c4c7';
			if ( false !== strpos( $line, '[ERROR]' ) ) {
				$color = '#f86368';
			} elseif ( false !== strpos( $line, '[WARNING]' ) ) {
				$color = '#f0c33c';
			} elseif ( false !== strpos( $line, '[INFO]' ) ) {
				$color = '#72aee6';
			} elseif ( false !== strpos( $line, '[DEBUG]' ) ) {
				$color = '#8c8f94';
			}
			echo '<span style="color:' . esc_attr( $color ) . '">' . $line . '</span>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $line is already esc_html'd above.
		}
		echo '</pre>';
		echo '</div></div>';

		echo '<p style="margin-top:8px">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=seo-agent-log&tab=debug&clear=1&_wpnonce=' . wp_create_nonce( 'seo_agent_ai_clear_log' ) ) ) . '" class="sai-btn sai-btn-danger sai-btn-sm" onclick="return confirm(\'' . esc_js( __( 'Clear the debug log file?', 'seo-agent-ai' ) ) . '\')">';
		echo '<span class="btn-label">' . esc_html__( 'Clear Log', 'seo-agent-ai' ) . '</span>';
		echo '</a>';
		echo '</p>';

		// Handle clear action.
		if ( ! empty( $_GET['clear'] ) && ! empty( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'seo_agent_ai_clear_log' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				@file_put_contents( $log_path, '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				wp_safe_redirect( admin_url( 'admin.php?page=seo-agent-log&tab=debug' ) );
				exit;
			}
		}
	}
}
