<?php
/**
 * Cron Status admin page — health check, last-run times, manual triggers.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Cron_Status_Page {

	/**
	 * All managed cron hooks with their schedule and description.
	 */
	private static function cron_hooks() {
		return array(
			'seo_agent_ai_daily_analysis' => array(
				'schedule'    => 'daily',
				'description' => __( 'Main daily analysis: fetch GSC/GA4, analyze posts, apply autopilot.', 'seo-agent-ai' ),
			),
			'seo_agent_fetch_gsc_data' => array(
				'schedule'    => 'daily',
				'description' => __( 'Dedicated GSC keyword history fetch → keyword_history table.', 'seo-agent-ai' ),
			),
			'seo_agent_fetch_ga4_data' => array(
				'schedule'    => 'daily',
				'description' => __( 'Dedicated GA4 engagement metrics fetch.', 'seo-agent-ai' ),
			),
			'seo_agent_generate_report' => array(
				'schedule'    => 'daily',
				'description' => __( 'Generate and store daily SEO report.', 'seo-agent-ai' ),
			),
			'seo_agent_score_pages' => array(
				'schedule'    => 'weekly',
				'description' => __( 'Run SEO scoring engine on all published posts.', 'seo-agent-ai' ),
			),
			'seo_agent_detect_decay' => array(
				'schedule'    => 'weekly',
				'description' => __( 'Content decay + freshness detection pass.', 'seo-agent-ai' ),
			),
			'seo_agent_run_internal_links' => array(
				'schedule'    => 'weekly',
				'description' => __( 'Internal link opportunity detection and insertion.', 'seo-agent-ai' ),
			),
			'seo_agent_purge_old_data' => array(
				'schedule'    => 'weekly',
				'description' => __( 'Purge keyword_history and page_insights rows beyond retention window.', 'seo-agent-ai' ),
			),
		);
	}

	/**
	 * Handle manual trigger POST action.
	 */
	public function handle_trigger() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-agent-ai' ) );
		}

		$hook = sanitize_key( $_POST['hook'] ?? '' );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'seo_agent_ai_trigger_' . $hook ) ) {
			wp_die( esc_html__( 'Security check failed.', 'seo-agent-ai' ) );
		}

		$allowed = array_keys( self::cron_hooks() );
		if ( ! in_array( $hook, $allowed, true ) ) {
			wp_die( esc_html__( 'Unknown hook.', 'seo-agent-ai' ) );
		}

		// Fire the event now.
		do_action( $hook );

		$redirect_page = sanitize_key( $_POST['redirect_page'] ?? 'seo-agent-cron' );
		$allowed_pages = array( 'seo-agent-cron', 'seo-agent-rankings' );
		if ( ! in_array( $redirect_page, $allowed_pages, true ) ) {
			$redirect_page = 'seo-agent-cron';
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page . '&triggered=' . rawurlencode( $hook ) ) );
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'seo-agent-ai' ) );
		}

		if ( ! empty( $_GET['triggered'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$hook = sanitize_key( $_GET['triggered'] ); // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="sai-notice n-success" style="margin-bottom:16px"><p>';
			echo esc_html( sprintf( __( 'Hook "%s" triggered manually.', 'seo-agent-ai' ), $hook ) );
			echo '</p></div>';
		}

		?>
		<div class="wrap sai-page">
			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow"><span class="sai-dot"></span><?php esc_html_e( 'SEO Agent AI', 'seo-agent-ai' ); ?></p>
					<h1 class="sai-header-title"><?php esc_html_e( 'Cron Status', 'seo-agent-ai' ); ?></h1>
				</div>
				<div class="sai-header-actions">
					<?php
					// Trigger-all: loop and emit one button that triggers the main daily analysis.
					$main_hook  = 'seo_agent_ai_daily_analysis';
					$main_nonce = wp_create_nonce( 'seo_agent_ai_trigger_' . $main_hook );
					?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="seo_agent_ai_trigger_cron">
						<input type="hidden" name="hook" value="<?php echo esc_attr( $main_hook ); ?>">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $main_nonce ); ?>">
						<button type="submit" class="sai-btn sai-btn-primary">
							<span class="btn-label"><?php esc_html_e( 'Run Daily Analysis Now', 'seo-agent-ai' ); ?></span>
						</button>
					</form>
				</div>
			</div>

			<div class="sai-body">
				<p class="description" style="margin-bottom:16px">
					<?php esc_html_e( 'All scheduled SEO Agent cron jobs. Use "Run Now" to trigger any job immediately.', 'seo-agent-ai' ); ?>
				</p>

				<div class="sai-cron-grid">
					<?php foreach ( self::cron_hooks() as $hook => $info ) : ?>
						<?php
						$next_run  = wp_next_scheduled( $hook );
						$last_run  = (string) get_option( 'seo_agent_ai_last_run_' . $hook, '' );
						$scheduled = $next_run !== false;

						// Determine indicator class.
						if ( ! $scheduled ) {
							$indicator = 'ci-miss';
						} elseif ( $next_run < time() - 3600 ) {
							$indicator = 'ci-late'; // overdue by more than an hour.
						} else {
							$indicator = 'ci-ok';
						}

						$next_str = $scheduled ? $this->human_time( $next_run ) : __( 'Not scheduled', 'seo-agent-ai' );
						$last_str = $last_run !== '' ? $last_run : __( 'Never', 'seo-agent-ai' );
						$nonce    = wp_create_nonce( 'seo_agent_ai_trigger_' . $hook );
						?>
						<div class="sai-cron-job">
							<div class="sai-cron-indicator <?php echo esc_attr( $indicator ); ?>"></div>
							<div class="sai-cron-body">
								<div class="sai-cron-name"><?php echo esc_html( $hook ); ?></div>
								<div class="sai-cron-next">
									<?php echo esc_html( $next_str ); ?>
									<?php if ( $last_run !== '' ) : ?>
										<span style="color:#787c82;font-size:11px"> &mdash; <?php echo esc_html__( 'Last:', 'seo-agent-ai' ) . ' ' . esc_html( $last_str ); ?></span>
									<?php endif; ?>
								</div>
								<div class="sai-cron-schedule"><?php echo esc_html( $info['schedule'] ); ?></div>
								<div style="margin-top:8px;font-size:12px;color:#787c82"><?php echo esc_html( $info['description'] ); ?></div>
							</div>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:auto">
								<input type="hidden" name="action" value="seo_agent_ai_trigger_cron">
								<input type="hidden" name="hook" value="<?php echo esc_attr( $hook ); ?>" data-cron-hook="<?php echo esc_attr( $hook ); ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
								<button type="submit" class="sai-btn sai-btn-ghost sai-btn-sm">
									<span class="btn-label"><?php esc_html_e( 'Run Now', 'seo-agent-ai' ); ?></span>
								</button>
							</form>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="sai-card" style="margin-top:24px">
					<div class="sai-card-header"><h2 class="sai-card-title"><?php esc_html_e( 'Queue Status', 'seo-agent-ai' ); ?></h2></div>
					<div class="sai-card-body">
						<?php $this->render_queue_status(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_queue_status() {
		$raw   = get_option( SEO_Agent_AI_Queue_Manager::OPTION_KEY, '' );
		$queue = $raw !== '' ? json_decode( $raw, true ) : null;

		if ( ! is_array( $queue ) ) {
			echo '<p style="color:#787c82">' . esc_html__( 'Queue not initialized.', 'seo-agent-ai' ) . '</p>';
			return;
		}

		$fields = array(
			'pending'              => __( 'Posts in queue', 'seo-agent-ai' ),
			'total_queued'         => __( 'Total ever queued', 'seo-agent-ai' ),
			'total_processed'      => __( 'Total processed', 'seo-agent-ai' ),
			'total_errors'         => __( 'Total errors', 'seo-agent-ai' ),
			'last_run'             => __( 'Last batch run', 'seo-agent-ai' ),
			'last_batch_processed' => __( 'Posts in last batch', 'seo-agent-ai' ),
		);

		echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">';
		foreach ( $fields as $key => $label ) {
			$val = 'pending' === $key ? count( $queue['items'] ?? array() ) : ( $queue[ $key ] ?? '—' );
			echo '<div style="background:#f6f7f7;border-radius:4px;padding:10px 14px">';
			echo '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#787c82;margin-bottom:4px">' . esc_html( $label ) . '</div>';
			echo '<div style="font-size:18px;font-weight:700;color:#1d2327">' . esc_html( (string) $val ) . '</div>';
			echo '</div>';
		}
		echo '</div>';
	}

	private function human_time( $timestamp ) {
		$diff = $timestamp - time();
		if ( $diff < 0 ) {
			return __( 'Overdue', 'seo-agent-ai' );
		}
		/* translators: Human-readable time difference. */
		return sprintf( __( 'in %s', 'seo-agent-ai' ), human_time_diff( time(), $timestamp ) );
	}
}
