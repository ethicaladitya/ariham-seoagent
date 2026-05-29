<?php
/**
 * Report Engine.
 *
 * Generates structured daily SEO reports and stores them in the
 * seo_agent_daily_reports table. Optionally emails the admin.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Report_Engine {

	/** @var SEO_Agent_AI_Logger */
	private $logger;

	public function __construct( SEO_Agent_AI_Logger $logger ) {
		$this->logger = $logger;
	}

	// -------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------

	/**
	 * Generate a daily report for a given date.
	 *
	 * @param string $date  'Y-m-d' format (defaults to today).
	 * @param bool   $force Overwrite if a report already exists for this date.
	 * @return array  The report data array.
	 */
	public function generate( $date = '', $force = false ) {
		$date = $date !== '' ? $date : gmdate( 'Y-m-d' );

		// Skip if report already exists for this date and not forced.
		if ( ! $force ) {
			$existing = SEO_Agent_AI_DB_Manager::get_daily_report( $date );
			if ( $existing !== null ) {
				$this->logger->debug( "Daily report for {$date} already exists — skipping." );
				return is_array( $existing['report_data'] ) ? $existing['report_data'] : json_decode( $existing['report_data'] ?? '{}', true );
			}
		}

		$report = $this->build_report( $date );

		$pages_analyzed  = $report['summary']['pages_analyzed'] ?? 0;
		$pages_optimized = $report['summary']['pages_optimized'] ?? 0;
		$opportunities   = $report['summary']['opportunities_detected'] ?? 0;
		$problems        = $report['summary']['problems_detected'] ?? 0;

		SEO_Agent_AI_DB_Manager::upsert_report(
			array(
				'report_date'            => $date,
				'report_data'            => wp_json_encode( $report ),
				'pages_analyzed'         => $pages_analyzed,
				'pages_optimized'        => $pages_optimized,
				'opportunities_detected' => $opportunities,
				'problems_detected'      => $problems,
			)
		);

		$this->logger->info( "Daily report for {$date} generated. {$pages_analyzed} pages, {$opportunities} opportunities." );

		// Optionally email admin.
		if ( (bool) get_option( 'seo_agent_ai_email_reports', false ) ) {
			$this->email_report( $report, $date );
		}

		return $report;
	}

	/**
	 * Retrieve a stored report by date.
	 *
	 * @param string $date  'Y-m-d' (defaults to today).
	 * @return array|null
	 */
	public function get( $date = '' ) {
		$date = $date !== '' ? $date : gmdate( 'Y-m-d' );
		$row  = SEO_Agent_AI_DB_Manager::get_daily_report( $date );
		if ( $row === null ) {
			return null;
		}
		return is_array( $row['report_data'] ) ? $row['report_data'] : json_decode( $row['report_data'], true );
	}

	/**
	 * List available report dates (most recent first).
	 *
	 * @param int $limit
	 * @return string[]  Array of 'Y-m-d' date strings.
	 */
	public function list_dates( $limit = 30 ) {
		return SEO_Agent_AI_DB_Manager::get_report_dates( $limit );
	}

	// -------------------------------------------------------------------
	// Report builder
	// -------------------------------------------------------------------

	private function build_report( $date ) {
		// Pull data from DB tables populated by the analysis cron.
		$activity_since = $date . ' 00:00:00';
		$activity_until = $date . ' 23:59:59';

		// Activity log entries for this date.
		$activity_rows   = SEO_Agent_AI_DB_Manager::get_activity_for_range( $activity_since, $activity_until );
		$pages_optimized = count( array_unique( array_column( $activity_rows, 'post_id' ) ) );

		// AI decisions created today.
		$decisions_today = SEO_Agent_AI_DB_Manager::get_decisions(
			array(
				'date_from' => $activity_since,
				'date_to'   => $activity_until,
			)
		);

		// Pending approvals total.
		$pending_count = SEO_Agent_AI_DB_Manager::count_decisions( SEO_Agent_AI_DB_Manager::STATUS_PENDING );

		// Latest page insights (all posts with a snapshot).
		$all_insights   = SEO_Agent_AI_DB_Manager::get_all_latest_insights( 200 );
		$pages_analyzed = count( $all_insights );

		// Score distribution.
		$score_dist = $this->score_distribution( $all_insights );

		// Top opportunities from ai_decisions (pending, ordered by confidence).
		$top_opportunities = SEO_Agent_AI_DB_Manager::get_decisions(
			array(
				'status' => SEO_Agent_AI_DB_Manager::STATUS_PENDING,
				'limit'  => 10,
			)
		);

		// Rising vs declining pages (from keyword_history trends).
		$trends = $this->compute_trends();

		// Problems detected: pages with overall score < 40.
		$low_score_pages   = array_filter( $all_insights, fn( $r ) => (int) ( $r['score_overall'] ?? 100 ) < 40 );
		$problems_detected = count( $low_score_pages );

		// Opportunities: ai_decisions pending.
		$opps_count = count( $top_opportunities );

		$report = array(
			'generated_at'       => gmdate( 'Y-m-d H:i:s' ),
			'report_date'        => $date,
			'summary'            => array(
				'pages_analyzed'         => $pages_analyzed,
				'pages_optimized'        => $pages_optimized,
				'opportunities_detected' => $opps_count,
				'problems_detected'      => $problems_detected,
				'pending_approvals'      => $pending_count,
				'changes_made'           => count( $activity_rows ),
			),
			'score_distribution' => $score_dist,
			'top_opportunities'  => $this->format_decisions( $top_opportunities ),
			'recent_changes'     => $this->format_activity( $activity_rows ),
			'trends'             => $trends,
			'low_score_pages'    => array_slice( $this->format_insights( array_values( $low_score_pages ) ), 0, 10 ),
		);

		return $report;
	}

	// -------------------------------------------------------------------
	// Formatting helpers
	// -------------------------------------------------------------------

	private function score_distribution( array $insights ) {
		$buckets = array(
			'excellent' => 0, // 80-100
			'good'      => 0, // 60-79
			'average'   => 0, // 40-59
			'poor'      => 0, // 20-39
			'critical'  => 0, // 0-19
		);

		foreach ( $insights as $row ) {
			$score = (int) ( $row['score_overall'] ?? 0 );
			if ( $score >= 80 ) {
				++$buckets['excellent'];
			} elseif ( $score >= 60 ) {
				++$buckets['good'];
			} elseif ( $score >= 40 ) {
				++$buckets['average'];
			} elseif ( $score >= 20 ) {
				++$buckets['poor'];
			} else {
				++$buckets['critical'];
			}
		}

		return $buckets;
	}

	private function compute_trends() {
		// Get top 10 rising and declining pages based on keyword_history position changes.
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'seo_agent_keyword_history' );

		$seven_days_ago    = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$fourteen_days_ago = gmdate( 'Y-m-d', strtotime( '-14 days' ) );

		// Table name is a constant built from $wpdb->prefix — safe to interpolate.
		$rising_sql = "SELECT * FROM (
		     SELECT post_id, keyword,
		         AVG(CASE WHEN recorded_at >= %s THEN position END) AS pos_recent,
		         AVG(CASE WHEN recorded_at < %s AND recorded_at >= %s THEN position END) AS pos_prior
		     FROM `{$table}`
		     GROUP BY post_id, keyword
		 ) AS agg
		 WHERE pos_recent IS NOT NULL AND pos_prior IS NOT NULL
		   AND (pos_prior - pos_recent) >= 2
		 ORDER BY (pos_prior - pos_recent) DESC
		 LIMIT 10"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rising = $wpdb->get_results( $wpdb->prepare( $rising_sql, $seven_days_ago, $seven_days_ago, $fourteen_days_ago ), ARRAY_A );

		$declining_sql = "SELECT * FROM (
		     SELECT post_id, keyword,
		         AVG(CASE WHEN recorded_at >= %s THEN position END) AS pos_recent,
		         AVG(CASE WHEN recorded_at < %s AND recorded_at >= %s THEN position END) AS pos_prior
		     FROM `{$table}`
		     GROUP BY post_id, keyword
		 ) AS agg
		 WHERE pos_recent IS NOT NULL AND pos_prior IS NOT NULL
		   AND (pos_recent - pos_prior) >= 2
		 ORDER BY (pos_recent - pos_prior) DESC
		 LIMIT 10"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$declining = $wpdb->get_results( $wpdb->prepare( $declining_sql, $seven_days_ago, $seven_days_ago, $fourteen_days_ago ), ARRAY_A );

		$format_trend = function ( $rows ) {
			$out = array();
			foreach ( $rows as $row ) {
				$post_id = (int) $row['post_id'];
				$post    = get_post( $post_id );
				$out[]   = array(
					'post_id'    => $post_id,
					'post_title' => $post instanceof WP_Post ? $post->post_title : "(#{$post_id})",
					'keyword'    => $row['keyword'],
					'pos_prior'  => round( (float) $row['pos_prior'], 1 ),
					'pos_recent' => round( (float) $row['pos_recent'], 1 ),
					'change'     => round( (float) $row['pos_prior'] - (float) $row['pos_recent'], 1 ),
				);
			}
			return $out;
		};

		return array(
			'rising'    => $format_trend( is_array( $rising ) ? $rising : array() ),
			'declining' => $format_trend( is_array( $declining ) ? $declining : array() ),
		);
	}

	private function format_decisions( array $decisions ) {
		$out = array();
		foreach ( $decisions as $dec ) {
			$post_id = (int) ( $dec['post_id'] ?? 0 );
			$post    = get_post( $post_id );
			$out[]   = array(
				'decision_id'    => (int) $dec['id'],
				'post_id'        => $post_id,
				'post_title'     => $post instanceof WP_Post ? $post->post_title : "(#{$post_id})",
				'post_url'       => $post instanceof WP_Post ? get_permalink( $post ) : '',
				'decision_type'  => $dec['decision_type'] ?? '',
				'field'          => $dec['field'] ?? '',
				'proposed_value' => $dec['proposed_value'] ?? '',
				'confidence'     => round( (float) ( $dec['confidence'] ?? 0.0 ), 2 ),
				'risk_level'     => $dec['risk_level'] ?? '',
				'reasoning'      => $dec['reasoning'] ?? '',
			);
		}
		return $out;
	}

	private function format_activity( array $rows ) {
		$out = array();
		foreach ( array_slice( $rows, 0, 20 ) as $row ) {
			$post_id = (int) ( $row['post_id'] ?? 0 );
			$post    = get_post( $post_id );
			$out[]   = array(
				'post_id'    => $post_id,
				'post_title' => $post instanceof WP_Post ? $post->post_title : "(#{$post_id})",
				'type'       => $row['change_type'] ?? $row['type'] ?? '',
				'field'      => $row['field_changed'] ?? $row['field'] ?? '',
				'created_at' => $row['created_at'] ?? '',
			);
		}
		return $out;
	}

	private function format_insights( array $insights ) {
		$out = array();
		foreach ( $insights as $row ) {
			$post_id = (int) ( $row['post_id'] ?? 0 );
			$post    = get_post( $post_id );
			$out[]   = array(
				'post_id'       => $post_id,
				'post_title'    => $post instanceof WP_Post ? $post->post_title : "(#{$post_id})",
				'post_url'      => $post instanceof WP_Post ? get_permalink( $post ) : '',
				'score_overall' => (int) ( $row['score_overall'] ?? 0 ),
				'recorded_at'   => $row['recorded_at'] ?? '',
			);
		}
		return $out;
	}

	// -------------------------------------------------------------------
	// Email — daily digest
	// -------------------------------------------------------------------

	private function email_report( array $report, $date ) {
		$to = $this->get_report_email();
		if ( $to === '' ) {
			return;
		}

		$blog_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: 1: site name, 2: date. */
			__( '[%1$s] SEO Agent Daily Report — %2$s', 'ariham-seoagent' ),
			$blog_name,
			$date
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$body    = $this->build_daily_email_html( $report, $date );

		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Send a weekly ranking digest covering GSC trends from the last 7 days.
	 *
	 * Called by the weekly ranking email cron.
	 *
	 * @return void
	 */
	public function send_weekly_ranking_email() {
		$to = $this->get_report_email();
		if ( $to === '' ) {
			return;
		}

		$blog_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: 1: site name. */
			__( '[%1$s] Weekly SEO Rankings Summary', 'ariham-seoagent' ),
			$blog_name
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$body    = $this->build_weekly_email_html();

		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Resolve the configured report recipient.
	 *
	 * @return string Email address, or empty string when none is set.
	 */
	private function get_report_email() {
		$custom = (string) get_option( 'seo_agent_ai_email_address', '' );
		if ( $custom !== '' ) {
			return sanitize_email( $custom );
		}
		$admin = (string) get_option( 'admin_email', '' );
		return sanitize_email( $admin );
	}

	// -------------------------------------------------------------------
	// HTML email builders
	// -------------------------------------------------------------------

	private function build_daily_email_html( array $report, $date ) {
		$summary       = $report['summary'] ?? array();
		$changes       = $report['recent_changes'] ?? array();
		$opps          = $report['top_opportunities'] ?? array();
		$rising        = $report['trends']['rising'] ?? array();
		$declining     = $report['trends']['declining'] ?? array();
		$low_score     = $report['low_score_pages'] ?? array();
		$score_dist    = $report['score_distribution'] ?? array();
		$approvals     = (int) ( $summary['pending_approvals'] ?? 0 );
		$site_name     = esc_html( get_bloginfo( 'name' ) );
		$approvals_url = esc_url( admin_url( 'admin.php?page=seo-agent-approvals' ) );
		$dashboard_url = esc_url( admin_url( 'admin.php?page=seo-agent-ai' ) );

		$stats = array(
			__( 'Pages Analyzed', 'ariham-seoagent' ) => absint( $summary['pages_analyzed'] ?? 0 ),
			__( 'Changes Made', 'ariham-seoagent' )   => absint( $summary['changes_made'] ?? 0 ),
			__( 'Opportunities', 'ariham-seoagent' )  => absint( $summary['opportunities_detected'] ?? 0 ),
			__( 'Problems Found', 'ariham-seoagent' ) => absint( $summary['problems_detected'] ?? 0 ),
			__( 'Pending Review', 'ariham-seoagent' ) => $approvals,
		);

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1a1a2e">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0">
<tr><td>
<table width="600" cellpadding="0" cellspacing="0" align="center" style="max-width:600px;width:100%">

	<tr><td style="background:#1a1a2e;border-radius:8px 8px 0 0;padding:24px 32px">
		<p style="margin:0;font-size:12px;color:#8b8fa8;text-transform:uppercase;letter-spacing:1px"><?php echo esc_html( $site_name ); ?></p>
		<h1 style="margin:4px 0 0;font-size:22px;font-weight:700;color:#ffffff"><?php esc_html_e( 'Daily SEO Report', 'ariham-seoagent' ); ?></h1>
		<p style="margin:4px 0 0;font-size:13px;color:#8b8fa8"><?php echo esc_html( $date ); ?></p>
	</td></tr>

	<tr><td style="background:#ffffff;padding:24px 32px">

		<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
		<tr>
		<?php foreach ( $stats as $label => $value ) : ?>
			<td style="text-align:center;padding:12px 8px;background:#f8f9fa;border-radius:6px;margin:0 4px">
				<div style="font-size:24px;font-weight:700;color:#1a1a2e"><?php echo absint( $value ); ?></div>
				<div style="font-size:11px;color:#6b7280;margin-top:2px"><?php echo esc_html( $label ); ?></div>
			</td>
		<?php endforeach; ?>
		</tr>
		</table>

		<?php if ( $approvals > 0 ) : ?>
		<div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:14px 16px;margin-bottom:20px">
			<strong style="color:#92400e"><?php echo absint( $approvals ); ?> <?php esc_html_e( 'decisions need your review', 'ariham-seoagent' ); ?></strong>
			— <a href="<?php echo esc_url( $approvals_url ); ?>" style="color:#1d4ed8"><?php esc_html_e( 'Review now →', 'ariham-seoagent' ); ?></a>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $changes ) ) : ?>
		<h2 style="font-size:14px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;padding-bottom:8px;margin:0 0 12px"><?php esc_html_e( "Today's Changes", 'ariham-seoagent' ); ?></h2>
		<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;font-size:13px">
			<?php foreach ( array_slice( $changes, 0, 10 ) as $ch ) : ?>
			<tr style="border-bottom:1px solid #f3f4f6">
				<td style="padding:6px 0;color:#374151;width:60%"><?php echo esc_html( $ch['post_title'] ?? '' ); ?></td>
				<td style="padding:6px 0;color:#6b7280;text-transform:capitalize"><?php echo esc_html( str_replace( '_', ' ', $ch['field'] ?? $ch['type'] ?? '' ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php endif; ?>

		<?php if ( ! empty( $opps ) ) : ?>
		<h2 style="font-size:14px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;padding-bottom:8px;margin:0 0 12px"><?php esc_html_e( 'Top Opportunities', 'ariham-seoagent' ); ?></h2>
		<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;font-size:13px">
			<?php foreach ( array_slice( $opps, 0, 5 ) as $op ) : ?>
			<tr style="border-bottom:1px solid #f3f4f6">
				<td style="padding:6px 0;width:55%">
					<div style="color:#374151;font-weight:500"><?php echo esc_html( $op['post_title'] ?? '' ); ?></div>
					<div style="color:#6b7280;font-size:12px"><?php echo esc_html( $op['reasoning'] ?? '' ); ?></div>
				</td>
				<td style="padding:6px 0;color:#6b7280;text-transform:capitalize;width:25%"><?php echo esc_html( str_replace( '_', ' ', $op['decision_type'] ?? '' ) ); ?></td>
				<td style="padding:6px 0;color:#059669;font-weight:600;text-align:right"><?php echo esc_html( (string) round( (float) ( $op['confidence'] ?? 0 ) * 100 ) . '%' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php endif; ?>

		<?php if ( ! empty( $rising ) ) : ?>
		<h2 style="font-size:14px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;padding-bottom:8px;margin:0 0 12px"><?php esc_html_e( 'Rising Pages', 'ariham-seoagent' ); ?></h2>
		<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;font-size:13px">
			<?php foreach ( array_slice( $rising, 0, 5 ) as $r ) : ?>
			<tr style="border-bottom:1px solid #f3f4f6">
				<td style="padding:6px 0;color:#374151"><?php echo esc_html( $r['post_title'] ?? '' ); ?></td>
				<td style="padding:6px 0;color:#6b7280;font-size:12px"><?php echo esc_html( $r['keyword'] ?? '' ); ?></td>
				<td style="padding:6px 0;color:#059669;font-weight:600;text-align:right">+<?php echo esc_html( (string) ( $r['change'] ?? 0 ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php endif; ?>

		<?php if ( ! empty( $declining ) ) : ?>
		<h2 style="font-size:14px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;padding-bottom:8px;margin:0 0 12px"><?php esc_html_e( 'Declining Pages', 'ariham-seoagent' ); ?></h2>
		<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;font-size:13px">
			<?php foreach ( array_slice( $declining, 0, 5 ) as $d ) : ?>
			<tr style="border-bottom:1px solid #f3f4f6">
				<td style="padding:6px 0;color:#374151"><?php echo esc_html( $d['post_title'] ?? '' ); ?></td>
				<td style="padding:6px 0;color:#6b7280;font-size:12px"><?php echo esc_html( $d['keyword'] ?? '' ); ?></td>
				<td style="padding:6px 0;color:#dc2626;font-weight:600;text-align:right">&minus;<?php echo esc_html( (string) abs( $d['change'] ?? 0 ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php endif; ?>

		<?php if ( ! empty( $low_score ) ) : ?>
		<h2 style="font-size:14px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;padding-bottom:8px;margin:0 0 12px"><?php esc_html_e( 'Needs Attention', 'ariham-seoagent' ); ?></h2>
		<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;font-size:13px">
			<?php foreach ( array_slice( $low_score, 0, 5 ) as $lp ) : ?>
			<tr style="border-bottom:1px solid #f3f4f6">
				<td style="padding:6px 0;color:#374151"><?php echo esc_html( $lp['post_title'] ?? '' ); ?></td>
				<td style="padding:6px 0;color:#dc2626;font-weight:600;text-align:right"><?php esc_html_e( 'Score', 'ariham-seoagent' ); ?>: <?php echo absint( $lp['score_overall'] ?? 0 ); ?></td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php endif; ?>

	</td></tr>

	<tr><td style="background:#f8f9fa;border-top:1px solid #e5e7eb;border-radius:0 0 8px 8px;padding:16px 32px;text-align:center">
		<a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;background:#1a1a2e;color:#ffffff;text-decoration:none;padding:10px 24px;border-radius:5px;font-size:13px;font-weight:600"><?php esc_html_e( 'Open Dashboard', 'ariham-seoagent' ); ?></a>
		<p style="margin:12px 0 0;font-size:11px;color:#9ca3af"><?php esc_html_e( 'SEO Agent AI — autonomous SEO for WordPress', 'ariham-seoagent' ); ?></p>
	</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	private function build_weekly_email_html() {
		global $wpdb;

		$table         = esc_sql( $wpdb->prefix . 'seo_agent_keyword_history' );
		$site_name     = esc_html( get_bloginfo( 'name' ) );
		$period_from   = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$period_to     = gmdate( 'Y-m-d' );
		$dashboard_url = esc_url( admin_url( 'admin.php?page=seo-agent-rankings' ) );

		// Top movers: biggest position improvement in last 7 days.
		$top_movers_sql = 'SELECT post_id, keyword,
				    AVG(CASE WHEN recorded_at >= %s THEN position END) AS pos_recent,
				    AVG(CASE WHEN recorded_at < %s AND recorded_at >= %s THEN position END) AS pos_prior
				FROM `' . $table . '` GROUP BY post_id, keyword HAVING pos_recent IS NOT NULL AND pos_prior IS NOT NULL AND ABS(pos_prior - pos_recent) >= 1 ORDER BY (pos_prior - pos_recent) DESC LIMIT 20'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$top_movers = $wpdb->get_results( $wpdb->prepare( $top_movers_sql, $period_from, $period_from, gmdate( 'Y-m-d', strtotime( '-14 days' ) ) ), ARRAY_A );

		// Score distribution snapshot.
		$all_insights = SEO_Agent_AI_DB_Manager::get_all_latest_insights( 200 );
		$scores       = array_column( $all_insights, 'score_overall' );
		$avg_score    = count( $scores ) > 0 ? round( array_sum( $scores ) / count( $scores ) ) : 0;
		$excellent    = count(
			array_filter(
				$scores,
				static function ( $s ) {
					return (int) $s >= 80;
				}
			)
		);
		$poor         = count(
			array_filter(
				$scores,
				static function ( $s ) {
					return (int) $s < 40;
				}
			)
		);

		ob_start();
		?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1a1a2e">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0">
<tr><td>
<table width="600" cellpadding="0" cellspacing="0" align="center" style="max-width:600px;width:100%">

	<tr><td style="background:#1a1a2e;border-radius:8px 8px 0 0;padding:24px 32px">
		<p style="margin:0;font-size:12px;color:#8b8fa8;text-transform:uppercase;letter-spacing:1px"><?php echo esc_html( $site_name ); ?></p>
		<h1 style="margin:4px 0 0;font-size:22px;font-weight:700;color:#ffffff"><?php esc_html_e( 'Weekly Rankings Summary', 'ariham-seoagent' ); ?></h1>
		<p style="margin:4px 0 0;font-size:13px;color:#8b8fa8"><?php echo esc_html( $period_from . ' → ' . $period_to ); ?></p>
	</td></tr>

	<tr><td style="background:#ffffff;padding:24px 32px">

		<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
		<tr>
			<td style="text-align:center;padding:12px 8px;background:#f8f9fa;border-radius:6px">
				<div style="font-size:28px;font-weight:700;color:#1a1a2e"><?php echo absint( $avg_score ); ?></div>
				<div style="font-size:11px;color:#6b7280;margin-top:2px"><?php esc_html_e( 'Avg SEO Score', 'ariham-seoagent' ); ?></div>
			</td>
			<td style="text-align:center;padding:12px 8px;background:#f0fdf4;border-radius:6px">
				<div style="font-size:28px;font-weight:700;color:#059669"><?php echo absint( $excellent ); ?></div>
				<div style="font-size:11px;color:#6b7280;margin-top:2px"><?php esc_html_e( 'Excellent (80+)', 'ariham-seoagent' ); ?></div>
			</td>
			<td style="text-align:center;padding:12px 8px;background:#fef2f2;border-radius:6px">
				<div style="font-size:28px;font-weight:700;color:#dc2626"><?php echo absint( $poor ); ?></div>
				<div style="font-size:11px;color:#6b7280;margin-top:2px"><?php esc_html_e( 'Poor (<40)', 'ariham-seoagent' ); ?></div>
			</td>
		</tr>
		</table>

		<?php if ( ! empty( $top_movers ) ) : ?>
		<h2 style="font-size:14px;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;padding-bottom:8px;margin:0 0 12px"><?php esc_html_e( 'Ranking Changes This Week', 'ariham-seoagent' ); ?></h2>
		<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;font-size:13px">
		<tr style="background:#f8f9fa">
			<th style="padding:8px;text-align:left;font-weight:600;color:#374151"><?php esc_html_e( 'Page', 'ariham-seoagent' ); ?></th>
			<th style="padding:8px;text-align:left;font-weight:600;color:#374151"><?php esc_html_e( 'Keyword', 'ariham-seoagent' ); ?></th>
			<th style="padding:8px;text-align:right;font-weight:600;color:#374151"><?php esc_html_e( 'Change', 'ariham-seoagent' ); ?></th>
			<th style="padding:8px;text-align:right;font-weight:600;color:#374151"><?php esc_html_e( 'Position', 'ariham-seoagent' ); ?></th>
		</tr>
			<?php
			foreach ( $top_movers as $m ) :
				$post   = get_post( (int) $m['post_id'] );
				$title  = $post instanceof WP_Post ? $post->post_title : '(#' . (int) $m['post_id'] . ')';
				$change = round( (float) $m['pos_prior'] - (float) $m['pos_recent'], 1 );
				$is_up  = $change > 0;
				?>
			<tr style="border-bottom:1px solid #f3f4f6">
				<td style="padding:6px 8px;color:#374151"><?php echo esc_html( $title ); ?></td>
				<td style="padding:6px 8px;color:#6b7280;font-size:12px"><?php echo esc_html( (string) $m['keyword'] ); ?></td>
				<td style="padding:6px 8px;font-weight:700;text-align:right;color:<?php echo $is_up ? '#059669' : '#dc2626'; ?>">
					<?php echo $is_up ? '+' : ''; ?><?php echo esc_html( (string) $change ); ?>
				</td>
				<td style="padding:6px 8px;text-align:right;color:#374151"><?php echo esc_html( (string) round( (float) $m['pos_recent'], 1 ) ); ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php else : ?>
		<p style="color:#6b7280;font-size:13px"><?php esc_html_e( 'Not enough ranking data yet. Rankings will populate as GSC data accumulates.', 'ariham-seoagent' ); ?></p>
		<?php endif; ?>

	</td></tr>

	<tr><td style="background:#f8f9fa;border-top:1px solid #e5e7eb;border-radius:0 0 8px 8px;padding:16px 32px;text-align:center">
		<a href="<?php echo esc_url( $dashboard_url ); ?>" style="display:inline-block;background:#1a1a2e;color:#ffffff;text-decoration:none;padding:10px 24px;border-radius:5px;font-size:13px;font-weight:600"><?php esc_html_e( 'View Rankings', 'ariham-seoagent' ); ?></a>
		<p style="margin:12px 0 0;font-size:11px;color:#9ca3af"><?php esc_html_e( 'SEO Agent AI — autonomous SEO for WordPress', 'ariham-seoagent' ); ?></p>
	</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
