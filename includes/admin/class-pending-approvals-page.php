<?php
/**
 * Pending AI Decision Approvals admin page.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Pending_Approvals_Page {

	/** @var SEO_Agent_AI_Decision_Engine */
	private $decision_engine;

	/** @var SEO_Agent_AI_Fix_Executor */
	private $fix_executor;

	/** @var SEO_Agent_AI_Internal_Link_Engine */
	private $link_engine;

	public function __construct(
		SEO_Agent_AI_Decision_Engine $decision_engine,
		SEO_Agent_AI_Fix_Executor $fix_executor,
		SEO_Agent_AI_Internal_Link_Engine $link_engine
	) {
		$this->decision_engine = $decision_engine;
		$this->fix_executor    = $fix_executor;
		$this->link_engine     = $link_engine;
	}

	/**
	 * Apply all pending safe decisions at once (autopilot bulk action).
	 */
	public function handle_bulk_apply_safe() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-agent-ai' ) );
		}

		check_admin_referer( 'seo_agent_ai_bulk_apply_safe' );

		$safe_decisions = SEO_Agent_AI_DB_Manager::get_decisions(
			array(
				'status'     => SEO_Agent_AI_DB_Manager::STATUS_PENDING,
				'risk_level' => 'safe',
				'limit'      => 200,
			)
		);

		foreach ( $safe_decisions as $dec ) {
			$dec_id = (int) $dec['id'];
			$this->decision_engine->approve( $dec_id );
			$this->execute_decision( $dec_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=seo-agent-opportunities&updated=1' ) );
		exit;
	}

	/**
	 * Handle approve/reject POST actions.
	 * Called via admin_post_seo_agent_ai_decision_{action}.
	 */
	public function handle_action() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-agent-ai' ) );
		}

		$action      = sanitize_key( $_POST['seo_action'] ?? '' );
		$decision_id = absint( wp_unslash( $_POST['decision_id'] ?? 0 ) );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'seo_agent_ai_decision_' . $decision_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'seo-agent-ai' ) );
		}

		if ( 'approve' === $action ) {
			$this->decision_engine->approve( $decision_id );
			$this->execute_decision( $decision_id );
		} elseif ( 'reject' === $action ) {
			$this->decision_engine->reject( $decision_id );
		}

		$redirect_to = sanitize_key( $_POST['redirect_to'] ?? '' );
		$back_page   = ( '' !== $redirect_to ) ? $redirect_to : 'seo-agent-approvals';
		wp_safe_redirect( admin_url( 'admin.php?page=' . $back_page . '&updated=1' ) );
		exit;
	}

	/**
	 * Execute the actual change for an approved decision.
	 * Routes to the correct engine based on decision_type.
	 */
	private function execute_decision( $decision_id ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'seo_agent_ai_decisions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$dec = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $decision_id ), ARRAY_A );

		if ( ! $dec ) {
			return;
		}

		$post_id = (int) $dec['post_id'];
		$type    = $dec['decision_type'] ?? '';

		switch ( $type ) {
			case 'meta_update':
			case 'monitor_decline':
				// Reconstruct recommendation from stored decision fields.
				$field    = $dec['field'] ?? '';
				$value    = $dec['proposed_value'] ?? '';
				$proposed = array();
				if ( 'meta_title' === $field ) {
					$proposed['meta_title'] = $value;
				} elseif ( 'meta_description' === $field ) {
					$proposed['meta_description'] = $value;
				} else {
					// Try to decode JSON payload (multi-field decisions).
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						$proposed = $decoded;
					}
				}
				if ( ! empty( $proposed ) ) {
					$this->fix_executor->apply(
						$post_id,
						array(
							'type'       => $type,
							'risk'       => 'safe',
							'proposed'   => $proposed,
							'reason'     => $dec['reasoning'] ?? '',
							'confidence' => (float) ( $dec['confidence'] ?? 0.7 ),
						),
						'manual'
					);
				}
				break;

			case 'internal_link_needed':
				// Find other posts that can link to this post and insert up to 3 links.
				$this->link_engine->run_for_post( $post_id );
				break;

			case 'schema_update':
				// Schema is injected via wp_head when enabled — store a flag so schema engine activates for this post.
				update_post_meta( $post_id, '_seo_agent_ai_schema_approved', 1 );
				break;
		}

		// Mark as applied in DB.
		$this->decision_engine->mark_applied( $decision_id );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'seo-agent-ai' ) );
		}

		$single_id = isset( $_GET['decision_id'] ) ? (int) $_GET['decision_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$paged     = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$per_page  = 15;

		$total = SEO_Agent_AI_DB_Manager::count_decisions( SEO_Agent_AI_DB_Manager::STATUS_PENDING );

		?>
		<div class="wrap sai-page">

			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow">
						<span class="sai-dot pulsing-green"></span>
						<?php esc_html_e( 'SEO Agent AI', 'seo-agent-ai' ); ?>
					</p>
					<h1 class="sai-header-title">
						<?php esc_html_e( 'Pending Approvals', 'seo-agent-ai' ); ?>
						<?php if ( $total > 0 ) : ?>
						<span class="sai-badge b-warning" style="font-size:14px;vertical-align:middle;margin-left:8px"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
						<?php endif; ?>
					</h1>
				</div>
				<?php if ( $single_id === 0 && $total > 0 ) : ?>
				<div class="sai-header-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'seo_agent_ai_bulk_apply_safe', '_wpnonce' ); ?>
						<input type="hidden" name="action" value="seo_agent_ai_bulk_apply_safe">
						<button type="submit" class="sai-btn sai-btn-success sai-bulk-apply-safe"
							onclick="return confirm('<?php echo esc_js( __( 'Apply all safe decisions now?', 'seo-agent-ai' ) ); ?>')">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
							<span class="btn-label"><?php esc_html_e( 'Apply All Safe', 'seo-agent-ai' ); ?></span>
						</button>
					</form>
				</div>
				<?php endif; ?>
			</div>

			<div class="sai-body">

				<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Decision updated successfully.', 'seo-agent-ai' ) ; ?></p></div>
				<?php endif; ?>

				<?php
				if ( $single_id > 0 ) {
					$this->render_single_decision( $single_id );
				} else {
					$this->render_decisions_list( $paged, $per_page, $total );
				}
				?>

			</div><!-- .sai-body -->
		</div><!-- .sai-page -->
		<?php
	}

	// -------------------------------------------------------------------
	// Decisions list
	// -------------------------------------------------------------------

	private function render_decisions_list( $paged, $per_page, $total ) {
		$decisions = SEO_Agent_AI_DB_Manager::get_decisions(
			array(
				'status' => SEO_Agent_AI_DB_Manager::STATUS_PENDING,
				'limit'  => $per_page,
				'offset' => ( $paged - 1 ) * $per_page,
			)
		);

		if ( empty( $decisions ) ) {
			?>
			<div class="sai-empty">
				<div class="sai-empty-icon">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" width="16" height="16" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
				</div>
				<h3><?php esc_html_e( 'All caught up!', 'seo-agent-ai' ); ?></h3>
				<p><?php esc_html_e( 'No decisions pending. The agent will surface new recommendations after the next analysis run.', 'seo-agent-ai' ); ?></p>
			</div>
			<?php
			return;
		}

		echo '<p class="sai-page-desc">';
		echo esc_html(
			sprintf(
				/* translators: %d: number of pending approvals. */
				__( '%d decisions pending your review.', 'seo-agent-ai' ),
				$total
			)
		);
		echo '</p>';

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
			$risk_badge = $is_safe ? 'b-neutral' : 'b-danger';
			$risk_label = $is_safe ? __( 'Safe', 'seo-agent-ai' ) : __( 'Risky', 'seo-agent-ai' );

			echo '<div class="sai-decision">';

			// Header row.
			echo '<div class="sai-decision-header">';

			echo '<div class="sai-decision-type-icon ' . esc_attr( $icon_class ) . '">';
			echo $this->get_type_svg( $dec_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup.
			echo '</div>';

			echo '<div class="sai-decision-info">';
			echo '<p class="sai-decision-post">';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=seo-agent-approvals&decision_id=' . $dec_id ) ) . '">' . esc_html( $title ) . '</a>';
			if ( $field !== '' ) {
				echo ' <span style="color:var(--sai-text-muted);font-size:12px">— ' . esc_html( $field ) . '</span>';
			}
			echo '</p>';
			if ( $reasoning !== '' ) {
				echo '<p class="sai-decision-reason">' . esc_html( wp_trim_words( $reasoning, 18 ) ) . '</p>';
			}
			echo '<div class="sai-decision-tags">';
			echo '<span class="sai-badge b-primary">' . esc_html( $dec_type ) . '</span>';
			echo '<span class="sai-badge ' . esc_attr( $risk_badge ) . '">' . esc_html( $risk_label ) . '</span>';
			if ( $impact !== '' ) {
				echo '<span class="sai-badge b-neutral">' . esc_html( $impact ) . '</span>';
			}
			echo '<span class="sai-conf"><span class="sai-conf-bar"><span class="sai-conf-fill ' . esc_attr( $conf_level ) . '" style="width:' . esc_attr( $confidence ) . '%"></span></span> ' . esc_html( $confidence ) . '%</span>';
			echo '</div>';
			echo '</div>';

			echo '<div class="sai-decision-actions">';
			// Approve form.
			echo '<form method="post" action="' . esc_url( $admin_post_url ) . '" style="display:inline">';
			echo '<input type="hidden" name="action" value="seo_agent_ai_decision">';
			echo '<input type="hidden" name="seo_action" value="approve">';
			echo '<input type="hidden" name="decision_id" value="' . esc_attr( $dec_id ) . '">';
			echo wp_nonce_field( 'seo_agent_ai_decision_' . $dec_id, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field is safe.
			echo '<button type="submit" class="sai-btn sai-btn-success sai-btn-sm"><span class="btn-label">' . esc_html__( 'Apply', 'seo-agent-ai' ) . '</span></button>';
			echo '</form>';
			// Reject form.
			echo '<form method="post" action="' . esc_url( $admin_post_url ) . '" style="display:inline">';
			echo '<input type="hidden" name="action" value="seo_agent_ai_decision">';
			echo '<input type="hidden" name="seo_action" value="reject">';
			echo '<input type="hidden" name="decision_id" value="' . esc_attr( $dec_id ) . '">';
			echo wp_nonce_field( 'seo_agent_ai_decision_' . $dec_id, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce field is safe.
			echo '<button type="submit" class="sai-btn sai-btn-danger sai-btn-sm"><span class="btn-label">' . esc_html__( 'Reject', 'seo-agent-ai' ) . '</span></button>';
			echo '</form>';
			echo '</div>';

			echo '</div>';// .sai-decision-header

			// Expandable body — diff shown prominently.
			echo '<div class="sai-decision-body" style="display:none">';
			if ( $cur_val !== '' || $prop_val !== '' ) {
				echo '<div class="sai-timeline-diff">';
				if ( $cur_val !== '' ) {
					echo '<div class="sai-diff-before"><span class="sai-diff-label">' . esc_html__( 'Before', 'seo-agent-ai' ) . '</span>' . esc_html( $cur_val ) . '</div>';
				}
				if ( $prop_val !== '' ) {
					echo '<div class="sai-diff-after"><span class="sai-diff-label">' . esc_html__( 'After', 'seo-agent-ai' ) . '</span>' . esc_html( $prop_val ) . '</div>';
				}
				echo '</div>';
			}
			if ( $reasoning !== '' ) {
				echo '<div class="sai-decision-proposed"><strong>' . esc_html__( 'AI Reasoning', 'seo-agent-ai' ) . '</strong> ' . esc_html( $reasoning ) . '</div>';
			}
			echo '</div>';// .sai-decision-body

			echo '</div>';// .sai-decision
		}

		// Pagination.
		$pages = (int) ceil( $total / $per_page );
		if ( $pages > 1 ) {
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

		// Toggle body on header click.
		?>
		<?php ob_start(); ?>
		document.addEventListener('click', function (e) {
			var header = e.target.closest('.sai-decision-header');
			if (!header) return;
			if (e.target.closest('button[type="submit"]') || e.target.closest('a')) return;
			var body = header.nextElementSibling;
			if (body && body.classList.contains('sai-decision-body')) {
				body.style.display = (body.style.display === 'none' || body.style.display === '') ? 'block' : 'none';
			}
		});
		<?php wp_add_inline_script( 'seo-agent-ai-admin', ob_get_clean() ); ?>
		<?php
	}

	// -------------------------------------------------------------------
	// Single decision detail view
	// -------------------------------------------------------------------

	private function render_single_decision( $decision_id ) {
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'seo_agent_ai_decisions' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$dec = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $decision_id ), ARRAY_A );

		if ( ! $dec ) {
			echo '<div class="sai-empty"><h3>' . esc_html__( 'Decision not found.', 'seo-agent-ai' ) . '</h3></div>';
			return;
		}

		$post_id    = (int) $dec['post_id'];
		$post       = get_post( $post_id );
		$title      = $post instanceof WP_Post ? $post->post_title : "(#{$post_id})";
		$confidence = round( (float) $dec['confidence'] * 100 );
		$conf_level = $confidence >= 80 ? 'high' : ( $confidence >= 50 ? 'med' : 'low' );
		$risk       = (string) ( $dec['risk_level'] ?? '' );
		$is_safe    = 'safe' === $risk;
		$dec_type   = (string) ( $dec['decision_type'] ?? '' );
		$icon_class = $this->get_icon_class( $dec_type );
		$cur_val    = (string) ( $dec['current_value'] ?? '' );
		$prop_val   = (string) ( $dec['proposed_value'] ?? '' );
		$reasoning  = (string) ( $dec['reasoning'] ?? '' );
		$impact     = (string) ( $dec['expected_impact'] ?? '' );
		$risk_badge = $is_safe ? 'b-neutral' : 'b-danger';
		$risk_label = $is_safe ? __( 'Safe', 'seo-agent-ai' ) : __( 'Risky', 'seo-agent-ai' );

		echo '<a href="' . esc_url( admin_url( 'admin.php?page=seo-agent-approvals' ) ) . '" class="sai-btn sai-btn-ghost sai-btn-sm" style="margin-bottom:20px;display:inline-flex">';
		echo '<span class="btn-label">&#8592; ' . esc_html__( 'Back to list', 'seo-agent-ai' ) . '</span>';
		echo '</a>';

		echo '<div class="sai-decision">';
		echo '<div class="sai-decision-header">';

		echo '<div class="sai-decision-type-icon ' . esc_attr( $icon_class ) . '">';
		echo $this->get_type_svg( $dec_type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup.
		echo '</div>';

		echo '<div class="sai-decision-info">';
		echo '<p class="sai-decision-post">';
		echo '<strong>' . esc_html( $title ) . '</strong>';
		if ( $post instanceof WP_Post ) {
			echo ' <a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" class="sai-btn sai-btn-ghost sai-btn-sm" style="font-size:11px"><span class="btn-label">' . esc_html__( 'View post ↗', 'seo-agent-ai' ) . '</span></a>';
		}
		echo '</p>';
		echo '<div class="sai-decision-tags">';
		echo '<span class="sai-badge b-primary">' . esc_html( $dec_type ) . '</span>';
		echo '<span class="sai-badge ' . esc_attr( $risk_badge ) . '">' . esc_html( $risk_label ) . '</span>';
		if ( $impact !== '' ) {
			echo '<span class="sai-badge b-neutral">' . esc_html( $impact ) . '</span>';
		}
		echo '<span class="sai-conf"><span class="sai-conf-bar"><span class="sai-conf-fill ' . esc_attr( $conf_level ) . '" style="width:' . esc_attr( $confidence ) . '%"></span></span> ' . esc_html( $confidence ) . '%</span>';
		echo '</div>';
		echo '</div>';

		if ( $dec['status'] === SEO_Agent_AI_DB_Manager::STATUS_PENDING ) {
			echo '<div class="sai-decision-actions">';
			echo $this->action_buttons( $decision_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- action_buttons outputs escaped HTML.
			echo '</div>';
		}

		echo '</div>';// .sai-decision-header

		// Full diff body — always visible on single view.
		echo '<div class="sai-decision-body" style="display:block">';

		echo '<div class="sai-card" style="margin-bottom:16px">';
		echo '<div class="sai-card-body">';
		echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';

		$rows = array(
			__( 'Decision Type', 'seo-agent-ai' ) => '<code>' . esc_html( $dec_type ) . '</code>',
			__( 'Field', 'seo-agent-ai' )          => esc_html( $dec['field'] ?? '' ),
			__( 'Risk Level', 'seo-agent-ai' )      => '<span class="sai-badge ' . esc_attr( $risk_badge ) . '">' . esc_html( $risk_label ) . '</span>',
			__( 'Confidence', 'seo-agent-ai' )      => esc_html( $confidence ) . '%',
			__( 'Expected Impact', 'seo-agent-ai' ) => esc_html( $impact ),
			__( 'Status', 'seo-agent-ai' )           => '<span class="sai-status s-' . esc_attr( $dec['status'] ?? 'pending' ) . '">' . esc_html( $dec['status'] ?? '' ) . '</span>',
			__( 'Created', 'seo-agent-ai' )          => esc_html( $dec['created_at'] ?? '' ),
		);

		foreach ( $rows as $label => $value ) {
			echo '<tr style="border-bottom:1px solid var(--sai-border)">';
			echo '<th style="padding:10px 12px 10px 0;width:180px;font-weight:600;color:var(--sai-text-soft);text-align:left">' . esc_html( $label ) . '</th>';
			echo '<td style="padding:10px 0">' . $value . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values are individually escaped above.
			echo '</tr>';
		}
		echo '</table>';
		echo '</div>';
		echo '</div>';

		if ( $cur_val !== '' || $prop_val !== '' ) {
			echo '<div class="sai-timeline-diff">';
			if ( $cur_val !== '' ) {
				echo '<div class="sai-diff-before"><span class="sai-diff-label">' . esc_html__( 'Current Value', 'seo-agent-ai' ) . '</span>' . esc_html( $cur_val ) . '</div>';
			}
			if ( $prop_val !== '' ) {
				echo '<div class="sai-diff-after"><span class="sai-diff-label">' . esc_html__( 'Proposed Value', 'seo-agent-ai' ) . '</span>' . esc_html( $prop_val ) . '</div>';
			}
			echo '</div>';
		}

		if ( $reasoning !== '' ) {
			echo '<div class="sai-decision-proposed" style="margin-top:12px"><strong>' . esc_html__( 'AI Reasoning', 'seo-agent-ai' ) . '</strong> ' . esc_html( $reasoning ) . '</div>';
		}

		echo '</div>';// .sai-decision-body
		echo '</div>';// .sai-decision
	}

	// -------------------------------------------------------------------
	// Approve / Reject buttons (used in single view)
	// -------------------------------------------------------------------

	private function action_buttons( $decision_id ) {
		$url = admin_url( 'admin-post.php' );

		$approve = '<form method="post" action="' . esc_url( $url ) . '" style="display:inline">'
			. '<input type="hidden" name="action" value="seo_agent_ai_decision">'
			. '<input type="hidden" name="seo_action" value="approve">'
			. '<input type="hidden" name="decision_id" value="' . esc_attr( $decision_id ) . '">'
			. wp_nonce_field( 'seo_agent_ai_decision_' . $decision_id, '_wpnonce', true, false )
			. '<button type="submit" class="sai-btn sai-btn-success sai-btn-sm"><span class="btn-label">' . esc_html__( 'Apply', 'seo-agent-ai' ) . '</span></button>'
			. '</form>';

		$reject = '<form method="post" action="' . esc_url( $url ) . '" style="display:inline">'
			. '<input type="hidden" name="action" value="seo_agent_ai_decision">'
			. '<input type="hidden" name="seo_action" value="reject">'
			. '<input type="hidden" name="decision_id" value="' . esc_attr( $decision_id ) . '">'
			. wp_nonce_field( 'seo_agent_ai_decision_' . $decision_id, '_wpnonce', true, false )
			. '<button type="submit" class="sai-btn sai-btn-danger sai-btn-sm"><span class="btn-label">' . esc_html__( 'Reject', 'seo-agent-ai' ) . '</span></button>'
			. '</form>';

		return $approve . $reject;
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
			'monitor_decline'      => 'dt-meta',
		);
		return $map[ $dec_type ] ?? 'dt-default';
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
}
