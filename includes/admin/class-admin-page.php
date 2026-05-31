<?php
/**
 * Admin page controller.
 *
 * Registers the WP Admin menu structure and delegates rendering to
 * specialised page classes:
 *   Overview   -> this file
 *   Connect    -> class-connect-page.php
 *   Report     -> class-report-page.php
 *   Settings   -> this file
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ariham_SEOAgent_Admin_Page {

	/** @var Ariham_SEOAgent_Data_Store */
	private $data_store;

	/** @var Ariham_SEOAgent_Connect_Page */
	private $connect_page;

	/** @var Ariham_SEOAgent_Report_Page */
	private $report_page;

	/** @var Ariham_SEOAgent_Google_OAuth */
	private $oauth;

	/** @var Ariham_SEOAgent_SEO_Plugin_Bridge */
	private $bridge;

	/** @var Ariham_SEOAgent_Dashboard_Page */
	private $dashboard_page;

	/** @var Ariham_SEOAgent_Opportunities_Page */
	private $opportunities_page;

	/** @var Ariham_SEOAgent_Rankings_Page */
	private $rankings_page;

	/** @var Ariham_SEOAgent_Pending_Approvals_Page */
	private $pending_approvals_page;

	/** @var Ariham_SEOAgent_Rollback_Center_Page */
	private $rollback_center_page;

	/** @var Ariham_SEOAgent_Cron_Status_Page */
	private $cron_status_page;

	/** @var Ariham_SEOAgent_Image_SEO_Page */
	private $image_seo_page;

	/** @var Ariham_SEOAgent_Redirects_Page */
	private $redirects_page;

	/** @var Ariham_SEOAgent_Activity_Log_Page */
	private $activity_log_page;

	public function __construct(
		Ariham_SEOAgent_Data_Store $data_store,
		Ariham_SEOAgent_Connect_Page $connect_page,
		Ariham_SEOAgent_Report_Page $report_page,
		Ariham_SEOAgent_Google_OAuth $oauth,
		Ariham_SEOAgent_SEO_Plugin_Bridge $bridge,
		Ariham_SEOAgent_Dashboard_Page $dashboard_page,
		Ariham_SEOAgent_Opportunities_Page $opportunities_page,
		Ariham_SEOAgent_Rankings_Page $rankings_page,
		Ariham_SEOAgent_Pending_Approvals_Page $pending_approvals_page,
		Ariham_SEOAgent_Rollback_Center_Page $rollback_center_page,
		Ariham_SEOAgent_Cron_Status_Page $cron_status_page,
		Ariham_SEOAgent_Image_SEO_Page $image_seo_page,
		Ariham_SEOAgent_Redirects_Page $redirects_page,
		Ariham_SEOAgent_Activity_Log_Page $activity_log_page
	) {
		$this->data_store             = $data_store;
		$this->connect_page           = $connect_page;
		$this->report_page            = $report_page;
		$this->oauth                  = $oauth;
		$this->bridge                 = $bridge;
		$this->dashboard_page         = $dashboard_page;
		$this->opportunities_page     = $opportunities_page;
		$this->rankings_page          = $rankings_page;
		$this->pending_approvals_page = $pending_approvals_page;
		$this->rollback_center_page   = $rollback_center_page;
		$this->cron_status_page       = $cron_status_page;
		$this->image_seo_page         = $image_seo_page;
		$this->redirects_page         = $redirects_page;
		$this->activity_log_page      = $activity_log_page;

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	// -------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------

	public function register_menu() {
		// Top-level menu item goes to the new Dashboard.
		add_menu_page(
			__( 'Ariham SEOAgent', 'ariham-seoagent' ),
			__( 'Ariham SEOAgent', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent',
			array( $this->dashboard_page, 'render' ),
			'dashicons-chart-area',
			58
		);

		// First submenu must match the top-level slug to rename it.
		add_submenu_page(
			'ariham-seoagent',
			__( 'Dashboard', 'ariham-seoagent' ),
			__( 'Dashboard', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent',
			array( $this->dashboard_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Connect Google', 'ariham-seoagent' ),
			__( 'Connect Google', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-connect',
			array( $this->connect_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Analysis', 'ariham-seoagent' ),
			__( 'Analysis', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-report',
			array( $this->report_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Opportunities', 'ariham-seoagent' ),
			__( 'Opportunities', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-opportunities',
			array( $this->opportunities_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Keyword Rankings', 'ariham-seoagent' ),
			__( 'Rankings', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-rankings',
			array( $this->rankings_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Pending Approvals', 'ariham-seoagent' ),
			__( 'Approvals', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-approvals',
			array( $this->pending_approvals_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Rollback Center', 'ariham-seoagent' ),
			__( 'Rollback', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-rollback',
			array( $this->rollback_center_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Image SEO', 'ariham-seoagent' ),
			__( 'Image SEO', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-image-seo',
			array( $this->image_seo_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Redirects & 404s', 'ariham-seoagent' ),
			__( 'Redirects & 404s', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-redirects',
			array( $this->redirects_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Audit Log', 'ariham-seoagent' ),
			__( 'Audit Log', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-log',
			array( $this->activity_log_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Cron Status', 'ariham-seoagent' ),
			__( 'Cron Status', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-cron',
			array( $this->cron_status_page, 'render' )
		);

		add_submenu_page(
			'ariham-seoagent',
			__( 'Settings', 'ariham-seoagent' ),
			__( 'Settings', 'ariham-seoagent' ),
			'manage_options',
			'ariham-seoagent-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'seoagent' ) === false && strpos( $hook, 'seo-agent' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'ariham-seoagent-admin',
			ARIHAM_SEOAGENT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ARIHAM_SEOAGENT_VERSION
		);
		wp_enqueue_script(
			'ariham-seoagent-admin',
			ARIHAM_SEOAGENT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ARIHAM_SEOAGENT_VERSION,
			true
		);
		wp_localize_script(
			'ariham-seoagent-admin',
			'arihamSeoagentAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'ariham_seoagent_analyze_batch' ),
				'nonceApprove' => wp_create_nonce( 'ariham_seoagent_bulk_apply_safe' ),
				'i18n'         => array(
					'loading'         => __( 'Working…', 'ariham-seoagent' ),
					'scanning'        => __( 'Scanning', 'ariham-seoagent' ),
					'scan_done'       => __( 'Scan complete!', 'ariham-seoagent' ),
					'scan_error'      => __( 'Scan failed. Please try again.', 'ariham-seoagent' ),
					'network_error'   => __( 'Network error. Please try again.', 'ariham-seoagent' ),
					'recommendations' => __( 'recommendation(s) generated.', 'ariham-seoagent' ),
					'saved'           => __( 'Settings saved!', 'ariham-seoagent' ),
					'bulk_confirm'    => __( 'Apply all safe pending decisions now? This cannot be undone.', 'ariham-seoagent' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------
	// Overview page
	// -------------------------------------------------------------------

	public function render_overview_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice       = filter_input( INPUT_GET, 'ariham_seoagent_notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$notice       = is_string( $notice ) ? sanitize_key( wp_unslash( $notice ) ) : '';
		$post_ids     = $this->data_store->get_posts_with_recommendations( 100 );
		$last_run     = $this->data_store->get_last_run();
		$is_connected = $this->oauth->is_connected();
		$autopilot    = (bool) get_option( 'ariham_seoagent_autopilot_enabled', false );
		?>
		<div class="wrap seo-agent-wrap">
			<h1 style="display:flex;align-items:center;gap:12px;">
				<?php esc_html_e( 'Ariham SEOAgent', 'ariham-seoagent' ); ?>
				<?php if ( $autopilot ) : ?>
					<span class="seo-agent-autopilot-badge on"><span class="dot"></span><?php esc_html_e( 'Autopilot ON', 'ariham-seoagent' ); ?></span>
				<?php else : ?>
					<span class="seo-agent-autopilot-badge off"><span class="dot"></span><?php esc_html_e( 'Autopilot OFF', 'ariham-seoagent' ); ?></span>
				<?php endif; ?>
			</h1>

			<?php if ( ! $is_connected ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: Connect Google page link */
							esc_html__( 'Google account not connected. %s to enable live data analysis.', 'ariham-seoagent' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=ariham-seoagent-connect' ) ) . '">' . esc_html__( 'Connect Google', 'ariham-seoagent' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php $this->render_notice( $notice ); ?>

			<?php if ( ! empty( $last_run ) ) : ?>
				<div class="seo-agent-card" style="padding:14px 20px;">
					<p style="margin:0;font-size:13px;color:#3c434a;">
						<strong><?php esc_html_e( 'Last run:', 'ariham-seoagent' ); ?></strong>
						<?php
						echo esc_html(
							sprintf(
								'%s (%s) — %d posts analyzed, %d with recommendations',
								isset( $last_run['finished_at'] ) ? $last_run['finished_at'] : '-',
								isset( $last_run['mode'] ) ? $last_run['mode'] : 'manual',
								isset( $last_run['processed_posts'] ) ? (int) $last_run['processed_posts'] : 0,
								isset( $last_run['posts_with_recommendations'] ) ? (int) $last_run['posts_with_recommendations'] : 0
							)
						);
						?>
						<?php if ( ! empty( $last_run['failed_posts'] ) ) : ?>
							<span style="color:#b32d2e;margin-left:8px;"><?php echo esc_html( (int) $last_run['failed_posts'] . ' API failures' ); ?></span>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			<div id="seo-analysis-wrap" style="margin-bottom:24px;">
				<button id="seo-run-analysis" class="button button-primary" style="font-size:14px;height:38px;padding:0 20px;">
					<?php esc_html_e( 'Run Analysis Now', 'ariham-seoagent' ); ?>
				</button>
				<div id="seo-analysis-progress" style="display:none;max-width:540px;margin-top:16px;">
					<div class="seo-agent-progress-track">
						<div id="seo-progress-fill" class="seo-agent-progress-fill" style="width:0%"></div>
					</div>
					<p id="seo-progress-status" class="seo-agent-progress-status">
						<?php esc_html_e( 'Initializing&hellip;', 'ariham-seoagent' ); ?>
					</p>
				</div>
			</div>

			<?php ob_start(); ?>
			(function($) {
				'use strict';
				var batchNonce = '<?php echo esc_js( wp_create_nonce( 'ariham_seoagent_analyze_batch' ) ); ?>';
				var strings = {
					analyzing: '<?php echo esc_js( __( 'Analyzing\u2026', 'ariham-seoagent' ) ); ?>',
					of:        '<?php echo esc_js( __( 'of', 'ariham-seoagent' ) ); ?>',
					done:      '<?php echo esc_js( __( 'Analysis complete', 'ariham-seoagent' ) ); ?>',
					posts:     '<?php echo esc_js( __( 'posts analyzed', 'ariham-seoagent' ) ); ?>',
					recs:      '<?php echo esc_js( __( 'with recommendations', 'ariham-seoagent' ) ); ?>',
					errors:    '<?php echo esc_js( __( 'API errors', 'ariham-seoagent' ) ); ?>',
					loading:   '<?php echo esc_js( __( 'Loading results\u2026', 'ariham-seoagent' ) ); ?>',
					retry:     '<?php echo esc_js( __( 'Run Analysis Now', 'ariham-seoagent' ) ); ?>',
					connErr:   '<?php echo esc_js( __( 'Connection error \u2014 please try again.', 'ariham-seoagent' ) ); ?>'
				};

				$('#seo-run-analysis').on('click', function() {
					$(this).prop('disabled', true).html(
						'<span class="spinner is-active" style="float:none;margin:-3px 6px 0 0;vertical-align:middle;width:16px;height:16px;"></span>' + strings.analyzing
					);
					$('#seo-analysis-progress').slideDown(200);
					runBatch(0);
				});

				function runBatch(offset) {
					$.post(ajaxurl, {
						action:      'ariham_seoagent_analyze_batch',
						offset:      offset,
						_ajax_nonce: batchNonce
					})
					.done(function(r) {
						if (!r.success) {
							setStatus((r.data ? r.data : 'Error'), 'error');
							resetButton();
							return;
						}
						var d = r.data;
						$('#seo-progress-fill').css('width', d.percent + '%');

						if (d.done) {
							$('#seo-progress-fill').css('width', '100%').addClass('complete');
							var msg = '\u2713 ' + strings.done + ' \u2014 ' + d.total + ' ' + strings.posts;
							if (d.with_recs > 0) {
								msg += ', ' + d.with_recs + ' ' + strings.recs;
							}
							if (d.failed > 0) {
								msg += ' (' + d.failed + ' ' + strings.errors + ')';
							}
							setStatus(msg, 'success');
							setTimeout(function() {
								setStatus(strings.loading, 'info');
								location.reload();
							}, 1400);
						} else {
							var status = d.processed + ' ' + strings.of + ' ' + d.total;
							if (d.current_title) {
								status += ' \u2014 ' + d.current_title;
							}
							setStatus(status, 'info');
							runBatch(d.processed);
						}
					})
					.fail(function() {
						setStatus(strings.connErr, 'error');
						resetButton();
					});
				}

				function setStatus(msg, type) {
					var colors = { success: '#1e8e3e', error: '#c5221f', info: '#50575e' };
					$('#seo-progress-status').text(msg).css('color', colors[type] || colors.info);
				}

				function resetButton() {
					$('#seo-run-analysis').prop('disabled', false).text(strings.retry);
				}
			})(jQuery);
			<?php wp_add_inline_script( 'ariham-seoagent-admin', ob_get_clean() ); ?>

			<?php if ( empty( $post_ids ) ) : ?>
				<p><em><?php esc_html_e( 'No recommendations yet. Run an analysis to populate insights.', 'ariham-seoagent' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post', 'ariham-seoagent' ); ?></th>
							<th><?php esc_html_e( 'Severity', 'ariham-seoagent' ); ?></th>
							<th><?php esc_html_e( 'Signals', 'ariham-seoagent' ); ?></th>
							<th><?php esc_html_e( 'Recommendations', 'ariham-seoagent' ); ?></th>
							<th><?php esc_html_e( 'Backups', 'ariham-seoagent' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $post_ids as $post_id ) : ?>
							<?php $this->render_post_row( $post_id ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------
	// Overview: post row
	// -------------------------------------------------------------------

	private function render_post_row( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$metrics         = $this->data_store->get_post_metrics( $post_id );
		$analysis        = isset( $metrics['analysis'] ) && is_array( $metrics['analysis'] ) ? $metrics['analysis'] : array();
		$signals         = isset( $analysis['signals'] ) ? $analysis['signals'] : array();
		$severity        = isset( $analysis['severity'] ) ? (string) $analysis['severity'] : 'none';
		$confidence      = isset( $analysis['confidence'] ) ? (float) $analysis['confidence'] : 0.0;
		$recommendations = $this->data_store->get_recommendations( $post_id );
		$backups         = $this->data_store->get_backups( $post_id );
		$gsc_error       = isset( $metrics['gsc_error'] ) ? (string) $metrics['gsc_error'] : '';
		$ga4_error       = isset( $metrics['ga4_error'] ) ? (string) $metrics['ga4_error'] : '';
		$permalink       = get_permalink( $post_id );

		echo '<tr>';

		echo '<td>';
		echo '<strong><a href="' . esc_url( (string) get_edit_post_link( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a></strong>';
		echo '<br/><span class="seo-agent-muted">' . esc_html( $permalink ? (string) $permalink : '' ) . '</span>';
		echo '</td>';

		echo '<td><span class="seo-agent-pill ' . esc_attr( $severity ) . '">' . esc_html( strtoupper( $severity ) ) . '</span></td>';

		echo '<td>';
		echo wp_kses_post( $this->format_signals( $signals ) );
		if ( $gsc_error !== '' || $ga4_error !== '' ) {
			echo '<br/><span class="seo-agent-muted" style="color:#b32d2e;">';
			if ( $gsc_error ) {
				echo esc_html( 'GSC: ' . $gsc_error );
			}
			if ( $ga4_error ) {
				echo '<br/>' . esc_html( 'GA4: ' . $ga4_error );
			}
			echo '</span>';
		}
		echo '</td>';

		echo '<td>';
		if ( empty( $recommendations ) ) {
			echo '<span class="seo-agent-muted">' . esc_html__( 'No actions suggested.', 'ariham-seoagent' ) . '</span>';
		} else {
			foreach ( $recommendations as $index => $rec ) {
				$this->render_recommendation( $post_id, $index, $rec, $confidence );
			}
		}
		echo '</td>';

		echo '<td>';
		if ( ! empty( $backups ) ) {
			$latest   = $backups[0];
			$captured = isset( $latest['captured_at'] ) ? $latest['captured_at'] : '';
			echo '<span class="seo-agent-muted">' . esc_html( count( $backups ) . ' saved — latest ' . $captured ) . '</span><br/>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:6px;">';
			wp_nonce_field( 'ariham_seoagent_rollback_backup' );
			echo '<input type="hidden" name="action" value="ariham_seoagent_rollback_backup" />';
			echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '" />';
			echo '<button type="submit" class="button button-small"'
				. ' onclick="return confirm(\'' . esc_js( __( 'Restore the most recent backup for this post?', 'ariham-seoagent' ) ) . '\')">'
				. esc_html__( 'Rollback', 'ariham-seoagent' )
				. '</button>';
			echo '</form>';
		} else {
			echo '<span class="seo-agent-muted">' . esc_html__( 'No backup yet.', 'ariham-seoagent' ) . '</span>';
		}
		echo '</td>';

		echo '</tr>';
	}

	// -------------------------------------------------------------------
	// Recommendation card
	// -------------------------------------------------------------------

	private function render_recommendation( $post_id, $index, array $rec, $page_confidence ) {
		$type     = isset( $rec['type'] ) ? sanitize_text_field( $rec['type'] ) : '';
		$risk     = isset( $rec['risk'] ) ? sanitize_text_field( $rec['risk'] ) : 'risky';
		$priority = isset( $rec['priority'] ) ? sanitize_text_field( $rec['priority'] ) : 'low';
		$reason   = isset( $rec['reason'] ) ? sanitize_text_field( $rec['reason'] ) : '';
		$proposed = isset( $rec['proposed'] ) && is_array( $rec['proposed'] ) ? $rec['proposed'] : array();
		$conf     = isset( $rec['confidence'] ) ? (float) $rec['confidence'] : $page_confidence;
		$conf_pct = round( $conf * 100 );
		$conf_cls = $conf >= 0.75 ? 'high' : ( $conf >= 0.5 ? 'medium' : 'low' );

		echo '<div class="seo-agent-rec">';
		echo '<div class="seo-agent-rec-header">';
		echo '<span class="seo-agent-pill ' . esc_attr( $risk ) . '">' . esc_html( strtoupper( $risk ) ) . '</span>';
		echo '<span class="seo-agent-pill ' . esc_attr( $priority ) . '">' . esc_html( strtoupper( $priority ) ) . '</span>';
		echo '<span class="seo-agent-muted seo-agent-mono">' . esc_html( $type ) . '</span>';
		echo '<div class="seo-agent-confidence" style="margin-left:auto;">';
		echo '<div class="seo-agent-confidence-bar"><div class="seo-agent-confidence-fill ' . esc_attr( $conf_cls ) . '" style="width:' . esc_attr( (string) $conf_pct ) . '%"></div></div>';
		echo '<span class="seo-agent-muted">' . esc_html( $conf_pct . '%' ) . '</span>';
		echo '</div>';
		echo '</div>';

		echo '<p class="seo-agent-rec-reason">' . esc_html( $reason ) . '</p>';

		if ( ! empty( $proposed['meta_title'] ) || ! empty( $proposed['meta_description'] ) ) {
			echo '<div class="seo-agent-rec-proposed">';
			if ( ! empty( $proposed['meta_title'] ) ) {
				echo '<strong>' . esc_html__( 'Proposed title:', 'ariham-seoagent' ) . '</strong> ' . esc_html( $proposed['meta_title'] ) . '<br/>';
			}
			if ( ! empty( $proposed['meta_description'] ) ) {
				echo '<strong>' . esc_html__( 'Proposed description:', 'ariham-seoagent' ) . '</strong> ' . esc_html( $proposed['meta_description'] );
			}
			echo '</div>';
		}

		if ( ! empty( $proposed['summary'] ) ) {
			echo '<p class="seo-agent-rec-proposed">' . esc_html( $proposed['summary'] ) . '</p>';
		}

		if ( $risk === 'safe' && in_array( $type, array( 'meta_update', 'monitor_decline' ), true ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'ariham_seoagent_apply_fix' );
			echo '<input type="hidden" name="action" value="ariham_seoagent_apply_fix" />';
			echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '" />';
			echo '<input type="hidden" name="rec_index" value="' . esc_attr( (string) $index ) . '" />';
			echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Approve &amp; Apply', 'ariham-seoagent' ) . '</button>';
			echo '</form>';
		}

		echo '</div>';
	}

	// -------------------------------------------------------------------
	// Settings page
	// -------------------------------------------------------------------


	// -------------------------------------------------------------------
	// Settings page
	// -------------------------------------------------------------------

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice          = filter_input( INPUT_GET, 'ariham_seoagent_notice', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$notice          = is_string( $notice ) ? sanitize_key( wp_unslash( $notice ) ) : '';
		$client_id       = (string) get_option( Ariham_SEOAgent_Google_OAuth::OPTION_CLIENT_ID, '' );
		$client_secret   = (string) get_option( Ariham_SEOAgent_Google_OAuth::OPTION_CLIENT_SECRET, '' );
		$gsc_site_url    = (string) get_option( Ariham_SEOAgent_GSC_Client::OPTION_GSC_SITE_URL, home_url( '/' ) );
		$ga4_property_id = (string) get_option( Ariham_SEOAgent_GA4_Client::OPTION_GA4_PROPERTY_ID, '' );
		$gemini_has_key  = '' !== (string) get_option( Ariham_SEOAgent_Gemini_Client::OPTION_API_KEY, '' );
		$openai_has_key  = '' !== (string) get_option( Ariham_SEOAgent_OpenAI_Client::OPTION_API_KEY, '' );
		$autopilot       = (bool) get_option( 'ariham_seoagent_autopilot_enabled', false );
		$max_daily       = (int) get_option( 'ariham_seoagent_autopilot_max_daily', 5 );
		$min_confidence  = (float) get_option( 'ariham_seoagent_autopilot_min_confidence', 0.7 );
		$log_retention   = (int) get_option( 'ariham_seoagent_log_retention_days', 90 );
		$score_target    = (int) get_option( 'ariham_seoagent_score_target', 70 );
		$ai_provider     = (string) get_option( 'ariham_seoagent_ai_provider', 'gemini' );
		$email_reports   = (bool) get_option( 'ariham_seoagent_email_reports', false );
		$email_address   = (string) get_option( 'ariham_seoagent_email_address', '' );
		$cwv_enabled     = (bool) get_option( Ariham_SEOAgent_Plugin::OPTION_CWV_ENABLED, false );
		$conn_result     = get_transient( Ariham_SEOAgent_Plugin::CONNECTION_TEST_TRANSIENT );
		$is_connected    = $this->oauth->is_connected();
		$sitekit_active  = class_exists( 'Ariham_SEOAgent_SiteKit_Bridge' ) && Ariham_SEOAgent_SiteKit_Bridge::is_active();

		if ( $conn_result !== false ) {
			delete_transient( Ariham_SEOAgent_Plugin::CONNECTION_TEST_TRANSIENT );
		}
		?>
		<div class="wrap sai-page">
			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow"><span class="sai-dot"></span><?php esc_html_e( 'Ariham SEOAgent', 'ariham-seoagent' ); ?></p>
					<h1 class="sai-header-title"><?php esc_html_e( 'Settings', 'ariham-seoagent' ); ?></h1>
				</div>
				<div class="sai-header-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'ariham_seoagent_test_connection' ); ?>
						<input type="hidden" name="action" value="ariham_seoagent_test_connection">
						<button type="submit" class="sai-btn sai-btn-ghost"><span class="btn-label"><?php esc_html_e( 'Test Connection', 'ariham-seoagent' ); ?></span></button>
					</form>
				</div>
			</div>

			<div class="sai-body">
				<?php $this->render_notice( $notice ); ?>
				<?php if ( is_array( $conn_result ) ) : ?>
					<?php $this->render_connection_results( $conn_result ); ?>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ariham_seoagent_save_settings' ); ?>
					<input type="hidden" name="action" value="ariham_seoagent_save_settings">

					<?php // ------------------------------------------------------------------ ?>
					<?php // Google Data Sources ?>
					<?php // ------------------------------------------------------------------ ?>
					<div class="sai-settings-section">
						<h2 class="sai-settings-section-title"><?php esc_html_e( 'Google Data Sources', 'ariham-seoagent' ); ?></h2>

						<?php if ( $sitekit_active ) : ?>
						<div class="sai-card accent-success" style="margin-bottom:16px">
							<div class="sai-card-body">
								<div class="sai-connect-status-row">
									<div class="sai-connect-status-item csi-ok">
										<div class="sai-connect-icon-wrap">&#10003;</div>
										<div class="sai-connect-item-body">
											<strong><?php esc_html_e( 'Connected via Google Site Kit', 'ariham-seoagent' ); ?></strong>
											<span><?php esc_html_e( 'Search Console and Analytics data are being pulled automatically.', 'ariham-seoagent' ); ?></span>
										</div>
									</div>
								</div>
								<div class="sai-field" style="margin-top:12px">
									<span class="sai-field-label"><?php esc_html_e( 'Search Console', 'ariham-seoagent' ); ?></span>
									<div class="sai-field-control"><code><?php echo esc_html( Ariham_SEOAgent_SiteKit_Bridge::get_gsc_site_url() ); ?></code></div>
								</div>
								<?php if ( Ariham_SEOAgent_SiteKit_Bridge::is_ga4_active() ) : ?>
								<div class="sai-field">
									<span class="sai-field-label"><?php esc_html_e( 'Analytics (GA4)', 'ariham-seoagent' ); ?></span>
									<div class="sai-field-control"><code><?php echo esc_html( 'Property ' . Ariham_SEOAgent_SiteKit_Bridge::get_ga4_property_id() ); ?></code></div>
								</div>
								<?php endif; ?>
							</div>
						</div>

						<?php else : ?>

						<div class="sai-card" style="margin-bottom:16px">
							<div class="sai-card-header"><h3 class="sai-card-title"><?php esc_html_e( 'Google OAuth Credentials', 'ariham-seoagent' ); ?></h3></div>
							<div class="sai-card-body">
								<div class="sai-notice n-info" style="margin-bottom:14px">
									<p>
										<?php esc_html_e( 'Tip: Install the free Google Site Kit plugin to connect automatically — no credentials needed.', 'ariham-seoagent' ); ?>
										<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=google+site+kit&tab=search&type=term' ) ); ?>" style="margin-left:6px">
											<?php esc_html_e( 'Install Site Kit', 'ariham-seoagent' ); ?> &rarr;
										</a>
									</p>
								</div>
								<div class="sai-field">
									<label class="sai-field-label" for="google_client_id"><?php esc_html_e( 'Client ID', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<input type="text" id="google_client_id" name="google_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text">
									</div>
								</div>
								<div class="sai-field">
									<label class="sai-field-label" for="google_client_secret"><?php esc_html_e( 'Client Secret', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<div class="sai-key-input-wrap">
											<input type="password" id="google_client_secret" name="google_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text" autocomplete="off">
											<button type="button" class="sai-key-reveal" aria-label="<?php esc_attr_e( 'Show/hide', 'ariham-seoagent' ); ?>">&#128065;</button>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="sai-card" style="margin-bottom:16px">
							<div class="sai-card-header"><h3 class="sai-card-title"><?php esc_html_e( 'Search Console &amp; Analytics Properties', 'ariham-seoagent' ); ?></h3></div>
							<div class="sai-card-body">
								<?php if ( ! $is_connected ) : ?>
								<div class="sai-notice n-warning" style="margin-bottom:14px">
									<p>
										<?php
										printf(
											/* translators: %s: HTML link to the Connect Google page. */
											esc_html__( 'Connect your Google account to load available properties automatically. %s', 'ariham-seoagent' ),
											'<a href="' . esc_url( admin_url( 'admin.php?page=ariham-seoagent-connect' ) ) . '">' . esc_html__( 'Connect Google', 'ariham-seoagent' ) . '</a>'
										);
										?>
									</p>
								</div>
								<?php endif; ?>
								<div class="sai-field">
									<label class="sai-field-label" for="gsc_site_url"><?php esc_html_e( 'Search Console Property', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control" id="seo-gsc-property-wrap">
										<input type="text" id="gsc_site_url" name="gsc_site_url" value="<?php echo esc_attr( $gsc_site_url ); ?>" class="regular-text">
										<p class="description"><?php esc_html_e( 'Full URL, sc-domain:example.com, or bare domain.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
								<div class="sai-field">
									<label class="sai-field-label" for="ga4_property_id"><?php esc_html_e( 'Analytics Property ID', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control" id="seo-ga4-property-wrap">
										<input type="text" id="ga4_property_id" name="ga4_property_id" value="<?php echo esc_attr( $ga4_property_id ); ?>" class="regular-text">
										<p class="description"><?php esc_html_e( 'Numeric GA4 property ID.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
							</div>
						</div>

						<?php endif; // $sitekit_active ?>
					</div>

					<?php // ------------------------------------------------------------------ ?>
					<?php // SEO Plugin Integration ?>
					<?php // ------------------------------------------------------------------ ?>
					<div class="sai-settings-section">
						<h2 class="sai-settings-section-title"><?php esc_html_e( 'SEO Plugin Integration', 'ariham-seoagent' ); ?></h2>
						<div class="sai-card" style="margin-bottom:16px">
							<div class="sai-card-body">
								<?php
								$detected_plugins = $this->bridge->get_detected_plugins();
								if ( ! empty( $detected_plugins ) ) :
									?>
								<p class="description" style="margin-bottom:12px">
									<?php esc_html_e( 'Ariham SEOAgent is automatically syncing changes with the following active plugins.', 'ariham-seoagent' ); ?>
								</p>
								<div style="display:flex;flex-wrap:wrap;gap:8px">
									<?php foreach ( $detected_plugins as $slug ) : ?>
										<span class="sai-badge b-success"><?php echo esc_html( $this->bridge->get_plugin_label( $slug ) ); ?></span>
									<?php endforeach; ?>
								</div>
								<?php else : ?>
								<div class="sai-notice n-info">
									<p><?php esc_html_e( 'No supported SEO plugin detected. Install Yoast SEO, RankMath SEO, or SmartCrawl to automatically sync generated metadata.', 'ariham-seoagent' ); ?></p>
								</div>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<?php // ------------------------------------------------------------------ ?>
					<?php // AI Provider ?>
					<?php // ------------------------------------------------------------------ ?>
					<div class="sai-settings-section">
						<h2 class="sai-settings-section-title"><?php esc_html_e( 'AI Provider', 'ariham-seoagent' ); ?></h2>
						<div class="sai-card" style="margin-bottom:16px">
							<div class="sai-card-body">
								<div class="sai-field">
									<span class="sai-field-label"><?php esc_html_e( 'Provider', 'ariham-seoagent' ); ?></span>
									<div class="sai-field-control">
										<fieldset>
											<label style="display:block;margin-bottom:8px">
												<input type="radio" name="ai_provider" value="gemini" <?php checked( $ai_provider, 'gemini' ); ?>>
												<?php esc_html_e( 'Gemini (Google AI)', 'ariham-seoagent' ); ?>
											</label>
											<label style="display:block;margin-bottom:8px">
												<input type="radio" name="ai_provider" value="openai" <?php checked( $ai_provider, 'openai' ); ?>>
												<?php esc_html_e( 'OpenAI-compatible (default or custom endpoint)', 'ariham-seoagent' ); ?>
											</label>
											<label style="display:block">
												<input type="radio" name="ai_provider" value="auto" <?php checked( $ai_provider, 'auto' ); ?>>
												<?php esc_html_e( 'Auto (try Gemini first, fall back to OpenAI, then rule-based)', 'ariham-seoagent' ); ?>
											</label>
										</fieldset>
									</div>
								</div>
								<div class="sai-field" style="margin-top:16px">
									<label class="sai-field-label" for="gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<div class="sai-key-input-wrap">
											<input type="password" id="gemini_api_key" name="gemini_api_key" value="" class="regular-text" autocomplete="new-password"
												placeholder="<?php echo $gemini_has_key ? esc_attr__( 'Key saved — enter a new one to replace it', 'ariham-seoagent' ) : esc_attr__( 'Enter Gemini API key', 'ariham-seoagent' ); ?>">
											<button type="button" class="sai-key-reveal" aria-label="<?php esc_attr_e( 'Show/hide', 'ariham-seoagent' ); ?>">&#128065;</button>
										</div>
										<?php if ( $gemini_has_key ) : ?>
											<span class="sai-badge b-success" style="margin-top:6px;display:inline-block"><?php esc_html_e( 'Key saved', 'ariham-seoagent' ); ?></span>
										<?php endif; ?>
										<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep existing key.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
								<div class="sai-field">
									<label class="sai-field-label" for="openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<div class="sai-key-input-wrap">
											<input type="password" id="openai_api_key" name="openai_api_key" value="" class="regular-text" autocomplete="new-password"
												placeholder="<?php echo $openai_has_key ? esc_attr__( 'Key saved — enter a new one to replace it', 'ariham-seoagent' ) : esc_attr__( 'Enter API key', 'ariham-seoagent' ); ?>">
											<button type="button" class="sai-key-reveal" aria-label="<?php esc_attr_e( 'Show/hide', 'ariham-seoagent' ); ?>">&#128065;</button>
										</div>
										<?php if ( $openai_has_key ) : ?>
											<span class="sai-badge b-success" style="margin-top:6px;display:inline-block"><?php esc_html_e( 'Key saved', 'ariham-seoagent' ); ?></span>
										<?php endif; ?>
										<p class="description"><?php esc_html_e( 'Stored encrypted. Leave blank to keep existing key.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<?php // ------------------------------------------------------------------ ?>
					<?php // Content & Analysis ?>
					<?php // ------------------------------------------------------------------ ?>
					<div class="sai-settings-section">
						<h2 class="sai-settings-section-title"><?php esc_html_e( 'Content &amp; Analysis', 'ariham-seoagent' ); ?></h2>
						<div class="sai-card" style="margin-bottom:16px">
							<div class="sai-card-body">
								<div class="sai-field">
									<label class="sai-field-label" for="score_target"><?php esc_html_e( 'Score Target', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<input type="number" id="score_target" name="score_target" value="<?php echo esc_attr( (string) $score_target ); ?>" min="1" max="100" style="width:80px">
										<p class="description"><?php esc_html_e( 'Posts scoring below this threshold (1–100) are queued for improvement.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
								<div class="sai-field">
									<span class="sai-field-label"><?php esc_html_e( 'Post Types to Scan', 'ariham-seoagent' ); ?></span>
									<div class="sai-field-control">
										<?php
										$all_post_types   = get_post_types( array( 'public' => true ), 'objects' );
										$saved_post_types = (array) get_option( 'ariham_seoagent_post_types', array( 'post' ) );
										foreach ( $all_post_types as $pt ) :
											if ( 'attachment' === $pt->name ) {
												continue;
											}
											?>
											<label style="display:block;margin-bottom:6px">
												<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $saved_post_types, true ) ); ?>>
												<?php echo esc_html( $pt->labels->singular_name ); ?>
												<code style="font-size:11px;color:#888;margin-left:4px"><?php echo esc_html( $pt->name ); ?></code>
											</label>
										<?php endforeach; ?>
										<p class="description"><?php esc_html_e( 'Only selected post types are included in scans and scoring.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<?php // ------------------------------------------------------------------ ?>
					<?php // Autopilot ?>
					<?php // ------------------------------------------------------------------ ?>
					<div class="sai-settings-section">
						<h2 class="sai-settings-section-title"><?php esc_html_e( 'Autopilot Mode', 'ariham-seoagent' ); ?></h2>
						<div class="sai-card" style="margin-bottom:16px">
							<div class="sai-card-body">
								<div class="sai-toggle-wrap">
									<label class="sai-toggle">
										<input type="checkbox" name="autopilot_enabled" value="1" <?php checked( $autopilot ); ?> data-autopilot-toggle>
										<span class="sai-toggle-slider"></span>
									</label>
									<div class="sai-toggle-info">
										<strong><?php esc_html_e( 'Enable Autopilot', 'ariham-seoagent' ); ?></strong>
										<span><?php esc_html_e( 'Allow the agent to apply safe, high-confidence changes automatically during scheduled analysis.', 'ariham-seoagent' ); ?></span>
									</div>
								</div>
								<div class="sai-field" style="margin-top:16px">
									<label class="sai-field-label" for="autopilot_max_daily"><?php esc_html_e( 'Max Changes Per Day', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<input type="number" id="autopilot_max_daily" name="autopilot_max_daily" value="<?php echo esc_attr( (string) $max_daily ); ?>" min="1" max="50" style="width:80px">
										<p class="description"><?php esc_html_e( 'Hard safety limit. Recommended: 5.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
								<div class="sai-field">
									<label class="sai-field-label" for="autopilot_min_confidence"><?php esc_html_e( 'Minimum Confidence', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<input type="number" id="autopilot_min_confidence" name="autopilot_min_confidence" value="<?php echo esc_attr( (string) $min_confidence ); ?>" min="0.1" max="1.0" step="0.05" style="width:80px">
										<p class="description"><?php esc_html_e( 'Only apply changes at or above this score (0.0–1.0). Recommended: 0.70.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<?php // ------------------------------------------------------------------ ?>
					<?php // Maintenance ?>
					<?php // ------------------------------------------------------------------ ?>
					<div class="sai-settings-section">
						<h2 class="sai-settings-section-title"><?php esc_html_e( 'Maintenance', 'ariham-seoagent' ); ?></h2>
						<div class="sai-card" style="margin-bottom:16px">
							<div class="sai-card-body">
								<div class="sai-field">
									<label class="sai-field-label" for="log_retention_days"><?php esc_html_e( 'Log Retention (days)', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<input type="number" id="log_retention_days" name="log_retention_days" value="<?php echo esc_attr( (string) $log_retention ); ?>" min="7" max="730" style="width:80px">
										<p class="description"><?php esc_html_e( 'Log entries older than this many days are deleted automatically.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
								<div class="sai-field" style="margin-top:16px">
									<label class="sai-field-label" for="email_address"><?php esc_html_e( 'Report Email Address', 'ariham-seoagent' ); ?></label>
									<div class="sai-field-control">
										<input type="email" id="email_address" name="email_address" value="<?php echo esc_attr( $email_address ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email', '' ) ); ?>">
										<p class="description"><?php esc_html_e( 'Destination for daily and weekly reports. Defaults to the WordPress admin email when left blank.', 'ariham-seoagent' ); ?></p>
									</div>
								</div>
								<div class="sai-toggle-wrap" style="margin-top:12px">
									<label class="sai-toggle">
										<input type="checkbox" name="email_reports" value="1" <?php checked( $email_reports ); ?>>
										<span class="sai-toggle-slider"></span>
									</label>
									<div class="sai-toggle-info">
										<strong><?php esc_html_e( 'Email Daily Reports', 'ariham-seoagent' ); ?></strong>
										<span><?php esc_html_e( 'Send a rich HTML daily SEO report and a weekly rankings summary to the address above.', 'ariham-seoagent' ); ?></span>
									</div>
								</div>
								<div class="sai-toggle-wrap" style="margin-top:16px">
									<label class="sai-toggle">
										<input type="checkbox" name="cwv_enabled" value="1" <?php checked( $cwv_enabled ); ?>>
										<span class="sai-toggle-slider"></span>
									</label>
									<div class="sai-toggle-info">
										<strong><?php esc_html_e( 'Fetch Core Web Vitals (Google PageSpeed Insights)', 'ariham-seoagent' ); ?></strong>
										<span><?php esc_html_e( 'Opt in to a weekly background job that sends your published post URLs to the Google PageSpeed Insights API to retrieve Core Web Vitals data. This contacts a third-party Google service and is turned off by default. No PageSpeed requests are made unless you enable this.', 'ariham-seoagent' ); ?></span>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div style="margin-top:8px">
						<button type="submit" class="sai-btn sai-btn-primary"><span class="btn-label"><?php esc_html_e( 'Save Settings', 'ariham-seoagent' ); ?></span></button>
					</div>
				</form>
			</div>
		</div>

		<?php if ( $is_connected && ! $sitekit_active ) : ?>
			<?php ob_start(); ?>
		(function($) {
			'use strict';
			$(function() {
				var nonce = '<?php echo esc_js( wp_create_nonce( 'ariham_seoagent_property_list' ) ); ?>';

				function escHtml(str) {
					return String(str)
						.replace(/&/g, '&amp;')
						.replace(/</g, '&lt;')
						.replace(/>/g, '&gt;')
						.replace(/"/g, '&quot;');
				}

				function fallbackInput(name, val) {
					return '<input type="text" name="' + escHtml(name) + '" id="' + escHtml(name) + '" value="' + escHtml(val) + '" class="regular-text" />';
				}

				function spinnerHtml(msg) {
					return '<span class="spinner is-active" style="float:none;margin-top:0;"></span>' +
						'<span style="vertical-align:middle;margin-left:6px;font-size:13px;color:#50575e;">' + escHtml(msg) + '</span>';
				}

				function loadGSCSites() {
					var $wrap = $('#seo-gsc-property-wrap');
					var currentVal = <?php echo wp_json_encode( $gsc_site_url ); ?>;
					$wrap.html(spinnerHtml('<?php echo esc_js( __( 'Loading Search Console properties…', 'ariham-seoagent' ) ); ?>'));

					$.post(ajaxurl, { action: 'ariham_seoagent_list_gsc_sites', _ajax_nonce: nonce })
						.done(function(response) {
							if (response.success && response.data && response.data.length) {
								var html = '<select name="gsc_site_url" id="gsc_site_url" class="regular-text">';
								html += '<option value="">&mdash; <?php echo esc_js( __( 'Select property', 'ariham-seoagent' ) ); ?> &mdash;</option>';
								$.each(response.data, function(_, site) {
									var url = site.siteUrl || '';
									var sel = (url === currentVal) ? ' selected="selected"' : '';
									html += '<option value="' + escHtml(url) + '"' + sel + '>' + escHtml(url) + '</option>';
								});
								html += '</select>';
								html += '<p class="description"><?php echo esc_js( __( 'Select your verified Search Console property.', 'ariham-seoagent' ) ); ?></p>';
								$wrap.html(html);
							} else {
								var err = (response.data && typeof response.data === 'string') ? response.data : '';
								$wrap.html(
									fallbackInput('gsc_site_url', currentVal) +
									'<p class="description"' + (err ? ' style="color:#c5221f;"' : '') + '>' +
									(err ? '<?php echo esc_js( __( 'Could not load properties: ', 'ariham-seoagent' ) ); ?>' + escHtml(err)
										: '<?php echo esc_js( __( 'Full URL, sc-domain:example.com, or bare domain.', 'ariham-seoagent' ) ); ?>') +
									'</p>'
								);
							}
						})
						.fail(function() {
							$wrap.html(
								fallbackInput('gsc_site_url', currentVal) +
								'<p class="description"><?php echo esc_js( __( 'Full URL, sc-domain:example.com, or bare domain.', 'ariham-seoagent' ) ); ?></p>'
							);
						});
				}

				function loadGA4Properties() {
					var $wrap = $('#seo-ga4-property-wrap');
					var currentVal = <?php echo wp_json_encode( $ga4_property_id ); ?>;
					$wrap.html(spinnerHtml('<?php echo esc_js( __( 'Loading Analytics properties…', 'ariham-seoagent' ) ); ?>'));

					$.post(ajaxurl, { action: 'ariham_seoagent_list_ga4_properties', _ajax_nonce: nonce })
						.done(function(response) {
							if (response.success && response.data && response.data.length) {
								var html = '<select name="ga4_property_id" id="ga4_property_id" class="regular-text">';
								html += '<option value="">&mdash; <?php echo esc_js( __( 'Select property', 'ariham-seoagent' ) ); ?> &mdash;</option>';
								$.each(response.data, function(_, prop) {
									var sel = (prop.id === currentVal) ? ' selected="selected"' : '';
									html += '<option value="' + escHtml(prop.id) + '"' + sel + '>' + escHtml(prop.name) + '</option>';
								});
								html += '</select>';
								html += '<p class="description"><?php echo esc_js( __( 'Select your GA4 Analytics property.', 'ariham-seoagent' ) ); ?></p>';
								$wrap.html(html);
							} else {
								var err = (response.data && typeof response.data === 'string') ? response.data : '';
								var hint = err
									? '<?php echo esc_js( __( 'Could not load properties: ', 'ariham-seoagent' ) ); ?>' + escHtml(err) +
										' &mdash; <a href="https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com" target="_blank"><?php echo esc_js( __( 'Enable Analytics Admin API', 'ariham-seoagent' ) ); ?></a>'
									: '<?php echo esc_js( __( 'Numeric GA4 property ID.', 'ariham-seoagent' ) ); ?>';
								$wrap.html(
									fallbackInput('ga4_property_id', currentVal) +
									'<p class="description"' + (err ? ' style="color:#c5221f;"' : '') + '>' + hint + '</p>'
								);
							}
						})
						.fail(function() {
							$wrap.html(
								fallbackInput('ga4_property_id', currentVal) +
								'<p class="description"><?php echo esc_js( __( 'Numeric GA4 property ID.', 'ariham-seoagent' ) ); ?></p>'
							);
						});
				}

				loadGSCSites();
				loadGA4Properties();
			});
		})(jQuery);
			<?php wp_add_inline_script( 'ariham-seoagent-admin', ob_get_clean() ); ?>
		<?php endif; ?>
		<?php
	}


	// -------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------

	private function format_signals( array $signals ) {
		$labels = array(
			// Original signals.
			'content_refresh_needed'  => __( 'Content refresh needed', 'ariham-seoagent' ),
			'title_meta_optimization' => __( 'Title/meta optimization', 'ariham-seoagent' ),
			'intent_mismatch'         => __( 'Intent mismatch', 'ariham-seoagent' ),
			'declining_performance'   => __( 'Declining performance', 'ariham-seoagent' ),
			'thin_content'            => __( 'Thin content', 'ariham-seoagent' ),
			'missing_meta_basics'     => __( 'Missing meta basics', 'ariham-seoagent' ),
			// New v3.0 signals.
			'page_two_opportunity'    => __( 'Page-2 opportunity', 'ariham-seoagent' ),
			'ctr_anomaly'             => __( 'CTR below expected', 'ariham-seoagent' ),
			'cannibalization_risk'    => __( 'Keyword cannibalization', 'ariham-seoagent' ),
			'content_decay'           => __( 'Content decay', 'ariham-seoagent' ),
			'orphan_page'             => __( 'Orphan page', 'ariham-seoagent' ),
			'missing_schema'          => __( 'Missing schema', 'ariham-seoagent' ),
			'weak_engagement'         => __( 'Weak engagement', 'ariham-seoagent' ),
			'title_ctr_mismatch'      => __( 'Title/CTR mismatch', 'ariham-seoagent' ),
			'missing_faq'             => __( 'FAQ opportunity', 'ariham-seoagent' ),
			'index_anomaly'           => __( 'Index anomaly', 'ariham-seoagent' ),
		);

		$active = array();
		foreach ( $signals as $key => $enabled ) {
			if ( $enabled && isset( $labels[ $key ] ) ) {
				$active[] = '<span class="seo-agent-pill low">' . esc_html( $labels[ $key ] ) . '</span>';
			}
		}

		return empty( $active )
			? '<span class="seo-agent-muted">' . esc_html__( 'No active signals.', 'ariham-seoagent' ) . '</span>'
			: implode( ' ', $active );
	}

	private function render_notice( $notice ) {
		$map = array(
			'analysis_complete'        => array( 'success', __( 'Analysis completed.', 'ariham-seoagent' ) ),
			'analysis_scheduled'       => array( 'info', __( 'Analysis scheduled. WP-Cron will run it shortly; reload this page in a minute or two for results.', 'ariham-seoagent' ) ),
			'fix_applied'              => array( 'success', __( 'Safe metadata fix applied.', 'ariham-seoagent' ) ),
			'apply_failed'             => array( 'error', __( 'Could not apply fix. Check recommendation risk and payload.', 'ariham-seoagent' ) ),
			'invalid_input'            => array( 'error', __( 'Invalid input provided.', 'ariham-seoagent' ) ),
			'recommendation_not_found' => array( 'error', __( 'Recommendation no longer exists.', 'ariham-seoagent' ) ),
			'settings_saved'           => array( 'success', __( 'Settings saved.', 'ariham-seoagent' ) ),
			'connection_tested'        => array( 'info', __( 'Connection test completed. See results below.', 'ariham-seoagent' ) ),
			'rollback_done'            => array( 'success', __( 'Rollback applied. Previous metadata restored.', 'ariham-seoagent' ) ),
			'rollback_failed'          => array( 'error', __( 'Rollback failed. No backup found for this post.', 'ariham-seoagent' ) ),
			'google_disconnected'      => array( 'success', __( 'Google account disconnected.', 'ariham-seoagent' ) ),
			'google_connected'         => array( 'success', __( 'Google account connected.', 'ariham-seoagent' ) ),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		list( $type, $message ) = $map[ $notice ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private function render_connection_results( array $result ) {
		$services = array(
			'gsc'       => __( 'Search Console', 'ariham-seoagent' ),
			'analytics' => __( 'Google Analytics', 'ariham-seoagent' ),
		);

		echo '<div style="margin:16px 0;max-width:700px;">';
		foreach ( $services as $key => $label ) {
			$row     = isset( $result[ $key ] ) && is_array( $result[ $key ] ) ? $result[ $key ] : array();
			$success = ! empty( $row['success'] );
			$message = isset( $row['message'] ) ? (string) $row['message'] : '';
			$bg      = $success ? '#edf7ed' : '#fcf0f1';
			$border  = $success ? '#1e8e3e' : '#b32d2e';
			$icon    = $success ? '&#10003;' : '&#10007;';

			echo '<div style="background:' . esc_attr( $bg ) . ';border:1px solid ' . esc_attr( $border ) . ';border-radius:4px;padding:10px 14px;margin-bottom:8px;">';
			echo '<strong>' . wp_kses_post( $icon . ' ' . $label ) . '</strong>';
			if ( $message ) {
				echo '<span style="margin-left:12px;font-size:13px;">' . esc_html( $message ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
