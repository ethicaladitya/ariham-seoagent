<?php
/**
 * Keyword Rankings admin page — position history from keyword_history table.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Rankings_Page {

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'ariham-seoagent' ) );
		}

		$search_query = isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['keyword'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$post_id      = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$days         = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30; // phpcs:ignore WordPress.Security.NonceVerification
		$days         = in_array( $days, array( 7, 14, 30, 60, 90 ), true ) ? $days : 30;

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_GET['triggered'] ) && sanitize_key( $_GET['triggered'] ) === 'seo_agent_fetch_gsc_data' ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="sai-notice n-success"><p>';
			esc_html_e( 'GSC keyword fetch triggered. Reload in a moment to see results.', 'ariham-seoagent' );
			echo '</p></div>';
		}

		$gsc_site = $this->resolve_gsc_site();
		?>
		<div class="wrap sai-page">
			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow"><span class="sai-dot"></span><?php esc_html_e( 'SEO Agent AI', 'ariham-seoagent' ); ?></p>
					<h1 class="sai-header-title"><?php esc_html_e( 'Keyword Rankings', 'ariham-seoagent' ); ?></h1>
				</div>
				<div class="sai-header-actions">
					<?php if ( $gsc_site !== '' ) : ?>
						<?php
						$gsc_hook  = 'seo_agent_fetch_gsc_data';
						$nonce_val = wp_create_nonce( 'seo_agent_ai_trigger_' . $gsc_hook );
						?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
							<input type="hidden" name="action" value="seo_agent_ai_trigger_cron">
							<input type="hidden" name="hook" value="<?php echo esc_attr( $gsc_hook ); ?>">
							<input type="hidden" name="redirect_page" value="seo-agent-rankings">
							<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce_val ); ?>">
							<button type="submit" class="sai-btn sai-btn-ghost">
								<span class="btn-label"><?php esc_html_e( 'Sync GSC Now', 'ariham-seoagent' ); ?></span>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>

			<div class="sai-body">
				<?php $this->render_gsc_status_bar(); ?>
				<?php $this->render_filters( $search_query, $post_id, $days ); ?>

				<?php
				if ( $post_id > 0 ) {
					$this->render_post_rankings( $post_id, $days );
				} elseif ( $search_query !== '' ) {
					$this->render_keyword_rankings( $search_query, $days );
				} else {
					$this->render_top_movers( $days );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Resolve the GSC property URL using the same fallback chain as the GSC client:
	 * 1. Site Kit bridge (if active)
	 * 2. Plugin's own GSC site URL option
	 * 3. Legacy option key
	 *
	 * @return string Property URL or empty string when not connected.
	 */
	private function resolve_gsc_site() {
		if ( class_exists( 'SEO_Agent_AI_SiteKit_Bridge' ) && SEO_Agent_AI_SiteKit_Bridge::is_active() ) {
			$sk_url = SEO_Agent_AI_SiteKit_Bridge::get_gsc_site_url();
			if ( $sk_url !== '' ) {
				return $sk_url;
			}
		}

		$url = (string) get_option( 'seo_agent_ai_gsc_site_url', '' );
		if ( $url !== '' ) {
			return $url;
		}

		// Legacy option written by older settings save handlers.
		return (string) get_option( 'seo_agent_ai_gsc_site', '' );
	}

	private function render_gsc_status_bar() {
		$gsc_site  = $this->resolve_gsc_site();
		$last_sync = (string) get_option( 'seo_agent_ai_last_run_seo_agent_fetch_gsc_data', '' );

		if ( $gsc_site === '' ) {
			echo '<div class="sai-notice n-warning" style="margin-bottom:16px">';
			echo '<p style="margin:0">';
			echo '<strong>' . esc_html__( 'Google Search Console not connected.', 'ariham-seoagent' ) . '</strong> ';
			esc_html_e( 'Keyword ranking data comes from GSC. Connect it first, then fetch data.', 'ariham-seoagent' );
			echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ariham-seoagent-connect' ) ) . '" class="sai-btn sai-btn-sm sai-btn-ghost" style="margin-left:8px">';
			esc_html_e( 'Connect Google', 'ariham-seoagent' );
			echo '</a>';
			echo '</p></div>';
			return;
		}

		$sync_label = $last_sync !== ''
			? sprintf(
				/* translators: %s: human-readable time diff */
				__( 'Last sync: %s ago', 'ariham-seoagent' ),
				human_time_diff( strtotime( $last_sync ), current_time( 'timestamp' ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			)
			: __( 'Never synced', 'ariham-seoagent' );

		echo '<div class="sai-card" style="margin-bottom:16px">';
		echo '<div class="sai-card-body" style="display:flex;align-items:center;gap:12px;padding:10px 16px">';
		echo '<span class="sai-badge b-success">' . esc_html__( 'Connected', 'ariham-seoagent' ) . '</span>';
		echo '<span style="color:#50575e;font-size:13px"><strong>' . esc_html__( 'GSC:', 'ariham-seoagent' ) . '</strong> ' . esc_html( $gsc_site ) . '</span>';
		echo '<span style="color:#787c82;font-size:12px">' . esc_html( $sync_label ) . '</span>';
		echo '</div></div>';
	}

	private function render_filters( $search_query, $post_id, $days ) {
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		echo '<form method="get" class="sai-filters" style="margin-bottom:16px">';
		echo '<input type="hidden" name="page" value="seo-agent-rankings">';

		echo '<div class="sai-search-wrap">';
		echo '<input type="text" name="keyword" value="' . esc_attr( $search_query ) . '" placeholder="' . esc_attr__( 'Search keyword...', 'ariham-seoagent' ) . '">';
		echo '</div>';

		echo '<label>';
		echo '<select name="post_id">';
		echo '<option value="">' . esc_html__( 'All posts', 'ariham-seoagent' ) . '</option>';
		foreach ( $posts as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '"' . selected( $post_id, $p->ID, false ) . '>';
			echo esc_html( $p->post_title );
			echo '</option>';
		}
		echo '</select>';
		echo '</label>';

		echo '<label>';
		echo '<select name="days">';
		foreach ( array( 7, 14, 30, 60, 90 ) as $d ) {
			echo '<option value="' . esc_attr( $d ) . '"' . selected( $days, $d, false ) . '>';
			/* translators: %d: number of days. */
			echo esc_html( sprintf( __( 'Last %d days', 'ariham-seoagent' ), $d ) );
			echo '</option>';
		}
		echo '</select>';
		echo '</label>';

		echo '<button type="submit" class="sai-btn sai-btn-primary sai-btn-sm"><span class="btn-label">' . esc_html__( 'View', 'ariham-seoagent' ) . '</span></button>';
		echo '</form>';
	}

	private function render_post_rankings( $post_id, $days ) {
		global $wpdb;
		$table  = esc_sql( $wpdb->prefix . 'seo_agent_keyword_history' );
		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT keyword, position, impressions, clicks, recorded_at
			 FROM {$table}
			 WHERE post_id = %d AND recorded_at >= %s
			 ORDER BY keyword, recorded_at ASC",
			$post_id,
			$cutoff
		), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$post  = get_post( $post_id );
		$title = $post instanceof WP_Post ? $post->post_title : "(#{$post_id})";

		echo '<div class="sai-card">';
		// translators: %s is the post title.
		echo '<div class="sai-card-header"><h2 class="sai-card-title">' . esc_html( sprintf( __( 'Rankings for: %s', 'ariham-seoagent' ), $title ) ) . '</h2></div>';
		echo '<div class="sai-card-body">';

		if ( empty( $rows ) ) {
			echo '<div class="sai-empty">';
			echo '<div class="sai-empty-icon">&#128269;</div>';
			echo '<h3>' . esc_html__( 'No data yet', 'ariham-seoagent' ) . '</h3>';
			echo '<p>' . esc_html__( 'No keyword history for this post. Use "Sync GSC Now" to pull data from Google Search Console.', 'ariham-seoagent' ) . '</p>';
			echo '</div>';
			echo '</div></div>';
			return;
		}

		// Group by keyword.
		$by_keyword = array();
		foreach ( $rows as $row ) {
			$kw = $row['keyword'];
			if ( ! isset( $by_keyword[ $kw ] ) ) {
				$by_keyword[ $kw ] = array();
			}
			$by_keyword[ $kw ][] = $row;
		}

		$this->render_rankings_table( $by_keyword );
		echo '</div></div>';
	}

	private function render_keyword_rankings( $keyword, $days ) {
		global $wpdb;
		$table  = esc_sql( $wpdb->prefix . 'seo_agent_keyword_history' );
		$cutoff = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, keyword, position, impressions, clicks, recorded_at
			 FROM {$table}
			 WHERE keyword LIKE %s AND recorded_at >= %s
			 ORDER BY post_id, recorded_at ASC
			 LIMIT 200",
			'%' . $wpdb->esc_like( $keyword ) . '%',
			$cutoff
		), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		echo '<div class="sai-card">';
		// translators: %s is the search keyword.
		echo '<div class="sai-card-header"><h2 class="sai-card-title">' . esc_html( sprintf( __( 'Rankings for keyword: "%s"', 'ariham-seoagent' ), $keyword ) ) . '</h2></div>';
		echo '<div class="sai-card-body">';

		if ( empty( $rows ) ) {
			echo '<div class="sai-empty">';
			echo '<div class="sai-empty-icon">&#128269;</div>';
			echo '<h3>' . esc_html__( 'No results', 'ariham-seoagent' ) . '</h3>';
			echo '<p>' . esc_html__( 'No results found for this keyword.', 'ariham-seoagent' ) . '</p>';
			echo '</div>';
			echo '</div></div>';
			return;
		}

		echo '<div class="sai-table-wrap">';
		echo '<table class="sai-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Post', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-center">' . esc_html__( 'Avg Position', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-center">' . esc_html__( 'Total Impressions', 'ariham-seoagent' ) . '</th>';
		echo '</tr></thead><tbody>';

		// Group by post.
		$by_post = array();
		foreach ( $rows as $row ) {
			$pid = (int) $row['post_id'];
			if ( ! isset( $by_post[ $pid ] ) ) {
				$by_post[ $pid ] = array( 'positions' => array(), 'impressions' => 0 );
			}
			$by_post[ $pid ]['positions'][]  = (float) $row['position'];
			$by_post[ $pid ]['impressions'] += (int) $row['impressions'];
		}

		foreach ( $by_post as $pid => $data ) {
			$post    = get_post( $pid );
			$ptitle  = $post instanceof WP_Post ? $post->post_title : "(#{$pid})";
			$avg_pos = round( array_sum( $data['positions'] ) / count( $data['positions'] ), 1 );

			$pos_class = '';
			if ( $avg_pos <= 3 ) {
				$pos_class = 'pos-top3';
			} elseif ( $avg_pos <= 10 ) {
				$pos_class = 'pos-top10';
			} elseif ( $avg_pos <= 20 ) {
				$pos_class = 'pos-top20';
			} else {
				$pos_class = 'pos-lower';
			}

			echo '<tr>';
			echo '<td class="col-trunc"><a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html( $ptitle ) . '</a></td>';
			echo '<td class="col-center"><span class="sai-position ' . esc_attr( $pos_class ) . '">' . esc_html( $avg_pos ) . '</span></td>';
			echo '<td class="col-center">' . esc_html( number_format_i18n( $data['impressions'] ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '</div></div>';
	}

	private function render_top_movers( $days ) {
		global $wpdb;
		$table      = esc_sql( $wpdb->prefix . 'seo_agent_keyword_history' );
		$recent_cut = gmdate( 'Y-m-d', strtotime( '-' . (int) round( $days / 2 ) . ' days' ) );
		$prior_cut  = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Rising — wrap in subquery to avoid HAVING-alias restriction in strict MySQL.
		$rising = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM (
			     SELECT post_id, keyword,
			         AVG(CASE WHEN recorded_at >= %s THEN position END) AS pos_recent,
			         AVG(CASE WHEN recorded_at < %s AND recorded_at >= %s THEN position END) AS pos_prior
			     FROM {$table}
			     GROUP BY post_id, keyword
			 ) AS agg
			 WHERE pos_recent IS NOT NULL AND pos_prior IS NOT NULL
			   AND (pos_prior - pos_recent) >= 1
			 ORDER BY (pos_prior - pos_recent) DESC
			 LIMIT 20",
			$recent_cut,
			$recent_cut,
			$prior_cut
		), ARRAY_A );

		// Declining.
		$declining = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM (
			     SELECT post_id, keyword,
			         AVG(CASE WHEN recorded_at >= %s THEN position END) AS pos_recent,
			         AVG(CASE WHEN recorded_at < %s AND recorded_at >= %s THEN position END) AS pos_prior
			     FROM {$table}
			     GROUP BY post_id, keyword
			 ) AS agg
			 WHERE pos_recent IS NOT NULL AND pos_prior IS NOT NULL
			   AND (pos_recent - pos_prior) >= 1
			 ORDER BY (pos_recent - pos_prior) DESC
			 LIMIT 20",
			$recent_cut,
			$recent_cut,
			$prior_cut
		), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">';

		// Rising card.
		echo '<div class="sai-card accent-success">';
		echo '<div class="sai-card-header"><h2 class="sai-card-title">&#8593; ' . esc_html__( 'Rising Keywords', 'ariham-seoagent' ) . '</h2></div>';
		echo '<div class="sai-card-body">';
		$this->render_mover_table( is_array( $rising ) ? $rising : array(), 'rising' );
		echo '</div></div>';

		// Declining card.
		echo '<div class="sai-card accent-danger">';
		echo '<div class="sai-card-header"><h2 class="sai-card-title">&#8595; ' . esc_html__( 'Declining Keywords', 'ariham-seoagent' ) . '</h2></div>';
		echo '<div class="sai-card-body">';
		$this->render_mover_table( is_array( $declining ) ? $declining : array(), 'declining' );
		echo '</div></div>';

		echo '</div>';
	}

	private function render_mover_table( array $rows, $type ) {
		if ( empty( $rows ) ) {
			echo '<div class="sai-empty">';
			echo '<p>' . esc_html__( 'No data yet — keyword history needs at least two GSC syncs to calculate movement.', 'ariham-seoagent' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<div class="sai-table-wrap">';
		echo '<table class="sai-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Keyword', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-trunc">' . esc_html__( 'Post', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-center col-num">' . esc_html__( 'Prior', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-center col-num">' . esc_html__( 'Recent', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-center">' . esc_html__( 'Change', 'ariham-seoagent' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$post_id = (int) $row['post_id'];
			$post    = get_post( $post_id );
			$title   = $post instanceof WP_Post ? $post->post_title : "(#{$post_id})";
			$prior   = round( (float) $row['pos_prior'], 1 );
			$recent  = round( (float) $row['pos_recent'], 1 );
			$change  = round( $prior - $recent, 1 );

			if ( 'rising' === $type ) {
				$delta_cls = 'delta-up';
				$sign      = '+';
				$val       = abs( $change );
			} else {
				$delta_cls = 'delta-down';
				$sign      = '-';
				$val       = abs( $change );
			}

			echo '<tr>';
			echo '<td><strong>' . esc_html( $row['keyword'] ) . '</strong></td>';
			echo '<td class="col-trunc"><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( $title ) . '</a></td>';
			echo '<td class="col-center col-num">' . esc_html( $prior ) . '</td>';
			echo '<td class="col-center col-num">' . esc_html( $recent ) . '</td>';
			echo '<td class="col-center"><span class="sai-delta ' . esc_attr( $delta_cls ) . '">' . esc_html( $sign . $val ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	private function render_rankings_table( array $by_keyword ) {
		echo '<div class="sai-table-wrap">';
		echo '<table class="sai-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Keyword', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-center">' . esc_html__( 'Latest Position', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-center col-num">' . esc_html__( 'Impressions', 'ariham-seoagent' ) . '</th>';
		echo '<th class="col-center">' . esc_html__( 'Trend', 'ariham-seoagent' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $by_keyword as $kw => $rows ) {
			$latest     = end( $rows );
			$first      = reset( $rows );
			$pos        = round( (float) $latest['position'], 1 );
			$change     = round( (float) $first['position'] - (float) $latest['position'], 1 );
			$total_impr = array_sum( array_column( $rows, 'impressions' ) );

			$pos_class = '';
			if ( $pos <= 3 ) {
				$pos_class = 'pos-top3';
			} elseif ( $pos <= 10 ) {
				$pos_class = 'pos-top10';
			} elseif ( $pos <= 20 ) {
				$pos_class = 'pos-top20';
			} else {
				$pos_class = 'pos-lower';
			}

			echo '<tr>';
			echo '<td><strong>' . esc_html( $kw ) . '</strong></td>';
			echo '<td class="col-center"><span class="sai-position ' . esc_attr( $pos_class ) . '">' . esc_html( $pos ) . '</span></td>';
			echo '<td class="col-center col-num">' . esc_html( number_format_i18n( $total_impr ) ) . '</td>';
			echo '<td class="col-center">';
			if ( 0.0 !== $change ) {
				$delta_cls = $change > 0 ? 'delta-up' : 'delta-down';
				$sign      = $change > 0 ? '+' : '';
				echo '<span class="sai-delta ' . esc_attr( $delta_cls ) . '">' . esc_html( $sign . $change ) . '</span>';
			} else {
				echo '<span class="sai-delta delta-flat">—</span>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}
