<?php
/**
 * SEO Opportunities admin page.
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ariham_SEOAgent_Opportunities_Page {

	/** @var Ariham_SEOAgent_Decision_Engine */
	private $decision_engine;

	public function __construct( Ariham_SEOAgent_Decision_Engine $decision_engine ) {
		$this->decision_engine = $decision_engine;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'ariham-seoagent' ) );
		}

		$autopilot   = (bool) get_option( 'ariham_seoagent_autopilot_enabled', false );
		$filter_type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$filter_risk = isset( $_GET['risk'] ) ? sanitize_text_field( wp_unslash( $_GET['risk'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$paged       = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$per_page    = 20;

		$args = array(
			'status' => Ariham_SEOAgent_DB_Manager::STATUS_PENDING,
			'limit'  => $per_page,
			'offset' => ( $paged - 1 ) * $per_page,
		);
		if ( '' !== $filter_type ) {
			$args['decision_type'] = $filter_type;
		}
		if ( '' !== $filter_risk ) {
			$args['risk_level'] = $filter_risk;
		}

		$decisions = Ariham_SEOAgent_DB_Manager::get_decisions( $args );
		$total     = Ariham_SEOAgent_DB_Manager::count_decisions( Ariham_SEOAgent_DB_Manager::STATUS_PENDING );

		?>
		<div class="wrap sai-page">

			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow">
						<span class="sai-dot pulsing-green"></span>
						<?php esc_html_e( 'Ariham SEOAgent', 'ariham-seoagent' ); ?>
					</p>
					<h1 class="sai-header-title">
						<?php esc_html_e( 'Opportunities', 'ariham-seoagent' ); ?>
						<span class="sai-badge b-warning" style="font-size:14px;vertical-align:middle;margin-left:8px"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
					</h1>
				</div>
				<div class="sai-header-actions">
					<?php if ( $autopilot ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'ariham_seoagent_bulk_apply_safe', '_wpnonce' ); ?>
						<input type="hidden" name="action" value="ariham_seoagent_bulk_apply_safe">
						<button type="submit" class="sai-btn sai-btn-success"
							onclick="return confirm('<?php echo esc_js( __( 'Apply all safe opportunities now?', 'ariham-seoagent' ) ); ?>')">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
							<span class="btn-label"><?php esc_html_e( 'Apply All Safe', 'ariham-seoagent' ); ?></span>
						</button>
					</form>
					<?php endif; ?>
					<button id="seo-agent-scan-btn" class="sai-btn sai-btn-primary">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803M15.803 15.803A7.5 7.5 0 1 0 5.196 5.196"/></svg>
						<span class="btn-label" id="seo-agent-scan-label"><?php esc_html_e( 'Scan for Opportunities', 'ariham-seoagent' ); ?></span>
					</button>
				</div>
			</div>

			<div class="sai-body">

				<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Changes applied successfully.', 'ariham-seoagent' ); ?></p></div>
				<?php endif; ?>

				<?php if ( $autopilot ) : ?>
				<div class="sai-autopilot-bar ap-on">
					<div class="sai-ap-indicator">
						<div class="sai-ap-pulse"></div>
						<div>
							<div class="sai-ap-label">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>
								<?php esc_html_e( 'Autopilot ON', 'ariham-seoagent' ); ?>
							</div>
							<div class="sai-ap-sub"><?php esc_html_e( 'Safe opportunities can be applied directly from this page.', 'ariham-seoagent' ); ?></div>
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
								echo esc_html(
									sprintf(
										/* translators: %d: number of pending decisions. */
										__( '%d pending opportunities ready for review. Enable Autopilot in Settings to apply safe fixes directly.', 'ariham-seoagent' ),
										$total
									)
								);
								?>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<div class="sai-scan-progress" id="seo-agent-scan-wrap" style="display:none">
					<div class="sai-scan-bar-track"><div class="sai-scan-bar-fill" id="seo-agent-scan-bar"></div></div>
					<p class="sai-scan-status" id="seo-agent-scan-status"></p>
				</div>

				<?php $this->render_filters( $filter_type, $filter_risk ); ?>

				<?php if ( empty( $decisions ) ) : ?>
					<div class="sai-empty" id="seo-agent-empty-state">
						<div class="sai-empty-icon">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
						</div>
						<h3><?php esc_html_e( 'No opportunities yet', 'ariham-seoagent' ); ?></h3>
						<p><?php esc_html_e( 'Scan your site so the agent can analyze every page, score it, and surface prioritized SEO recommendations.', 'ariham-seoagent' ); ?></p>
						<button id="seo-agent-scan-btn-empty" class="sai-btn sai-btn-primary">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803M15.803 15.803A7.5 7.5 0 1 0 5.196 5.196"/></svg>
							<span class="btn-label"><?php esc_html_e( 'Scan My Site Now', 'ariham-seoagent' ); ?></span>
						</button>
					</div>
				<?php else : ?>
					<?php $this->render_decisions( $decisions, $autopilot ); ?>
					<?php $this->render_pagination( $total, $per_page, $paged ); ?>
				<?php endif; ?>

			</div><!-- .sai-body -->
		</div><!-- .sai-page -->

		<?php $this->render_scan_js(); ?>
		<?php
	}

	// -------------------------------------------------------------------
	// Decision cards
	// -------------------------------------------------------------------

	private function render_decisions( array $decisions, bool $autopilot = false ) {
		$admin_post_url = admin_url( 'admin-post.php' );

		foreach ( $decisions as $dec ) {
			$post_id    = (int) $dec['post_id'];
			$dec_id     = (int) $dec['id'];
			$post       = get_post( $post_id );
			$title      = $post instanceof WP_Post ? $post->post_title : "(#{$post_id})";
			$confidence = round( (float) $dec['confidence'] * 100 );
			$conf_level = $confidence >= 80 ? 'high' : ( $confidence >= 50 ? 'med' : 'low' );
			$risk       = (string) ( $dec['risk_level'] ?? '' );
			$is_safe    = 'safe' === $risk;
			$dec_type   = (string) ( $dec['decision_type'] ?? '' );
			$reasoning  = (string) ( $dec['reasoning'] ?? '' );
			$field      = (string) ( $dec['field'] ?? '' );
			$cur_val    = (string) ( $dec['current_value'] ?? '' );
			$prop_val   = (string) ( $dec['proposed_value'] ?? '' );
			$impact     = (string) ( $dec['expected_impact'] ?? '' );

			$icon_class = $this->get_icon_class( $dec_type );
			$type_badge = $this->get_type_badge_class( $dec_type );
			$risk_badge = $is_safe ? 'b-neutral' : 'b-danger';
			$risk_label = $is_safe ? __( 'Safe', 'ariham-seoagent' ) : __( 'Risky', 'ariham-seoagent' );

			echo '<div class="sai-decision">';

			// Header row.
			echo '<div class="sai-decision-header">';

			echo '<div class="sai-decision-type-icon ' . esc_attr( $icon_class ) . '">';
			echo $this->get_type_svg( $dec_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup.
			echo '</div>';

			echo '<div class="sai-decision-info">';
			echo '<p class="sai-decision-post">';
			echo '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( $title ) . '</a>';
			if ( $field !== '' ) {
				echo ' <span style="color:var(--sai-text-muted);font-size:12px">— ' . esc_html( $field ) . '</span>';
			}
			echo '</p>';
			if ( $reasoning !== '' ) {
				echo '<p class="sai-decision-reason">' . esc_html( wp_trim_words( $reasoning, 18 ) ) . '</p>';
			}
			echo '<div class="sai-decision-tags">';
			echo '<span class="sai-badge ' . esc_attr( $type_badge ) . '">' . esc_html( $dec_type ) . '</span>';
			echo '<span class="sai-badge ' . esc_attr( $risk_badge ) . '">' . esc_html( $risk_label ) . '</span>';
			if ( $impact !== '' ) {
				echo '<span class="sai-badge b-primary">' . esc_html( $impact ) . '</span>';
			}
			echo '<span class="sai-conf"><span class="sai-conf-bar"><span class="sai-conf-fill ' . esc_attr( $conf_level ) . '" style="width:' . esc_attr( $confidence ) . '%"></span></span> ' . esc_html( $confidence ) . '%</span>';
			echo '</div>';
			echo '</div>';

			echo '<div class="sai-decision-actions">';
			if ( $autopilot && $is_safe ) {
				echo '<button class="sai-btn sai-btn-success sai-btn-sm" data-decision-action="approve" data-decision-id="' . esc_attr( $dec_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'ariham_seoagent_decision_' . $dec_id ) ) . '"><span class="btn-label">' . esc_html__( 'Apply', 'ariham-seoagent' ) . '</span></button>';
			} else {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=ariham-seoagent-approvals&decision_id=' . $dec_id ) ) . '" class="sai-btn sai-btn-success sai-btn-sm"><span class="btn-label">' . esc_html__( 'Review', 'ariham-seoagent' ) . '</span></a>';
			}
			echo '<button class="sai-btn sai-btn-danger sai-btn-sm" data-decision-action="reject" data-decision-id="' . esc_attr( $dec_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'ariham_seoagent_decision_' . $dec_id ) ) . '"><span class="btn-label">' . esc_html__( 'Dismiss', 'ariham-seoagent' ) . '</span></button>';
			echo '</div>';

			echo '</div>';// .sai-decision-header

			// Expandable body with diff.
			echo '<div class="sai-decision-body" style="display:none">';
			if ( $cur_val !== '' || $prop_val !== '' ) {
				echo '<div class="sai-timeline-diff">';
				if ( $cur_val !== '' ) {
					echo '<div class="sai-diff-before"><span class="sai-diff-label">' . esc_html__( 'Before', 'ariham-seoagent' ) . '</span>' . esc_html( $cur_val ) . '</div>';
				}
				if ( $prop_val !== '' ) {
					echo '<div class="sai-diff-after"><span class="sai-diff-label">' . esc_html__( 'After', 'ariham-seoagent' ) . '</span>' . esc_html( $prop_val ) . '</div>';
				}
				echo '</div>';
			}
			if ( $reasoning !== '' ) {
				echo '<div class="sai-decision-proposed"><strong>' . esc_html__( 'Reasoning', 'ariham-seoagent' ) . '</strong> ' . esc_html( $reasoning ) . '</div>';
			}
			echo '</div>';// .sai-decision-body

			echo '</div>';// .sai-decision
		}

		// Hidden forms for AJAX-backed approve/reject actions.
		echo '<form id="sai-decision-form" method="post" action="' . esc_url( $admin_post_url ) . '" style="display:none">';
		echo '<input type="hidden" name="action" value="ariham_seoagent_decision">';
		echo '<input type="hidden" name="seo_action" id="sai-decision-seo-action" value="">';
		echo '<input type="hidden" name="decision_id" id="sai-decision-id" value="">';
		echo '<input type="hidden" name="_wpnonce" id="sai-decision-nonce" value="">';
		echo '<input type="hidden" name="redirect_to" value="ariham-seoagent-opportunities">';
		echo '</form>';
	}

	// -------------------------------------------------------------------
	// Filters
	// -------------------------------------------------------------------

	private function render_filters( $filter_type, $filter_risk ) {
		$types = array(
			''                  => __( 'All Types', 'ariham-seoagent' ),
			'meta_update'       => __( 'Meta Update', 'ariham-seoagent' ),
			'content_expansion' => __( 'Content Expansion', 'ariham-seoagent' ),
			'schema_update'     => __( 'Schema', 'ariham-seoagent' ),
			'page_two_push'     => __( 'Page 2 Push', 'ariham-seoagent' ),
			'monitor_decline'   => __( 'Decline Monitor', 'ariham-seoagent' ),
		);

		echo '<form method="get" class="sai-filters">';
		echo '<input type="hidden" name="page" value="ariham-seoagent-opportunities">';

		echo '<label>' . esc_html__( 'Type', 'ariham-seoagent' ) . '<select name="type">';
		foreach ( $types as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $filter_type, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';

		echo '<label>' . esc_html__( 'Risk', 'ariham-seoagent' ) . '<select name="risk">';
		echo '<option value=""' . selected( $filter_risk, '', false ) . '>' . esc_html__( 'All Risk Levels', 'ariham-seoagent' ) . '</option>';
		echo '<option value="safe"' . selected( $filter_risk, 'safe', false ) . '>' . esc_html__( 'Safe', 'ariham-seoagent' ) . '</option>';
		echo '<option value="risky"' . selected( $filter_risk, 'risky', false ) . '>' . esc_html__( 'Risky', 'ariham-seoagent' ) . '</option>';
		echo '</select></label>';

		echo '<button type="submit" class="sai-btn sai-btn-ghost sai-btn-sm"><span class="btn-label">' . esc_html__( 'Filter', 'ariham-seoagent' ) . '</span></button>';
		echo '</form>';
	}

	// -------------------------------------------------------------------
	// Pagination
	// -------------------------------------------------------------------

	private function render_pagination( $total, $per_page, $paged ) {
		$pages = (int) ceil( $total / $per_page );
		if ( $pages <= 1 ) {
			return;
		}

		echo '<div class="sai-pagination">';
		echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
			)
		);
		echo '</div>';
	}

	// -------------------------------------------------------------------
	// Helpers: icon class + SVG per decision type
	// -------------------------------------------------------------------

	private function get_icon_class( $dec_type ) {
		$map = array(
			'meta_update'          => 'dt-meta',
			'meta_description'     => 'dt-meta',
			'internal_link_needed' => 'dt-link',
			'schema_update'        => 'dt-schema',
			'content_expansion'    => 'dt-content',
			'content_update'       => 'dt-content',
			'alt_text'             => 'dt-image',
			'redirect'             => 'dt-redirect',
		);
		return $map[ $dec_type ] ?? 'dt-default';
	}

	private function get_type_badge_class( $dec_type ) {
		$map = array(
			'meta_update'          => 'b-primary',
			'meta_description'     => 'b-primary',
			'internal_link_needed' => 'b-neutral',
			'schema_update'        => 'b-warning',
			'content_expansion'    => 'b-primary',
			'content_update'       => 'b-primary',
			'alt_text'             => 'b-neutral',
			'redirect'             => 'b-danger',
		);
		return $map[ $dec_type ] ?? 'b-neutral';
	}

	private function get_type_svg( $dec_type ) {
		switch ( $dec_type ) {
			case 'internal_link_needed':
				return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>';
			case 'schema_update':
			case 'content_expansion':
			case 'content_update':
				return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>';
			default:
				return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>';
		}
	}

	// -------------------------------------------------------------------
	// Scan JS
	// -------------------------------------------------------------------

	private function render_scan_js() {
		$nonce = wp_create_nonce( 'ariham_seoagent_analyze_batch' );
		?>
		<?php ob_start(); ?>
		(function () {
			var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
			var ajaxUrl    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var adminPost  = <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;

			var btn        = document.getElementById('seo-agent-scan-btn');
			var btnEmpty   = document.getElementById('seo-agent-scan-btn-empty');
			var label      = document.getElementById('seo-agent-scan-label');
			var wrap       = document.getElementById('seo-agent-scan-wrap');
			var bar        = document.getElementById('seo-agent-scan-bar');
			var status     = document.getElementById('seo-agent-scan-status');

			function startScan() {
				setScanning(true);
				runBatch(0);
			}

			function setScanning(active) {
				if (btn)      btn.disabled      = active;
				if (btnEmpty) btnEmpty.disabled  = active;
				if (label)    label.textContent  = active
					? <?php echo wp_json_encode( __( 'Scanning…', 'ariham-seoagent' ) ); ?>
					: <?php echo wp_json_encode( __( 'Scan for Opportunities', 'ariham-seoagent' ) ); ?>;
				if (wrap) wrap.style.display = active ? 'block' : 'none';
			}

			function updateProgress(pct, text) {
				if (bar)    bar.style.width   = pct + '%';
				if (status) status.textContent = text;
			}

			function runBatch(offset) {
				var body = new FormData();
				body.append('action',      'ariham_seoagent_analyze_batch');
				body.append('_ajax_nonce', nonce);
				body.append('offset',      offset);

				fetch(ajaxUrl, { method: 'POST', body: body })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (!res.success) {
							showError(res.data || <?php echo wp_json_encode( __( 'Scan failed. Please try again.', 'ariham-seoagent' ) ); ?>);
							return;
						}
						var d    = res.data;
						var pct  = d.percent || 0;
						var text = d.done
							? <?php echo wp_json_encode( __( 'Scan complete!', 'ariham-seoagent' ) ); ?> + ' ' +
								d.with_recs + ' ' + <?php echo wp_json_encode( __( 'new recommendation(s) found. Reloading…', 'ariham-seoagent' ) ); ?>
							: <?php echo wp_json_encode( __( 'Scanning', 'ariham-seoagent' ) ); ?> + ' ' + pct + '% — ' + (d.current_title || '');
						updateProgress(pct, text);
						if (d.done) {
							setTimeout(function () { window.location.reload(); }, 1800);
						} else {
							runBatch(d.processed);
						}
					})
					.catch(function () {
						showError(<?php echo wp_json_encode( __( 'Network error. Please try again.', 'ariham-seoagent' ) ); ?>);
					});
			}

			function showError(msg) {
				setScanning(false);
				updateProgress(0, '');
				if (status) {
					status.style.color = 'var(--cl-danger)';
					status.textContent  = msg;
					if (wrap) wrap.style.display = 'block';
				}
			}

			if (btn)      btn.addEventListener('click',      startScan);
			if (btnEmpty) btnEmpty.addEventListener('click', startScan);

			// Decision action buttons (approve/reject via form submit).
			document.addEventListener('click', function (e) {
				var target = e.target.closest('[data-decision-action]');
				if (!target) return;

				var decAction = target.getAttribute('data-decision-action');
				var decId     = target.getAttribute('data-decision-id');
				var decNonce  = target.getAttribute('data-nonce');

				if (!decAction || !decId) return;

				if (decAction === 'reject') {
					if (!confirm(<?php echo wp_json_encode( __( 'Dismiss this opportunity?', 'ariham-seoagent' ) ); ?>)) {
						return;
					}
				}

				var form = document.getElementById('sai-decision-form');
				if (!form) return;

				document.getElementById('sai-decision-seo-action').value = decAction;
				document.getElementById('sai-decision-id').value         = decId;
				document.getElementById('sai-decision-nonce').value      = decNonce;
				form.submit();
			});

			// Toggle decision body on header click.
			document.addEventListener('click', function (e) {
				var header = e.target.closest('.sai-decision-header');
				if (!header) return;
				if (e.target.closest('[data-decision-action]') || e.target.closest('a')) return;
				var body = header.nextElementSibling;
				if (body && body.classList.contains('sai-decision-body')) {
					body.style.display = (body.style.display === 'none' || body.style.display === '') ? 'block' : 'none';
				}
			});
		})();
		<?php wp_add_inline_script( 'ariham-seoagent-admin', ob_get_clean() ); ?>
		<?php
	}
}
