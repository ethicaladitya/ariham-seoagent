<?php
/**
 * Main SEO Dashboard admin page.
 *
 * Shows: agent activity (what changed), traffic/ranking trends, score distribution.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Dashboard_Page {

	/** @var SEO_Agent_AI_Decision_Engine */
	private $decision_engine;

	/** @var SEO_Agent_AI_Report_Engine */
	private $report_engine;

	/** @var SEO_Agent_AI_Activity_Log */
	private $activity_log;

	public function __construct(
		SEO_Agent_AI_Decision_Engine $decision_engine,
		SEO_Agent_AI_Report_Engine $report_engine,
		SEO_Agent_AI_Activity_Log $activity_log
	) {
		$this->decision_engine = $decision_engine;
		$this->report_engine   = $report_engine;
		$this->activity_log    = $activity_log;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'ariham-seoagent' ) );
		}

		$report        = $this->report_engine->get( gmdate( 'Y-m-d' ) );
		$pending_count = $this->decision_engine->count_pending();
		$score_dist    = $report ? ( $report['score_distribution'] ?? array() ) : array();
		$trends        = $report ? ( $report['trends'] ?? array() ) : array();
		$summary       = $report ? ( $report['summary'] ?? array() ) : array();

		$recent_changes = $this->activity_log->get_entries( array(), 1, 15 );
		$total_changes  = $this->activity_log->get_count( array() );
		$sitekit_active = class_exists( 'SEO_Agent_AI_SiteKit_Bridge' ) && SEO_Agent_AI_SiteKit_Bridge::is_active();
		$gsc_connected  = $sitekit_active
			|| '' !== (string) get_option( 'seo_agent_ai_gsc_site_url', '' )
			|| '' !== (string) get_option( 'seo_agent_ai_gsc_site', '' );
		$is_first_run   = 0 === $total_changes && empty( $report ) && ! $gsc_connected;
		$autopilot      = (bool) get_option( 'seo_agent_ai_autopilot_enabled', false );

		$today_changes = $this->activity_log->get_count(
			array( 'date_from' => gmdate( 'Y-m-d' ) . ' 00:00:00' )
		);

		$last_run_raw   = get_option( 'seo_agent_ai_last_run_seo_agent_ai_daily_analysis', '' );
		$last_run_label = '';
		$agent_overdue  = false;
		if ( $last_run_raw !== '' ) {
			$diff = time() - (int) strtotime( $last_run_raw );
			if ( $diff < HOUR_IN_SECONDS ) {
				$last_run_label = sprintf(
					/* translators: %d: minutes ago. */
					__( '%d min ago', 'ariham-seoagent' ),
					(int) floor( $diff / 60 )
				);
			} elseif ( $diff < DAY_IN_SECONDS ) {
				$last_run_label = sprintf(
					/* translators: %d: hours ago. */
					__( '%dh ago', 'ariham-seoagent' ),
					(int) floor( $diff / HOUR_IN_SECONDS )
				);
			} else {
				$last_run_label = sprintf(
					/* translators: %d: days ago. */
					__( '%dd ago', 'ariham-seoagent' ),
					(int) floor( $diff / DAY_IN_SECONDS )
				);
				$agent_overdue = $diff > ( 26 * HOUR_IN_SECONDS );
			}
		} else {
			$last_run_label = __( 'Never', 'ariham-seoagent' );
			$agent_overdue  = true;
		}
		?>
		<div class="wrap sai-page">

			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow">
						<span class="sai-dot pulsing-green"></span>
						<?php esc_html_e( 'SEO Agent AI', 'ariham-seoagent' ); ?>
					</p>
					<h1 class="sai-header-title"><?php esc_html_e( 'Dashboard', 'ariham-seoagent' ); ?></h1>
				</div>
				<div class="sai-header-actions">
					<button class="sai-btn sai-btn-primary sai-run-scan">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803M15.803 15.803A7.5 7.5 0 1 0 5.196 5.196"/></svg>
						<span class="btn-label"><?php esc_html_e( 'Run Full Scan', 'ariham-seoagent' ); ?></span>
					</button>
				</div>
			</div>

			<div class="sai-body">

				<?php if ( $agent_overdue ) : ?>
				<div class="sai-autopilot-bar" style="background:#fef2f2;border-color:#fecaca">
					<div class="sai-ap-indicator">
						<div class="sai-ap-pulse" style="background:#dc2626"></div>
						<div>
							<div class="sai-ap-label" style="color:#991b1b">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
								<?php esc_html_e( 'Agent Overdue', 'ariham-seoagent' ); ?>
							</div>
							<div class="sai-ap-sub" style="color:#991b1b">
								<?php
								printf(
									/* translators: %s: time since last run. */
									esc_html__( 'Last run: %s — daily analysis may be stuck. Check Cron Status.', 'ariham-seoagent' ),
									esc_html( $last_run_label )
								);
								?>
							</div>
						</div>
					</div>
				</div>
				<?php elseif ( $autopilot ) : ?>
				<div class="sai-autopilot-bar ap-on">
					<div class="sai-ap-indicator">
						<div class="sai-ap-pulse"></div>
						<div>
							<div class="sai-ap-label">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>
								<?php esc_html_e( 'Autopilot ON', 'ariham-seoagent' ); ?>
							</div>
							<div class="sai-ap-sub">
								<?php
								printf(
									/* translators: %s: time of last run. */
									esc_html__( 'Auto-applying safe changes. Last run: %s', 'ariham-seoagent' ),
									esc_html( $last_run_label )
								);
								?>
							</div>
						</div>
					</div>
				</div>
				<?php else : ?>
				<div class="sai-autopilot-bar ap-off">
					<div class="sai-ap-indicator">
						<div class="sai-ap-pulse"></div>
						<div>
							<div class="sai-ap-label">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>
								<?php esc_html_e( 'Autopilot OFF', 'ariham-seoagent' ); ?>
							</div>
							<div class="sai-ap-sub">
								<?php
								printf(
									/* translators: %s: link to settings page */
									esc_html__( 'Manual review mode — %s to enable automatic safe fixes.', 'ariham-seoagent' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=seo-agent-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'ariham-seoagent' ) . '</a>'
								);
								?>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<div class="sai-scan-progress" style="display:none">
					<div class="sai-scan-bar-track"><div class="sai-scan-bar-fill" id="sai-scan-bar"></div></div>
					<p class="sai-scan-status" id="sai-scan-status"></p>
				</div>

				<?php if ( $is_first_run ) : ?>
					<?php $this->render_onboarding_banner( $gsc_connected ); ?>
				<?php endif; ?>

				<div class="sai-metrics">
					<div class="sai-metric m-primary">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Changes Today', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-activity-log' ) ); ?>" style="color:inherit;text-decoration:none"><?php echo esc_html( number_format_i18n( $today_changes ) ); ?></a>
						</div>
					</div>

					<div class="sai-metric m-warning">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Pending Approvals', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-approvals' ) ); ?>" style="color:inherit;text-decoration:none"><?php echo esc_html( number_format_i18n( $pending_count ) ); ?></a>
						</div>
					</div>

					<div class="sai-metric m-success">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Total Changes', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-activity-log' ) ); ?>" style="color:inherit;text-decoration:none"><?php echo esc_html( number_format_i18n( $total_changes ) ); ?></a>
						</div>
					</div>

					<div class="sai-metric m-neutral">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Opportunities', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-opportunities' ) ); ?>" style="color:inherit;text-decoration:none">
								<?php
								$opp_val = $summary['opportunities_detected'] ?? '—';
								echo is_int( $opp_val ) ? esc_html( number_format_i18n( $opp_val ) ) : esc_html( (string) $opp_val );
								?>
							</a>
						</div>
					</div>

					<div class="sai-metric m-neutral">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Pages Analyzed', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value">
							<?php
							$pages_val = $summary['pages_analyzed'] ?? '—';
							echo is_int( $pages_val ) ? esc_html( number_format_i18n( $pages_val ) ) : esc_html( (string) $pages_val );
							?>
						</div>
					</div>

					<div class="sai-metric <?php echo ( ( $summary['problems_detected'] ?? 0 ) > 0 ) ? 'm-danger' : 'm-success'; ?>">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Problems Found', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value">
							<?php
							$prob_val = $summary['problems_detected'] ?? '—';
							echo is_int( $prob_val ) ? esc_html( number_format_i18n( $prob_val ) ) : esc_html( (string) $prob_val );
							?>
						</div>
					</div>
				</div>

				<div class="sai-card" style="margin-top:24px">
					<div class="sai-card-header">
						<h2 class="sai-card-title">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
							<?php esc_html_e( 'Recent Agent Activity', 'ariham-seoagent' ); ?>
						</h2>
					</div>
					<div class="sai-card-body">
						<p class="sai-card-desc"><?php esc_html_e( 'Changes the agent has made — what was modified, what it looked like before, and the confidence level.', 'ariham-seoagent' ); ?></p>
						<?php $this->render_activity_timeline( $recent_changes ); ?>
					</div>
				</div>

				<div class="sai-two-col" style="margin-top:24px">
					<div class="sai-card">
						<div class="sai-card-header">
							<h2 class="sai-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
								<?php esc_html_e( 'Traffic & Keyword Trends', 'ariham-seoagent' ); ?>
							</h2>
						</div>
						<div class="sai-card-body">
							<p class="sai-card-desc"><?php esc_html_e( 'Keyword ranking movements detected from Search Console since the last GSC sync.', 'ariham-seoagent' ); ?></p>
							<?php $this->render_trends( $trends ); ?>
						</div>
					</div>

					<div class="sai-card">
						<div class="sai-card-header">
							<h2 class="sai-card-title">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
								<?php esc_html_e( 'Score Distribution', 'ariham-seoagent' ); ?>
							</h2>
						</div>
						<div class="sai-card-body">
							<p class="sai-card-desc"><?php esc_html_e( 'How your pages rank across SEO score tiers from the last scoring run.', 'ariham-seoagent' ); ?></p>
							<?php $this->render_score_distribution( $score_dist ); ?>
						</div>
					</div>
				</div>

			</div><!-- .sai-body -->
		</div><!-- .sai-page -->
		<?php

		$this->render_scan_js();
	}

	// -------------------------------------------------------------------
	// Onboarding banner
	// -------------------------------------------------------------------

	private function render_onboarding_banner( $google_connected ) {
		?>
		<div class="sai-card accent-primary sai-onboarding" style="margin-bottom:24px">
			<div class="sai-card-header">
				<h2 class="sai-card-title">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>
					<?php esc_html_e( "Welcome — let's scan your site", 'ariham-seoagent' ); ?>
				</h2>
			</div>
			<div class="sai-card-body">
				<p class="sai-card-desc">
					<?php esc_html_e( "SEO Agent hasn't analyzed your site yet. Run a scan to score every page, detect SEO problems, surface keyword opportunities, and generate AI-powered recommendations. Once done, you can review suggestions manually or switch on Autopilot.", 'ariham-seoagent' ); ?>
				</p>
				<ol class="sai-onboarding-steps">
					<li class="sai-onboarding-step <?php echo $google_connected ? 'step-done' : ''; ?>">
						<?php if ( $google_connected ) : ?>
							<span class="sai-badge b-success">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
								<?php esc_html_e( 'Google Search Console connected', 'ariham-seoagent' ); ?>
							</span>
						<?php else : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ariham-seoagent-connect' ) ); ?>" class="sai-btn sai-btn-ghost sai-btn-sm">
								<span class="btn-label"><?php esc_html_e( 'Connect Google Search Console', 'ariham-seoagent' ); ?></span>
							</a>
							<span class="sai-step-hint"><?php esc_html_e( '— optional, unlocks keyword & traffic data', 'ariham-seoagent' ); ?></span>
						<?php endif; ?>
					</li>
					<li class="sai-onboarding-step step-active">
						<strong><?php esc_html_e( 'Run your first site scan', 'ariham-seoagent' ); ?></strong>
					</li>
					<li class="sai-onboarding-step">
						<span style="color:var(--sai-text-muted)"><?php esc_html_e( 'Review recommendations or enable Autopilot in Settings', 'ariham-seoagent' ); ?></span>
					</li>
				</ol>
				<button class="sai-btn sai-btn-primary sai-run-scan" style="margin-top:16px">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803M15.803 15.803A7.5 7.5 0 1 0 5.196 5.196"/></svg>
					<span class="btn-label"><?php esc_html_e( 'Run Full Scan', 'ariham-seoagent' ); ?></span>
				</button>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------
	// Activity timeline
	// -------------------------------------------------------------------

	private function render_activity_timeline( array $entries ) {
		if ( empty( $entries ) ) {
			?>
			<div class="sai-empty">
				<div class="sai-empty-icon">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
				</div>
				<h3><?php esc_html_e( 'No activity yet', 'ariham-seoagent' ); ?></h3>
				<p><?php esc_html_e( 'Run a scan to let the agent analyze your site and generate recommendations.', 'ariham-seoagent' ); ?></p>
				<button class="sai-btn sai-btn-primary sai-run-scan">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803M15.803 15.803A7.5 7.5 0 1 0 5.196 5.196"/></svg>
					<span class="btn-label"><?php esc_html_e( 'Run Full Scan', 'ariham-seoagent' ); ?></span>
				</button>
			</div>
			<?php
			return;
		}

		$change_labels = array(
			'meta_title'       => __( 'Meta Title', 'ariham-seoagent' ),
			'meta_description' => __( 'Meta Description', 'ariham-seoagent' ),
			'focus_keyword'    => __( 'Focus Keyword', 'ariham-seoagent' ),
			'alt_text'         => __( 'Image Alt Text', 'ariham-seoagent' ),
			'heading'          => __( 'H1 Heading', 'ariham-seoagent' ),
			'faq_schema'       => __( 'FAQ Schema', 'ariham-seoagent' ),
			'internal_links'   => __( 'Internal Links', 'ariham-seoagent' ),
			'redirect'         => __( 'Redirect', 'ariham-seoagent' ),
		);

		$type_icons = array(
			'meta_title'       => 'dt-meta',
			'meta_description' => 'dt-meta',
			'focus_keyword'    => 'dt-meta',
			'alt_text'         => 'dt-image',
			'heading'          => 'dt-content',
			'faq_schema'       => 'dt-schema',
			'internal_links'   => 'dt-link',
			'redirect'         => 'dt-redirect',
		);

		echo '<ul class="sai-timeline">';

		foreach ( $entries as $entry ) {
			$post_id = (int) $entry['post_id'];
			$post    = get_post( $post_id );
			$title   = $post instanceof WP_Post ? $post->post_title : "(#{$post_id})";

			$change_key   = (string) $entry['field_changed'];
			$change_label = $change_labels[ $change_key ] ?? ucwords( str_replace( '_', ' ', $change_key ) );
			$icon_class   = $type_icons[ $change_key ] ?? 'dt-default';

			$before = (string) $entry['value_before'];
			$after  = (string) $entry['value_after'];

			$confidence = round( (float) $entry['confidence'] * 100 );
			$conf_level = $confidence >= 80 ? 'high' : ( $confidence >= 50 ? 'med' : 'low' );

			$status    = (string) $entry['status'];
			$triggered = (string) $entry['triggered_by'];
			$when_raw  = (string) $entry['created_at'];
			$when      = $when_raw ? human_time_diff( strtotime( $when_raw ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'ariham-seoagent' ) : '—'; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			$badge_class = 'b-neutral';
			if ( 'applied' === $status ) {
				$badge_class = 'b-success';
			} elseif ( 'pending' === $status ) {
				$badge_class = 'b-warning';
			} elseif ( 'rolled_back' === $status ) {
				$badge_class = 'b-danger';
			}

			echo '<li class="sai-timeline-item">';
			echo '<div class="sai-timeline-icon ' . esc_attr( $icon_class ) . '">';
			echo '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>';
			echo '</div>';

			echo '<div class="sai-timeline-body">';
			echo '<p class="sai-timeline-title">';
			echo '<a href="' . esc_url( get_permalink( $post_id ) ) . '" target="_blank">' . esc_html( $title ) . '</a>';
			echo ' <span class="sai-badge b-primary">' . esc_html( $change_label ) . '</span>';
			echo ' <span class="sai-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $status ) . '</span>';
			echo '</p>';
			echo '<p class="sai-timeline-sub">' . esc_html( $triggered ) . ' &middot; ' . esc_html( $when ) . '</p>';

			if ( $before !== '' || $after !== '' ) {
				echo '<div class="sai-timeline-diff">';
				if ( $before !== '' ) {
					echo '<div class="sai-diff-before"><span class="sai-diff-label">' . esc_html__( 'Before', 'ariham-seoagent' ) . '</span>' . esc_html( mb_strimwidth( $before, 0, 120, '…' ) ) . '</div>';
				}
				if ( $after !== '' ) {
					echo '<div class="sai-diff-after"><span class="sai-diff-label">' . esc_html__( 'After', 'ariham-seoagent' ) . '</span>' . esc_html( mb_strimwidth( $after, 0, 120, '…' ) ) . '</div>';
				}
				echo '</div>';
			}

			echo '<span class="sai-conf"><span class="sai-conf-bar"><span class="sai-conf-fill ' . esc_attr( $conf_level ) . '" style="width:' . esc_attr( $confidence ) . '%"></span></span> ' . esc_html( $confidence ) . '%</span>';
			echo '</div>';

			echo '<span class="sai-timeline-meta">' . esc_html( $when ) . '</span>';
			echo '</li>';
		}

		echo '</ul>';
		echo '<p style="margin-top:12px"><a href="' . esc_url( admin_url( 'admin.php?page=seo-agent-activity-log' ) ) . '" class="sai-btn sai-btn-ghost sai-btn-sm"><span class="btn-label">' . esc_html__( 'View full activity log →', 'ariham-seoagent' ) . '</span></a></p>';
	}

	// -------------------------------------------------------------------
	// Async scan JS — handled by assets/js/admin.js (initScan).
	// Nonce is passed via wp_localize_script in class-admin-page.php.
	// -------------------------------------------------------------------

	private function render_scan_js() {
		// No-op: admin.js handles the scan flow globally.
	}

	// -------------------------------------------------------------------
	// Traffic & keyword trends
	// -------------------------------------------------------------------

	private function render_trends( array $trends ) {
		$rising    = $trends['rising'] ?? array();
		$declining = $trends['declining'] ?? array();

		if ( empty( $rising ) && empty( $declining ) ) {
			?>
			<div class="sai-empty">
				<div class="sai-empty-icon">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
				</div>
				<h3><?php esc_html_e( 'No trend data yet', 'ariham-seoagent' ); ?></h3>
				<p><?php esc_html_e( 'Connect Google Search Console and wait for the daily GSC sync to populate this panel.', 'ariham-seoagent' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ariham-seoagent-connect' ) ); ?>" class="sai-btn sai-btn-primary">
					<span class="btn-label"><?php esc_html_e( 'Connect Google →', 'ariham-seoagent' ); ?></span>
				</a>
			</div>
			<?php
			return;
		}

		if ( ! empty( $rising ) ) {
			echo '<p class="sai-trend-label sai-trend-up">&#8593; ' . esc_html__( 'Improving', 'ariham-seoagent' ) . '</p>';
			echo '<ul class="sai-trend-list">';
			foreach ( array_slice( $rising, 0, 5 ) as $r ) {
				echo '<li class="sai-trend-item">';
				echo '<span class="sai-trend-keyword">' . esc_html( $r['keyword'] ) . '</span>';
				echo '<span class="sai-trend-page">' . esc_html( $r['post_title'] ) . '</span>';
				echo '<span class="sai-badge b-success">+' . esc_html( $r['change'] ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $declining ) ) {
			echo '<p class="sai-trend-label sai-trend-down" style="margin-top:16px">&#8595; ' . esc_html__( 'Declining', 'ariham-seoagent' ) . '</p>';
			echo '<ul class="sai-trend-list">';
			foreach ( array_slice( $declining, 0, 5 ) as $r ) {
				echo '<li class="sai-trend-item">';
				echo '<span class="sai-trend-keyword">' . esc_html( $r['keyword'] ) . '</span>';
				echo '<span class="sai-trend-page">' . esc_html( $r['post_title'] ) . '</span>';
				echo '<span class="sai-badge b-danger">-' . esc_html( $r['change'] ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		}
	}

	// -------------------------------------------------------------------
	// Score distribution
	// -------------------------------------------------------------------

	private function render_score_distribution( array $dist ) {
		if ( empty( $dist ) ) {
			?>
			<div class="sai-empty">
				<div class="sai-empty-icon">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
				</div>
				<h3><?php esc_html_e( 'No score data yet', 'ariham-seoagent' ); ?></h3>
				<p><?php esc_html_e( 'Wait for the weekly scoring cron or run it manually via Cron Status.', 'ariham-seoagent' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-cron' ) ); ?>" class="sai-btn sai-btn-ghost">
					<span class="btn-label"><?php esc_html_e( 'Go to Cron Status →', 'ariham-seoagent' ); ?></span>
				</a>
			</div>
			<?php
			return;
		}

		$labels = array(
			'excellent' => __( 'Excellent (80-100)', 'ariham-seoagent' ),
			'good'      => __( 'Good (60-79)', 'ariham-seoagent' ),
			'average'   => __( 'Average (40-59)', 'ariham-seoagent' ),
			'poor'      => __( 'Poor (20-39)', 'ariham-seoagent' ),
			'critical'  => __( 'Critical (0-19)', 'ariham-seoagent' ),
		);
		$colors = array(
			'excellent' => '#10b981',
			'good'      => '#34d399',
			'average'   => '#f59e0b',
			'poor'      => '#f97316',
			'critical'  => '#ef4444',
		);

		$total = array_sum( $dist );

		echo '<div class="sai-dist-bars">';
		foreach ( $labels as $key => $label ) {
			$count = (int) ( $dist[ $key ] ?? 0 );
			$pct   = $total > 0 ? round( $count / $total * 100 ) : 0;
			echo '<div class="sai-dist-row">';
			echo '<span class="sai-dist-label">' . esc_html( $label ) . '</span>';
			echo '<div class="sai-dist-bar-track"><div class="sai-dist-bar-fill" data-w="' . esc_attr( $pct ) . '" style="background:' . esc_attr( $colors[ $key ] ) . ';width:0%"></div></div>';
			echo '<span class="sai-dist-count">' . esc_html( $count ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}
}
