<?php
/**
 * Plugin orchestrator.
 *
 * Wires all modules together, registers WordPress hooks, and coordinates
 * analysis, autopilot, rollbacks, and OAuth.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Plugin {

	const ANALYSIS_LOCK_KEY         = 'seo_agent_ai_analysis_lock';
	const ANALYSIS_LOCK_TTL         = 15 * MINUTE_IN_SECONDS;
	const CONNECTION_TEST_TRANSIENT = 'seo_agent_ai_connection_test_result';
	const DAILY_AP_TRANSIENT_PREFIX = 'seo_agent_ai_ap_count_';
	const BATCH_ANALYSIS_KEY        = 'seo_agent_ai_batch_state';
	const OPTION_API_FAILURES       = 'seo_agent_ai_consecutive_api_failures';
	const OPTION_LAST_API_ERROR     = 'seo_agent_ai_last_api_error';
	const API_FAILURE_NOTICE_AFTER  = 2;
	const CRON_HOOK_DAILY           = 'seo_agent_ai_daily_analysis';
	const CRON_HOOK_MANUAL          = 'seo_agent_ai_run_manual_analysis';
	const CRON_HOOK_GSC             = 'seo_agent_fetch_gsc_data';
	const CRON_HOOK_GA4             = 'seo_agent_fetch_ga4_data';
	const CRON_HOOK_REPORT          = 'seo_agent_generate_report';
	const CRON_HOOK_SCORE           = 'seo_agent_score_pages';
	const CRON_HOOK_DECAY           = 'seo_agent_detect_decay';
	const CRON_HOOK_LINKS           = 'seo_agent_run_internal_links';
	const CRON_HOOK_PURGE           = 'seo_agent_purge_old_data';
	const CRON_HOOK_CANNIBAL        = 'seo_agent_detect_cannibalization';
	const CRON_HOOK_IMPROVE         = 'seo_agent_score_and_improve';
	const CRON_HOOK_ORPHAN          = 'seo_agent_detect_orphans';
	const CRON_HOOK_IMAGE_ALTS      = 'seo_agent_generate_image_alts';
	const CRON_HOOK_OBSERVE         = 'seo_agent_observe_results';
	const CRON_HOOK_CWV             = 'seo_agent_fetch_cwv_data';
	const CRON_HOOK_HEALTH          = 'seo_agent_health_check';
	const CRON_HOOK_AUTO_REDIRECT   = 'seo_agent_auto_redirect_404s';
	const CRON_HOOK_WEEKLY_EMAIL    = 'seo_agent_weekly_ranking_email';

	private static $instance = null;

	/** @var SEO_Agent_AI_Data_Store */
	private $data_store;

	/** @var SEO_Agent_AI_Google_OAuth */
	private $oauth;

	/** @var SEO_Agent_AI_GSC_Client */
	private $gsc_client;

	/** @var SEO_Agent_AI_GA4_Client */
	private $ga4_client;

	/** @var SEO_Agent_AI_SEO_Analyzer */
	private $analyzer;

	/** @var SEO_Agent_AI_Recommendation_Engine */
	private $recommendation_engine;

	/** @var SEO_Agent_AI_Fix_Executor */
	private $fix_executor;

	/** @var SEO_Agent_AI_Activity_Log */
	private $activity_log;

	/** @var SEO_Agent_AI_SEO_Plugin_Bridge */
	private $bridge;

	/** @var SEO_Agent_AI_Gemini_Client */
	private $gemini;

	/** @var SEO_Agent_AI_OpenAI_Client */
	private $openai;

	/** @var SEO_Agent_AI_Logger */
	private $logger;

	/** @var SEO_Agent_AI_Content_Analyzer */
	private $content_analyzer;

	/** @var SEO_Agent_AI_Keyword_Cluster */
	private $keyword_cluster;

	/** @var SEO_Agent_AI_SEO_Scoring_Engine */
	private $scoring_engine;

	/** @var SEO_Agent_AI_Decision_Engine */
	private $decision_engine;

	/** @var SEO_Agent_AI_Schema_Engine */
	private $schema_engine;

	/** @var SEO_Agent_AI_Internal_Link_Engine */
	private $internal_link_engine;

	/** @var SEO_Agent_AI_Report_Engine */
	private $report_engine;

	/** @var SEO_Agent_AI_Queue_Manager */
	private $queue_manager;

	/** @var SEO_Agent_AI_GSC_Opportunity_Analyzer */
	private $gsc_opportunity_analyzer;

	/** @var SEO_Agent_AI_Admin_Page */
	private $admin_page;

	/** @var SEO_Agent_AI_Image_SEO */
	private $image_seo;

	/** @var SEO_Agent_AI_Social_Meta */
	private $social_meta;

	/** @var SEO_Agent_AI_Meta_Box */
	private $meta_box;

	/** @var SEO_Agent_AI_Taxonomy_SEO */
	private $taxonomy_seo;

	/** @var SEO_Agent_AI_Redirect_Manager */
	private $redirect_manager;

	/** @var SEO_Agent_AI_PageSpeed_Client */
	private $pagespeed_client;

	/** @var SEO_Agent_AI_Content_Expander */
	private $content_expander;

	/** @var SEO_Agent_AI_IndexNow */
	private $indexnow;

	// -------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Core infrastructure.
		$this->logger       = new SEO_Agent_AI_Logger();
		$this->data_store   = new SEO_Agent_AI_Data_Store();
		$this->activity_log = new SEO_Agent_AI_Activity_Log();
		$this->oauth        = new SEO_Agent_AI_Google_OAuth();
		$this->bridge       = new SEO_Agent_AI_SEO_Plugin_Bridge();

		// API clients.
		$this->gemini           = new SEO_Agent_AI_Gemini_Client();
		$this->openai           = new SEO_Agent_AI_OpenAI_Client();
		$this->gsc_client       = new SEO_Agent_AI_GSC_Client( $this->oauth );
		$this->ga4_client       = new SEO_Agent_AI_GA4_Client( $this->oauth );
		$this->pagespeed_client = new SEO_Agent_AI_PageSpeed_Client();

		// Analysis engines.
		$this->content_analyzer = new SEO_Agent_AI_Content_Analyzer();
		$this->keyword_cluster  = new SEO_Agent_AI_Keyword_Cluster();
		$this->scoring_engine   = new SEO_Agent_AI_SEO_Scoring_Engine( $this->content_analyzer );
		$this->decision_engine  = new SEO_Agent_AI_Decision_Engine();

		// Schema injection (wp_head, priority 5).
		$this->schema_engine = new SEO_Agent_AI_Schema_Engine( $this->content_analyzer, $this->logger );

		// Analyzer + recommendation engine (depend on above).
		$this->analyzer              = new SEO_Agent_AI_SEO_Analyzer( $this->content_analyzer, $this->keyword_cluster );
		$this->recommendation_engine = new SEO_Agent_AI_Recommendation_Engine( $this->gemini, $this->openai, $this->decision_engine );
		$this->fix_executor          = new SEO_Agent_AI_Fix_Executor( $this->activity_log, $this->bridge );

		// Autonomous systems.
		$this->internal_link_engine     = new SEO_Agent_AI_Internal_Link_Engine( $this->logger );
		$this->report_engine            = new SEO_Agent_AI_Report_Engine( $this->logger );
		$this->queue_manager            = new SEO_Agent_AI_Queue_Manager( $this->logger );
		$this->gsc_opportunity_analyzer = new SEO_Agent_AI_GSC_Opportunity_Analyzer( $this->gsc_client );

		// Content generation.
		$this->content_expander = new SEO_Agent_AI_Content_Expander( $this->openai, $this->gemini );

		// IndexNow — instant URL submission after fixes.
		$this->indexnow = new SEO_Agent_AI_IndexNow( $this->logger );
		// write_key_file() deferred to init so WordPress filesystem helpers are ready.
		add_action( 'init', array( $this->indexnow, 'write_key_file' ), 20 );
		add_action( 'seo_agent_ai_fix_applied', array( $this, 'on_fix_applied_indexnow' ), 10, 1 );

		// Feature modules.
		$this->image_seo        = new SEO_Agent_AI_Image_SEO( $this->gemini, $this->openai, $this->logger );
		$this->social_meta      = new SEO_Agent_AI_Social_Meta();
		$this->meta_box         = new SEO_Agent_AI_Meta_Box();
		$this->taxonomy_seo     = new SEO_Agent_AI_Taxonomy_SEO();
		$this->redirect_manager = new SEO_Agent_AI_Redirect_Manager();

		// Admin page sub-page instances.
		$connect_page           = new SEO_Agent_AI_Connect_Page( $this->oauth );
		$report_page            = new SEO_Agent_AI_Report_Page( $this->activity_log, $this->data_store );
		$dashboard_page         = new SEO_Agent_AI_Dashboard_Page( $this->decision_engine, $this->report_engine, $this->activity_log );
		$opportunities_page     = new SEO_Agent_AI_Opportunities_Page( $this->decision_engine );
		$rankings_page          = new SEO_Agent_AI_Rankings_Page();
		$pending_approvals_page = new SEO_Agent_AI_Pending_Approvals_Page( $this->decision_engine, $this->fix_executor, $this->internal_link_engine );
		$rollback_center_page   = new SEO_Agent_AI_Rollback_Center_Page( $this->fix_executor, $this->activity_log );
		$cron_status_page       = new SEO_Agent_AI_Cron_Status_Page();
		$image_seo_page         = new SEO_Agent_AI_Image_SEO_Page( $this->image_seo );
		$redirects_page         = new SEO_Agent_AI_Redirects_Page( $this->redirect_manager );
		$activity_log_page      = new SEO_Agent_AI_Activity_Log_Page( $this->activity_log, $this->logger );

		$this->admin_page = new SEO_Agent_AI_Admin_Page(
			$this->data_store,
			$connect_page,
			$report_page,
			$this->oauth,
			$this->bridge,
			$dashboard_page,
			$opportunities_page,
			$rankings_page,
			$pending_approvals_page,
			$rollback_center_page,
			$cron_status_page,
			$image_seo_page,
			$redirects_page,
			$activity_log_page
		);

		// Admin hooks.
		add_action( 'admin_menu', array( $this->admin_page, 'register_menu' ) );
		add_action( 'admin_post_seo_agent_ai_apply_fix', array( $this, 'handle_apply_fix' ) );
		add_action( 'admin_post_seo_agent_ai_run_analysis', array( $this, 'handle_manual_analysis' ) );
		add_action( 'admin_post_seo_agent_ai_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_seo_agent_ai_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_seo_agent_ai_google_disconnect', array( $this, 'handle_google_disconnect' ) );
		add_action( 'admin_post_seo_agent_ai_rollback_backup', array( $this, 'handle_rollback_backup' ) );
		add_action( 'admin_post_seo_agent_ai_rollback', array( $this, 'handle_activity_rollback' ) );

		// New v3.0 admin-post handlers.
		add_action( 'admin_post_seo_agent_ai_decision', array( $pending_approvals_page, 'handle_action' ) );
		add_action( 'admin_post_seo_agent_ai_bulk_apply_safe', array( $pending_approvals_page, 'handle_bulk_apply_safe' ) );
		add_action( 'admin_post_seo_agent_ai_rollback_new', array( $rollback_center_page, 'handle_rollback' ) );
		add_action( 'admin_post_seo_agent_ai_trigger_cron', array( $cron_status_page, 'handle_trigger' ) );
		add_action( 'admin_post_seo_agent_ai_manage_redirect', array( $redirects_page, 'handle_action' ) );

		// OAuth callback — must run before page output.
		add_action( 'admin_init', array( $this, 'maybe_handle_oauth_callback' ) );

		// AJAX: property listing for Settings page.
		add_action( 'wp_ajax_seo_agent_ai_list_gsc_sites', array( $this, 'ajax_list_gsc_sites' ) );
		add_action( 'wp_ajax_seo_agent_ai_list_ga4_properties', array( $this, 'ajax_list_ga4_properties' ) );

		// AJAX: interactive batch analysis.
		add_action( 'wp_ajax_seo_agent_ai_analyze_batch', array( $this, 'ajax_analyze_batch' ) );

		// Cron hooks — daily.
		add_action( self::CRON_HOOK_DAILY, array( $this, 'run_daily_analysis' ) );
		add_action( self::CRON_HOOK_MANUAL, array( $this, 'run_daily_analysis' ) );
		add_action( self::CRON_HOOK_GSC, array( $this, 'run_fetch_gsc' ) );
		add_action( self::CRON_HOOK_GA4, array( $this, 'run_fetch_ga4' ) );
		add_action( self::CRON_HOOK_REPORT, array( $this, 'run_generate_report' ) );

		// Thin-content auto-expansion: runs as part of the daily analysis pass.
		add_action( self::CRON_HOOK_DAILY, array( $this, 'queue_thin_content_for_expansion' ) );

		// Async single-post expansion worker (scheduled by queue_thin_content_for_expansion).
		add_action( 'seo_agent_ai_expand_single_post', array( $this, 'run_expand_single_post' ), 10, 3 );

		// Cron hooks — weekly.
		add_action( self::CRON_HOOK_SCORE, array( $this, 'run_score_pages' ) );
		add_action( self::CRON_HOOK_DECAY, array( $this, 'run_detect_decay' ) );
		add_action( self::CRON_HOOK_LINKS, array( $this, 'run_internal_links' ) );
		add_action( self::CRON_HOOK_PURGE, array( $this, 'run_purge_old_data' ) );
		add_action( self::CRON_HOOK_CANNIBAL, array( $this, 'run_detect_cannibalization' ) );
		add_action( self::CRON_HOOK_IMPROVE, array( $this, 'run_improve_low_scoring_posts' ) );

		// Cron hook — orphan detection.
		add_action( self::CRON_HOOK_ORPHAN, array( $this, 'run_detect_orphans' ) );

		// Cron hook — daily image alt text generation (autopilot only).
		add_action( self::CRON_HOOK_IMAGE_ALTS, array( $this, 'run_generate_image_alts' ) );

		// Cron hook — weekly observation pass: compare GSC metrics before/after applied changes.
		add_action( self::CRON_HOOK_OBSERVE, array( $this, 'run_observe_results' ) );

		// Cron hook — weekly Core Web Vitals data prefetch via PageSpeed Insights API.
		add_action( self::CRON_HOOK_CWV, array( $this, 'run_fetch_cwv_data' ) );

		// Cron hook — 6-hourly health check: alert if daily analysis is overdue.
		add_action( self::CRON_HOOK_HEALTH, array( $this, 'run_health_check' ) );

		// Cron hook — weekly: auto-resolve high-traffic 404s with redirects.
		add_action( self::CRON_HOOK_AUTO_REDIRECT, array( $this, 'run_auto_redirect_404s' ) );

		// Cron hook — weekly: send ranking summary email.
		add_action( self::CRON_HOOK_WEEKLY_EMAIL, array( $this, 'run_weekly_ranking_email' ) );

		$this->image_seo->init_hooks();
		$this->social_meta->init_hooks();
		$this->meta_box->init_hooks();
		$this->taxonomy_seo->init_hooks();
		$this->redirect_manager->init_hooks();

		// Defensive: re-add cron schedules on every load (guarded by transient).
		add_action( 'init', array( $this, 'ensure_cron_schedules' ) );

		// Persistent admin notice when GSC/GA4 fails repeatedly.
		add_action( 'admin_notices', array( $this, 'maybe_render_api_failure_notice' ) );
	}

	// -------------------------------------------------------------------
	// Activation / deactivation
	// -------------------------------------------------------------------

	public static function activate() {
		SEO_Agent_AI_Activity_Log::create_table();
		SEO_Agent_AI_DB_Manager::create_tables();

		$daily_hooks = array(
			self::CRON_HOOK_DAILY,
			self::CRON_HOOK_GSC,
			self::CRON_HOOK_GA4,
			self::CRON_HOOK_REPORT,
			self::CRON_HOOK_IMAGE_ALTS,
		);
		$offset      = 0;
		foreach ( $daily_hooks as $hook ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS + $offset * 5 * MINUTE_IN_SECONDS, 'daily', $hook );
			}
			++$offset;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK_HEALTH ) ) {
			wp_schedule_event( time() + 6 * HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK_HEALTH );
		}

		$weekly_hooks = array(
			self::CRON_HOOK_SCORE,
			self::CRON_HOOK_DECAY,
			self::CRON_HOOK_LINKS,
			self::CRON_HOOK_PURGE,
			self::CRON_HOOK_CANNIBAL,
			self::CRON_HOOK_IMPROVE,
			self::CRON_HOOK_ORPHAN,
			self::CRON_HOOK_OBSERVE,
			self::CRON_HOOK_CWV,
			self::CRON_HOOK_AUTO_REDIRECT,
			self::CRON_HOOK_WEEKLY_EMAIL,
		);
		foreach ( $weekly_hooks as $hook ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', $hook );
			}
		}

		add_option( SEO_Agent_AI_Data_Store::OPTION_LAST_RUN, array(), '', false );
	}

	public static function deactivate() {
		$all_hooks = array(
			self::CRON_HOOK_DAILY,
			self::CRON_HOOK_MANUAL,
			self::CRON_HOOK_GSC,
			self::CRON_HOOK_GA4,
			self::CRON_HOOK_REPORT,
			self::CRON_HOOK_SCORE,
			self::CRON_HOOK_DECAY,
			self::CRON_HOOK_LINKS,
			self::CRON_HOOK_PURGE,
			self::CRON_HOOK_CANNIBAL,
			self::CRON_HOOK_IMPROVE,
			self::CRON_HOOK_ORPHAN,
			self::CRON_HOOK_IMAGE_ALTS,
			self::CRON_HOOK_OBSERVE,
			self::CRON_HOOK_CWV,
			self::CRON_HOOK_HEALTH,
			self::CRON_HOOK_AUTO_REDIRECT,
			self::CRON_HOOK_WEEKLY_EMAIL,
		);
		foreach ( $all_hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	public static function maybe_upgrade() {
		$installed = (int) get_option( SEO_Agent_AI_Activity_Log::DB_VERSION_OPTION, 0 );
		if ( $installed < SEO_Agent_AI_Activity_Log::DB_VERSION ) {
			SEO_Agent_AI_Activity_Log::create_table();
		}
		SEO_Agent_AI_DB_Manager::maybe_upgrade();
	}

	/**
	 * Ensure all cron events are scheduled.
	 * Guarded by a 1-hour transient to avoid 9 DB queries on every page load.
	 */
	public function ensure_cron_schedules() {
		if ( get_transient( 'seo_agent_ai_cron_checked' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK_DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK_DAILY );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_GSC ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS + 5 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK_GSC );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_GA4 ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS + 10 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK_GA4 );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_REPORT ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS + 15 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK_REPORT );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_SCORE ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK_SCORE );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_DECAY ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK_DECAY );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_LINKS ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK_LINKS );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_PURGE ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK_PURGE );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_CANNIBAL ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON_HOOK_CANNIBAL );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_IMPROVE ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS + 2 * HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK_IMPROVE );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_ORPHAN ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS + 3 * HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK_ORPHAN );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_IMAGE_ALTS ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS + 30 * MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK_IMAGE_ALTS );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_OBSERVE ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS + 5 * HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK_OBSERVE );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_CWV ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS + 6 * HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK_CWV );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_HEALTH ) ) {
			wp_schedule_event( time() + 6 * HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK_HEALTH );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_AUTO_REDIRECT ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS + 7 * HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK_AUTO_REDIRECT );
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK_WEEKLY_EMAIL ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS + 8 * HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK_WEEKLY_EMAIL );
		}

		set_transient( 'seo_agent_ai_cron_checked', 1, HOUR_IN_SECONDS );
	}

	// -------------------------------------------------------------------
	// Cron callbacks — specialized jobs
	// -------------------------------------------------------------------

	public function run_fetch_gsc() {
		$this->logger->info( 'Starting dedicated GSC keyword history fetch.' );
		$post_types = (array) get_option( 'seo_agent_ai_post_types', array( 'post' ) );
		$posts      = get_posts(
			array(
				'post_type'   => $post_types ?: array( 'post' ),
				'post_status' => 'publish',
				'numberposts' => 100,
			)
		);

		foreach ( $posts as $post ) {
			$url = get_permalink( $post );
			if ( ! $url ) {
				continue;
			}
			$history = $this->gsc_client->get_keyword_history( $url, 90 );
			if ( is_array( $history ) ) {
				foreach ( $history as $kw => $data ) {
					if ( is_string( $kw ) && is_array( $data ) ) {
						SEO_Agent_AI_DB_Manager::upsert_keyword_history( (int) $post->ID, $kw, $data );
					}
				}
			}
			update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_GSC, current_time( 'mysql' ), false );
		}

		$this->logger->info( 'GSC keyword history fetch complete. Posts: ' . count( $posts ) );

		// Detect low-CTR opportunities: pages with impressions > 0 but CTR < 2%.
		// These are queued as gsc_ctr_gap decisions for the recommendation engine.
		$this->detect_ctr_gap_opportunities( $posts );
	}

	/**
	 * Identify posts with GSC impressions but very low click-through rates,
	 * and insert them as pending gsc_ctr_gap decisions so they surface in reports.
	 *
	 * insert_decision() is idempotent — it skips duplicates by (post_id, decision_type, field, status).
	 *
	 * @param WP_Post[] $posts
	 */
	private function detect_ctr_gap_opportunities( array $posts ) {
		foreach ( $posts as $post ) {
			$url     = get_permalink( $post );
			$metrics = $this->gsc_client->get_page_metrics( $url );
			if ( is_wp_error( $metrics ) || ! is_array( $metrics ) ) {
				continue;
			}

			$impressions = (float) ( $metrics['impressions_total'] ?? 0 );
			$ctr         = (float) ( $metrics['ctr_avg'] ?? 0 );
			$position    = (float) ( $metrics['position_avg'] ?? 99 );

			// Only flag pages with real impressions but critically low CTR on page 1.
			if ( $impressions < 50 || $ctr >= 0.02 || $position > 20 ) {
				continue;
			}

			SEO_Agent_AI_DB_Manager::insert_decision(
				array(
					'post_id'         => (int) $post->ID,
					'decision_type'   => 'gsc_ctr_gap',
					'field'           => 'meta_title',
					'proposed_value'  => '',
					'current_value'   => '',
					'confidence'      => 0.85,
					'reasoning'       => sprintf(
						/* translators: 1: impressions count, 2: CTR %, 3: avg position. */
						__(
							'%1$d impressions but only %2$s%% CTR at position %3$s. Optimising the title tag and meta description to better match search intent could significantly increase clicks.',
							'seo-agent-ai'
						),
						(int) $impressions,
						number_format( $ctr * 100, 1 ),
						number_format( $position, 1 )
					),
					'expected_impact' => __( 'High — even a 1% CTR improvement on 50+ impressions yields meaningful traffic gains.', 'seo-agent-ai' ),
					'risk_level'      => 'safe',
					'status'          => SEO_Agent_AI_DB_Manager::STATUS_PENDING,
				)
			);
		}
	}

	public function run_fetch_ga4() {
		$this->logger->info( 'Starting dedicated GA4 engagement metrics fetch.' );
		$quality = $this->ga4_client->get_landing_page_quality( 28, 100 );
		if ( is_array( $quality ) ) {
			foreach ( $quality as $item ) {
				$post_id = url_to_postid( $item['page'] ?? '' );
				if ( $post_id ) {
					SEO_Agent_AI_DB_Manager::upsert_page_insight_engagement( $post_id, $item );
				}
			}
		}
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_GA4, current_time( 'mysql' ), false );
		$this->logger->info( 'GA4 engagement fetch complete.' );
	}

	/**
	 * Weekly cron: pre-fetch Core Web Vitals data via PageSpeed Insights for the
	 * top 50 published posts. Results are stored as transients (24h) and consumed
	 * by the scoring engine and recommendation engine without making live API calls.
	 *
	 * A 0.2s sleep between requests avoids hitting the API rate limit.
	 */
	public function run_fetch_cwv_data() {
		$this->logger->info( 'Starting weekly Core Web Vitals data fetch.' );

		$posts   = $this->get_posts_for_analysis( 50 );
		$fetched = 0;
		$failed  = 0;

		foreach ( $posts as $post ) {
			$url = get_permalink( $post );
			if ( ! $url ) {
				continue;
			}
			$result = $this->pagespeed_client->get_metrics( $url, 'mobile' );
			if ( is_wp_error( $result ) ) {
				++$failed;
				$this->logger->error(
					sprintf( 'CWV fetch failed for post %d: %s', (int) $post->ID, $result->get_error_message() )
				);
			} else {
				++$fetched;
			}
			// Brief pause to stay within the 25k/day unauthenticated quota.
			usleep( 200000 ); // 0.2 seconds.
		}

		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_CWV, current_time( 'mysql' ), false );
		$this->logger->info(
			sprintf( 'CWV data fetch complete. Fetched: %d, Failed: %d.', $fetched, $failed )
		);
	}

	public function run_generate_report() {
		$this->logger->info( 'Generating daily SEO report.' );
		$result = $this->report_engine->generate( '', false );
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_REPORT, current_time( 'mysql' ), false );
		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Report generation failed: ' . $result->get_error_message() );
		} else {
			$this->logger->info( 'Daily report generated.' );
		}
	}

	public function run_score_pages() {
		$this->logger->info( 'Starting weekly SEO scoring pass.' );
		$post_types = (array) get_option( 'seo_agent_ai_post_types', array( 'post' ) );
		$posts      = get_posts(
			array(
				'post_type'   => $post_types ?: array( 'post' ),
				'post_status' => 'publish',
				'numberposts' => 200,
			)
		);

		foreach ( $posts as $post ) {
			$score_data = $this->scoring_engine->score( $post );
			if ( is_array( $score_data ) ) {
				SEO_Agent_AI_DB_Manager::upsert_page_insight( (int) $post->ID, $score_data );
				update_post_meta( (int) $post->ID, '_seo_agent_ai_score', $score_data['overall'] ?? 0 );
			}
		}
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_SCORE, current_time( 'mysql' ), false );
		$this->logger->info( 'Scoring pass complete. Posts: ' . count( $posts ) );
	}

	public function run_detect_decay() {
		$this->logger->info( 'Running content decay detection.' );
		$post_types = (array) get_option( 'seo_agent_ai_post_types', array( 'post' ) );
		$posts      = get_posts(
			array(
				'post_type'   => $post_types ?: array( 'post' ),
				'post_status' => 'publish',
				'numberposts' => 100,
			)
		);

		$flagged = 0;
		foreach ( $posts as $post ) {
			$content_data = $this->content_analyzer->analyze( $post );
			if ( empty( $content_data['content_decay_risk'] ) ) {
				continue;
			}

			$freshness  = isset( $content_data['freshness_score'] ) ? (float) $content_data['freshness_score'] : 0.5;
			$confidence = round( max( 0.50, min( 0.80, 1.0 - $freshness ) ), 3 );

			$rec = array(
				'type'            => 'content_refresh',
				'field'           => 'content',
				'proposed_value'  => '',
				'current_value'   => '',
				'confidence'      => $confidence,
				'reasoning'       => 'Content may be outdated based on publish date and stale year references.',
				'expected_impact' => 'Moderate traffic recovery possible after a content refresh.',
				'risk_level'      => 'safe',
			);
			$this->decision_engine->process( (int) $post->ID, $rec, 0.70, false );
			++$flagged;
		}

		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_DECAY, current_time( 'mysql' ), false );
		$this->logger->info( "Decay detection complete. Flagged: {$flagged}" );
	}

	/**
	 * Weekly cannibalization check.
	 * Reads keyword_history DB table (populated by run_fetch_gsc) — no live API calls.
	 * Pages sharing a high-impression keyword are flagged via the decision engine.
	 */
	public function run_detect_cannibalization() {
		global $wpdb;

		$this->logger->info( 'Running cannibalization detection from keyword history.' );

		$table = esc_sql( SEO_Agent_AI_DB_Manager::keyword_history_table() );
		$since = gmdate( 'Y-m-d', strtotime( '-28 days' ) );

		// Aggregate impressions per (post_id, keyword) over the last 28 days.
		$sql = 'SELECT post_id, keyword,
		        SUM(impressions) AS total_impressions,
		        AVG(position) AS avg_position
		 FROM ' . $table . '
		 WHERE recorded_at >= %s AND impressions > 0
		 GROUP BY post_id, keyword
		 HAVING total_impressions >= 20
		 ORDER BY keyword, total_impressions DESC';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $since ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $rows ) ) {
			$this->logger->info( 'No keyword history data for cannibalization check.' );
			update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_CANNIBAL, current_time( 'mysql' ), false );
			return;
		}

		// Build keyword → [ { post_id, impressions, position } ] map.
		$kw_map = array();
		foreach ( $rows as $row ) {
			$kw = strtolower( trim( (string) $row['keyword'] ) );
			if ( ! isset( $kw_map[ $kw ] ) ) {
				$kw_map[ $kw ] = array();
			}
			$kw_map[ $kw ][] = array(
				'post_id'     => (int) $row['post_id'],
				'impressions' => (int) $row['total_impressions'],
				'position'    => (float) $row['avg_position'],
			);
		}

		$flagged = 0;
		foreach ( $kw_map as $keyword => $pages ) {
			if ( count( $pages ) < 2 ) {
				continue;
			}

			// Sort: best-ranking page first (lowest position number = better).
			usort(
				$pages,
				function ( $a, $b ) {
					return $a['position'] <=> $b['position'];
				}
			);

			$primary           = $pages[0];
			$total_impressions = array_sum( array_column( $pages, 'impressions' ) );

			// Flag each weaker competing page.
			foreach ( array_slice( $pages, 1 ) as $competing ) {
				$confidence = round( min( 0.85, 0.50 + ( $total_impressions / 5000 ) ), 3 );

				$rec = array(
					'type'            => 'cannibalization',
					'field'           => 'content',
					'proposed_value'  => '',
					'current_value'   => '',
					'confidence'      => $confidence,
					'reasoning'       => sprintf(
						/* translators: 1: keyword, 2: primary position, 3: competing position. */
						__( 'Keyword “%1$s” ranks for multiple pages (positions %2$.1f vs %3$.1f). Consider differentiating or consolidating content.', 'seo-agent-ai' ),
						esc_html( $keyword ),
						$primary['position'],
						$competing['position']
					),
					'expected_impact' => 'Reducing keyword cannibalization can improve ranking clarity and overall CTR.',
					'risk_level'      => 'safe',
				);

				$this->decision_engine->process( (int) $competing['post_id'], $rec, 0.70, false );
				++$flagged;
			}
		}

		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_CANNIBAL, current_time( 'mysql' ), false );
		$this->logger->info( "Cannibalization detection complete. Flagged: {$flagged}" );
	}

	public function run_internal_links() {
		$this->logger->info( 'Running internal link opportunity pass.' );
		$dry_run = (bool) get_option( 'seo_agent_ai_debug_mode', false );
		$result  = $this->internal_link_engine->run_pass( 5, $dry_run );
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_LINKS, current_time( 'mysql' ), false );
		$this->logger->info( sprintf( 'Internal links pass complete. Inserted: %d, Skipped: %d', (int) ( $result['inserted'] ?? 0 ), (int) ( $result['skipped'] ?? 0 ) ) );
	}

	public function run_purge_old_data() {
		$this->logger->info( 'Running data purge.' );
		$retention = (int) get_option( 'seo_agent_ai_log_retention_days', 90 );
		SEO_Agent_AI_DB_Manager::purge_old_data( $retention );
		$this->activity_log->purge_old_entries( $retention );
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_PURGE, current_time( 'mysql' ), false );
		$this->logger->info( 'Data purge complete.' );
	}

	/**
	 * Weekly observation pass.
	 *
	 * For every decision that was auto-applied 7–28 days ago with a before-metrics
	 * snapshot, fetch the current GSC page metrics and record the after-snapshot.
	 * Posts where CTR or clicks declined are re-flagged for priority re-analysis so
	 * the plugin can course-correct on the next daily analysis run.
	 */
	public function run_observe_results() {
		$this->logger->info( 'Starting results observation pass.' );

		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-28 days' ) );
		$until = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		$decisions = SEO_Agent_AI_DB_Manager::get_applied_decisions_for_observation( $since, $until );

		$observed = 0;
		$improved = 0;
		$declined = 0;

		foreach ( $decisions as $dec ) {
			$post_id = (int) $dec['post_id'];
			$post    = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$url         = get_permalink( $post_id );
			$current_gsc = $this->gsc_client->get_page_metrics( $url );
			if ( is_wp_error( $current_gsc ) ) {
				continue;
			}

			SEO_Agent_AI_DB_Manager::update_decision_metrics( (int) $dec['id'], null, $current_gsc );

			$before_raw    = $dec['metrics_before'];
			$before        = is_string( $before_raw ) ? json_decode( $before_raw, true ) : array();
			$before        = is_array( $before ) ? $before : array();
			$ctr_before    = (float) ( $before['ctr'] ?? 0.0 );
			$clicks_before = (int) ( $before['clicks'] ?? 0 );
			$ctr_after     = (float) ( $current_gsc['ctr'] ?? 0.0 );
			$clicks_after  = (int) ( $current_gsc['clicks'] ?? 0 );

			++$observed;
			if ( $ctr_after > $ctr_before || $clicks_after > $clicks_before ) {
				++$improved;
			} else {
				++$declined;
				// Flag the post so get_posts_for_analysis() picks it up as
				// a priority target on the next daily analysis run.
				update_post_meta( $post_id, '_seo_agent_ai_needs_reanalysis', '1' );
				$this->logger->info(
					sprintf( 'Post %d showed no improvement after optimization — re-queued.', $post_id )
				);
			}
		}

		$this->logger->info(
			sprintf(
				'Observation pass complete. Observed: %d, Improved: %d, Declined/stalled: %d.',
				$observed,
				$improved,
				$declined
			)
		);
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_OBSERVE, current_time( 'mysql' ), false );
	}

	/**
	 * Weekly cron: find posts scoring below the target threshold and generate
	 * targeted improvements for the weakest SEO dimensions.
	 *
	 * The target threshold is configurable via the `seo_agent_ai_score_target`
	 * option (default 70). Up to 30 lowest-scoring posts are processed per run.
	 * Safe fixes are auto-applied when autopilot is on; everything else is
	 * routed to the pending-approval queue.
	 */
	public function run_improve_low_scoring_posts() {
		$this->logger->info( 'Starting score-targeted improvement pass.' );

		$target    = max( 1, min( 100, (int) get_option( 'seo_agent_ai_score_target', 70 ) ) );
		$autopilot = (bool) get_option( 'seo_agent_ai_autopilot_enabled', false );
		$post_ids  = SEO_Agent_AI_DB_Manager::get_posts_below_score_threshold( $target, 30 );

		if ( empty( $post_ids ) ) {
			$this->logger->info( 'Score improvement pass: no posts below threshold ' . $target . '.' );
			update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_IMPROVE, current_time( 'mysql' ), false );
			return;
		}

		$queued = 0;
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post || $post->post_status !== 'publish' ) {
				continue;
			}

			// Re-score to get fresh dimension data.
			$score_data = $this->scoring_engine->score( $post );
			if ( ! is_array( $score_data ) ) {
				continue;
			}

			SEO_Agent_AI_DB_Manager::upsert_page_insight( $post_id, $score_data );
			update_post_meta( $post_id, '_seo_agent_ai_score', $score_data['overall'] ?? 0 );

			// Skip if the freshly-computed score already meets the target.
			if ( ( $score_data['overall'] ?? 0 ) >= $target ) {
				continue;
			}

			$this->generate_score_improvements( $post, $score_data, $autopilot );
			++$queued;
		}

		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_IMPROVE, current_time( 'mysql' ), false );
		$this->logger->info(
			sprintf(
				'Score improvement pass complete. Checked: %d, queued improvements for: %d (target ≥ %d).',
				count( $post_ids ),
				$queued,
				$target
			)
		);
	}

	/**
	 * Generate, persist, and route improvement recommendations for a single
	 * low-scoring post. Prioritises the weakest scoring dimensions.
	 *
	 * @param WP_Post $post
	 * @param array   $score_data  ScoringEngine output (overall, dimensions, signals, improvements).
	 * @param bool    $autopilot   Whether autopilot mode is active.
	 */
	private function generate_score_improvements( WP_Post $post, array $score_data, $autopilot ) {
		$overall    = (int) ( $score_data['overall'] ?? 0 );
		$dimensions = is_array( $score_data['dimensions'] ?? null ) ? $score_data['dimensions'] : array();
		$target     = max( 1, min( 100, (int) get_option( 'seo_agent_ai_score_target', 70 ) ) );
		$url        = get_permalink( $post );

		// Reuse already-cached API data (populated by daily GSC/GA4 cron jobs).
		$gsc_metrics = $url ? $this->gsc_client->get_page_metrics( $url ) : array();
		$ga4_metrics = $url ? $this->ga4_client->get_page_metrics( $url ) : array();
		$gsc_safe    = is_wp_error( $gsc_metrics ) ? array() : (array) $gsc_metrics;
		$ga4_safe    = is_wp_error( $ga4_metrics ) ? array() : (array) $ga4_metrics;

		$seo_audit = $this->bridge->audit_post( (int) $post->ID, $post );
		$analysis  = $this->analyzer->analyze( $post, $gsc_safe, $ga4_safe, $seo_audit );
		$min_conf  = (float) get_option( 'seo_agent_ai_autopilot_min_confidence', 0.7 );

		$recommendations = $this->recommendation_engine->generate(
			$post,
			$analysis,
			$gsc_safe,
			$ga4_safe,
			$seo_audit,
			$min_conf,
			false
		);

		if ( empty( $recommendations ) ) {
			return;
		}

		// Sort recommendations: weakest dimensions first so the most impactful
		// fixes are processed first within the daily autopilot budget.
		$dim_scores = array();
		foreach ( $dimensions as $dim => $dim_score ) {
			$dim_scores[ $dim ] = (int) $dim_score;
		}
		asort( $dim_scores );
		$weakest_dims = array_keys( $dim_scores );

		usort(
			$recommendations,
			function ( $a, $b ) use ( $weakest_dims ) {
				$a_idx = array_search( $a['field'] ?? '', $weakest_dims, true );
				$b_idx = array_search( $b['field'] ?? '', $weakest_dims, true );
				$a_idx = $a_idx === false ? PHP_INT_MAX : $a_idx;
				$b_idx = $b_idx === false ? PHP_INT_MAX : $b_idx;
				return $a_idx <=> $b_idx;
			}
		);

		$this->data_store->save_recommendations( (int) $post->ID, $recommendations );

		$signal_data = array(
			'signals'      => $analysis['signals'] ?? array(),
			'evidence'     => $analysis['evidence'] ?? array(),
			'score_before' => $overall,
			'score_target' => $target,
		);

		$date_key      = self::DAILY_AP_TRANSIENT_PREFIX . gmdate( 'Y-m-d' );
		$max_daily     = (int) get_option( 'seo_agent_ai_autopilot_max_daily', 5 );
		$applied_today = (int) get_transient( $date_key );

		foreach ( $recommendations as $rec ) {
			$confidence = (float) ( $rec['confidence'] ?? 0.0 );
			$reasoning  = $rec['reasoning'] ?? sprintf(
				/* translators: 1: current score, 2: target score, 3: dimension name. */
				__( 'Score %1$d → target %2$d: improve %3$s dimension.', 'seo-agent-ai' ),
				$overall,
				$target,
				$rec['field'] ?? 'unknown'
			);

			$decision_rec = array(
				'type'            => $rec['type'] ?? 'score_improvement',
				'field'           => $rec['field'] ?? '',
				'proposed_value'  => $rec['proposed_value'] ?? '',
				'current_value'   => $rec['current_value'] ?? '',
				'confidence'      => $confidence,
				'reasoning'       => $reasoning,
				'expected_impact' => $rec['expected_impact'] ?? __( 'Score improvement.', 'seo-agent-ai' ),
				'risk_level'      => $rec['risk'] ?? 'risky',
			);

			// Always record via decision engine — applies when autopilot+auto_apply, queues otherwise.
			$decision = $this->decision_engine->process( (int) $post->ID, $decision_rec, $min_conf, false );

			$rec_type = $rec['type'] ?? '';
			if ( ! $autopilot
				|| 'auto_apply' !== $decision['tier']
				|| ! in_array( $rec_type, self::$autopilot_executable_types, true ) ) {
				continue;
			}

			$is_safe = ( 'safe' === ( $decision['risk_level'] ?? 'risky' ) );
			if ( ! $is_safe && $applied_today >= $max_daily ) {
				continue;
			}

			$result = $this->apply_auto_decision( (int) $post->ID, $rec, $decision, $gsc_safe, $signal_data );
			if ( ! is_wp_error( $result ) && ! $is_safe ) {
				++$applied_today;
				set_transient( $date_key, $applied_today, DAY_IN_SECONDS );
			}
		}
	}

	// -------------------------------------------------------------------
	// Single-post expansion worker
	// -------------------------------------------------------------------

	/**
	 * Execute the AI content expansion for a single post.
	 * Called by the async WP-Cron event scheduled by queue_thin_content_for_expansion().
	 * The result is stored as a pending draft — nothing is auto-published.
	 *
	 * @param int    $post_id         Post to expand.
	 * @param string $expansion_focus Optional keyword/topic focus.
	 * @param array  $gsc_queries     GSC query data for context.
	 */
	public function run_expand_single_post( $post_id, $expansion_focus = '', array $gsc_queries = array() ) {
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post || $post->post_status !== 'publish' ) {
			return;
		}

		$result = $this->content_expander->expand( (int) $post_id, (string) $expansion_focus, $gsc_queries );

		if ( is_wp_error( $result ) ) {
			$this->logger->warning(
				sprintf( 'Content expansion failed for post %d: %s', $post_id, $result->get_error_message() )
			);
		} else {
			$this->logger->info( sprintf( 'Content expansion draft created for post %d.', $post_id ) );
			// After draft is ready, ping IndexNow so Google comes back to crawl
			// once the admin reviews and publishes the draft.
			$url = get_permalink( $post_id );
			if ( $url ) {
				$this->indexnow->ping( (string) $url );
			}
		}
	}

	// -------------------------------------------------------------------
	// IndexNow integration
	// -------------------------------------------------------------------

	/**
	 * Submit a post URL to IndexNow after a fix has been applied.
	 *
	 * @param int $post_id
	 */
	public function on_fix_applied_indexnow( $post_id ) {
		$url = get_permalink( (int) $post_id );
		if ( $url ) {
			$this->indexnow->ping( (string) $url );
		}
	}

	// -------------------------------------------------------------------
	// Thin-content auto-queuer (runs inside daily analysis cron)
	// -------------------------------------------------------------------

	/**
	 * Find published posts with fewer than 300 words that haven't had an AI
	 * expansion queued in the last 30 days, and queue them for content expansion
	 * via the Decision Engine. Capped at 5 posts per daily run to stay within
	 * API rate limits and avoid overwhelming the approval queue.
	 */
	public function queue_thin_content_for_expansion() {
		if ( ! (bool) get_option( 'seo_agent_ai_auto_expand_thin', true ) ) {
			return;
		}

		$post_types = (array) get_option( 'seo_agent_ai_post_types', array( 'post' ) );
		$cap        = (int) apply_filters( 'seo_agent_ai_thin_content_daily_cap', 5 );
		$min_words  = (int) apply_filters( 'seo_agent_ai_thin_content_word_threshold', 300 );

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 30, // scan pool; we'll filter by word count below.
				'orderby'        => 'rand',
				'fields'         => 'all',
				// Exclude posts we've already queued recently (within 30 days).
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => '_seo_agent_ai_expand_queued_at',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_seo_agent_ai_expand_queued_at',
						'value'   => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
						'compare' => '<',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return;
		}

		$queued = 0;
		foreach ( $posts as $post ) {
			if ( $queued >= $cap ) {
				break;
			}

			$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
			if ( $word_count >= $min_words ) {
				continue; // Post is not thin.
			}

			// Fetch GSC signals for context.
			$url         = (string) get_permalink( $post );
			$gsc_metrics = $url ? $this->gsc_client->get_page_metrics( $url ) : array();
			$gsc_safe    = is_wp_error( $gsc_metrics ) ? array() : (array) $gsc_metrics;

			// Build a simple recommendation for the decision engine.
			$recommendation = array(
				'type'        => 'content_expansion',
				'reason'      => sprintf(
					/* translators: 1: word count, 2: threshold */
					__( 'Post has only %1$d words (threshold: %2$d). AI expansion draft queued for review.', 'seo-agent-ai' ),
					$word_count,
					$min_words
				),
				'confidence'  => 0.85,
				'risk'        => 'safe',
				'impact'      => 'medium',
				'source'      => 'thin_content_detector',
				'word_count'  => $word_count,
				'gsc_metrics' => $gsc_safe,
			);

			// Route through the decision engine (creates a pending approval record).
			$this->decision_engine->process( $post->ID, $recommendation, 0.95 ); // High threshold — always pending.

			// Kick off the AI expansion draft asynchronously via a scheduled action.
			wp_schedule_single_event(
				time() + ( $queued * 30 ), // Stagger by 30 s each to avoid rate limits.
				'seo_agent_ai_expand_single_post',
				array( $post->ID, '', $gsc_safe )
			);

			update_post_meta( $post->ID, '_seo_agent_ai_expand_queued_at', current_time( 'mysql' ) );
			++$queued;

			$this->logger->info(
				sprintf( 'Thin content queued for expansion: post %d (%d words).', $post->ID, $word_count )
			);
		}
	}

	// -------------------------------------------------------------------
	// Manual analysis trigger
	// -------------------------------------------------------------------

	public function handle_manual_analysis() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'seo-agent-ai' ) );
		}
		check_admin_referer( 'seo_agent_ai_run_analysis' );

		if ( ! wp_next_scheduled( self::CRON_HOOK_MANUAL ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK_MANUAL );
		}

		wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'analysis_scheduled', admin_url( 'admin.php?page=seo-agent-ai' ) ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Core analysis pipeline (cron + manual)
	// -------------------------------------------------------------------

	public function run_daily_analysis() {
		if ( ! $this->acquire_lock() ) {
			return;
		}

		$started_at    = current_time( 'mysql' );
		$processed     = 0;
		$with_recs     = 0;
		$failed        = 0;
		$autopilot     = (bool) get_option( 'seo_agent_ai_autopilot_enabled', false );
		$log_retention = (int) get_option( 'seo_agent_ai_log_retention_days', 90 );

		try {
			$posts = $this->get_posts_for_analysis( 50 );

			foreach ( $posts as $post ) {
				++$processed;
				$result = $this->analyze_single_post( $post, $autopilot );
				if ( $result['had_api_failure'] ) {
					++$failed;
				}
				if ( $result['had_recommendations'] ) {
					++$with_recs;
				}
			}

			$this->update_api_failure_tracker( $processed, $failed );

			$this->data_store->set_last_run(
				array(
					'started_at'                 => $started_at,
					'finished_at'                => current_time( 'mysql' ),
					'processed_posts'            => $processed,
					'posts_with_recommendations' => $with_recs,
					'failed_posts'               => $failed,
					'mode'                       => wp_doing_cron() ? 'cron' : 'manual',
				)
			);

			if ( $log_retention > 0 ) {
				$this->activity_log->purge_old_entries( $log_retention );
			}

			// Heal any decisions stuck at STATUS_APPROVED.
			$this->drain_approved_decisions();

			// When autopilot is enabled, also drain old pending decisions — this applies
			// content_expansion drafts and internal-link suggestions that were queued but
			// not yet actioned (e.g. from before autopilot was turned on).
			if ( $autopilot ) {
				$this->drain_pending_decisions();
			}

			update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_DAILY, current_time( 'mysql' ), false );

		} finally {
			$this->release_lock();
		}
	}

	// -------------------------------------------------------------------
	// Per-post analysis (shared by cron, manual, and batch AJAX)
	// -------------------------------------------------------------------

	private function analyze_single_post( WP_Post $post, $autopilot = false ) {
		$url = get_permalink( $post );
		if ( ! $url ) {
			return array(
				'had_recommendations' => false,
				'had_api_failure'     => false,
			);
		}

		$gsc_metrics = $this->gsc_client->get_page_metrics( $url );
		$ga4_metrics = $this->ga4_client->get_page_metrics( $url );
		$seo_audit   = $this->bridge->audit_post( (int) $post->ID, $post );

		$had_api_failure = is_wp_error( $gsc_metrics ) || is_wp_error( $ga4_metrics );
		$gsc_safe        = is_wp_error( $gsc_metrics ) ? array() : $gsc_metrics;
		$ga4_safe        = is_wp_error( $ga4_metrics ) ? array() : $ga4_metrics;

		if ( $had_api_failure ) {
			$msg = is_wp_error( $gsc_metrics ) ? $gsc_metrics->get_error_message() : '';
			if ( $msg === '' && is_wp_error( $ga4_metrics ) ) {
				$msg = $ga4_metrics->get_error_message();
			}
			update_option( self::OPTION_LAST_API_ERROR, $msg, false );
		}

		$analysis        = $this->analyzer->analyze( $post, $gsc_safe, $ga4_safe, $seo_audit );
		$autopilot_conf  = (float) get_option( 'seo_agent_ai_autopilot_min_confidence', 0.7 );
		$recommendations = $this->recommendation_engine->generate(
			$post,
			$analysis,
			$gsc_safe,
			$ga4_safe,
			$seo_audit,
			$autopilot_conf,
			false
		);

		$metrics = array(
			'gsc'        => $gsc_safe,
			'ga4'        => $ga4_safe,
			'analysis'   => $analysis,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( is_wp_error( $gsc_metrics ) ) {
			$metrics['gsc_error'] = $gsc_metrics->get_error_message();
		}
		if ( is_wp_error( $ga4_metrics ) ) {
			$metrics['ga4_error'] = $ga4_metrics->get_error_message();
		}

		$this->data_store->save_post_metrics( (int) $post->ID, $metrics );
		$this->data_store->save_recommendations( (int) $post->ID, $recommendations );

		update_post_meta( (int) $post->ID, '_seo_agent_ai_last_analyzed', current_time( 'mysql' ) );

		$had_recs = ! empty( $recommendations );
		if ( $had_recs ) {
			// Route ALL recommendations through the decision engine so they are
			// recorded in ai_decisions and — when autopilot is on — auto-applied.
			$this->route_recommendations( (int) $post->ID, $recommendations, $gsc_safe, $analysis, $autopilot );
		}

		// Clear the re-analysis flag set by the observation pass.
		delete_post_meta( (int) $post->ID, '_seo_agent_ai_needs_reanalysis' );

		return array(
			'had_recommendations' => $had_recs,
			'had_api_failure'     => $had_api_failure,
			'title'               => $post->post_title,
		);
	}

	// -------------------------------------------------------------------
	// Post selection: round-robin across configured post types
	// -------------------------------------------------------------------

	/**
	 * Return $count posts for analysis.
	 *
	 * Up to half the slots are filled with posts flagged as high-priority by the
	 * GSC opportunity analyzer (page-2 rankings, CTR anomalies, declining pages).
	 * The remaining slots use a round-robin offset so every post is eventually
	 * covered. Priority posts that also appear in the round-robin slice are
	 * deduplicated so no post is analyzed twice in one run.
	 *
	 * @param int $count
	 * @return WP_Post[]
	 */
	private function get_posts_for_analysis( $count = 50 ) {
		$post_types = (array) get_option( 'seo_agent_ai_post_types', array( 'post' ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		// ------------------------------------------------------------------
		// Priority posts from GSC site-level opportunity data (no API call —
		// reads from the 6-hour transient populated by run_fetch_gsc).
		// ------------------------------------------------------------------
		$priority_ids  = array();
		$opportunities = $this->gsc_opportunity_analyzer->get_opportunities();

		if ( is_array( $opportunities ) ) {
			$opp_urls = array();
			foreach ( array( 'page2_pages', 'ctr_anomalies', 'declining_pages' ) as $bucket ) {
				foreach ( $opportunities[ $bucket ] ?? array() as $item ) {
					if ( ! empty( $item['page'] ) ) {
						$opp_urls[] = (string) $item['page'];
					}
				}
			}

			foreach ( array_unique( $opp_urls ) as $url ) {
				$pid = url_to_postid( $url );
				if ( $pid ) {
					$priority_ids[] = (int) $pid;
				}
			}
			$priority_ids = array_unique( $priority_ids );
		}

		// Also prioritise posts flagged by the observation pass as needing re-analysis.
		$reanalysis_posts = get_posts(
			array(
				'post_type'     => 'any',
				'post_status'   => 'publish',
				'numberposts'   => 20,
				'no_found_rows' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'      => '_seo_agent_ai_needs_reanalysis',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'    => '1',
			)
		);
		foreach ( $reanalysis_posts as $rp ) {
			$priority_ids[] = (int) $rp->ID;
		}
		$priority_ids = array_unique( $priority_ids );

		$priority_posts = array();
		$priority_slots = (int) floor( $count / 2 );

		if ( ! empty( $priority_ids ) ) {
			$priority_posts = get_posts(
				array(
					'post__in'      => array_slice( $priority_ids, 0, $priority_slots ),
					'post_type'     => 'any',
					'post_status'   => 'publish',
					'numberposts'   => $priority_slots,
					'orderby'       => 'post__in',
					'no_found_rows' => true,
				)
			);
		}

		$seen_ids = array_map(
			function ( $p ) {
				return (int) $p->ID;
			},
			$priority_posts
		);

		// ------------------------------------------------------------------
		// Round-robin fill for remaining slots.
		// ------------------------------------------------------------------
		$total = 0;
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}

		$round_robin_posts = array();
		if ( $total > 0 ) {
			$offset = (int) get_option( 'seo_agent_ai_analysis_offset', 0 );
			if ( $offset >= $total ) {
				$offset = 0;
			}

			$remaining = $count - count( $priority_posts );
			$batch     = get_posts(
				array(
					'post_type'     => $post_types,
					'post_status'   => 'publish',
					'numberposts'   => (int) $remaining,
					'offset'        => $offset,
					'orderby'       => 'ID',
					'order'         => 'ASC',
					'no_found_rows' => true,
				)
			);

			// Advance offset for next run.
			$new_offset = $offset + count( $batch );
			update_option( 'seo_agent_ai_analysis_offset', $new_offset >= $total ? 0 : $new_offset, false );

			// Dedup: skip posts already in the priority list.
			foreach ( $batch as $p ) {
				if ( ! in_array( (int) $p->ID, $seen_ids, true ) ) {
					$round_robin_posts[] = $p;
					$seen_ids[]          = (int) $p->ID;
				}
			}
		}

		return array_merge( $priority_posts, $round_robin_posts );
	}

	// -------------------------------------------------------------------
	// AJAX: interactive batch analysis
	// -------------------------------------------------------------------

	public function ajax_analyze_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
			return;
		}
		check_ajax_referer( 'seo_agent_ai_analyze_batch' );

		if ( get_transient( self::ANALYSIS_LOCK_KEY ) ) {
			wp_send_json_error( __( 'Analysis already in progress (scheduled task). Please wait.', 'seo-agent-ai' ) );
			return;
		}

		$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch_size = 5;
		$autopilot  = (bool) get_option( 'seo_agent_ai_autopilot_enabled', false );

		// For AJAX batch we get a consistent snapshot of posts for the session.
		$post_types = (array) get_option( 'seo_agent_ai_post_types', array( 'post' ) );
		$posts      = get_posts(
			array(
				'post_type'   => $post_types ?: array( 'post' ),
				'post_status' => 'publish',
				'numberposts' => 200,
				'orderby'     => 'modified',
				'order'       => 'DESC',
			)
		);

		$total = count( $posts );

		if ( $offset === 0 ) {
			$state = array(
				'with_recs'  => 0,
				'failed'     => 0,
				'started_at' => current_time( 'mysql' ),
			);
			set_transient( self::BATCH_ANALYSIS_KEY, $state, 30 * MINUTE_IN_SECONDS );
		} else {
			$state = get_transient( self::BATCH_ANALYSIS_KEY );
			if ( ! is_array( $state ) ) {
				$state = array(
					'with_recs'  => 0,
					'failed'     => 0,
					'started_at' => current_time( 'mysql' ),
				);
			}
		}

		$batch         = array_slice( $posts, $offset, $batch_size );
		$current_title = '';

		foreach ( $batch as $post ) {
			$current_title = $post->post_title;
			$result        = $this->analyze_single_post( $post, $autopilot );
			if ( $result['had_api_failure'] ) {
				++$state['failed'];
			}
			if ( $result['had_recommendations'] ) {
				++$state['with_recs'];
			}
		}

		$processed = min( $offset + count( $batch ), $total );
		$done      = ( $processed >= $total );

		set_transient( self::BATCH_ANALYSIS_KEY, $state, 30 * MINUTE_IN_SECONDS );

		if ( $done ) {
			$this->data_store->set_last_run(
				array(
					'started_at'                 => $state['started_at'],
					'finished_at'                => current_time( 'mysql' ),
					'processed_posts'            => $total,
					'posts_with_recommendations' => (int) $state['with_recs'],
					'failed_posts'               => (int) $state['failed'],
					'mode'                       => 'manual',
				)
			);
			$log_retention = (int) get_option( 'seo_agent_ai_log_retention_days', 90 );
			if ( $log_retention > 0 ) {
				$this->activity_log->purge_old_entries( $log_retention );
			}
			$this->update_api_failure_tracker( $total, (int) $state['failed'] );
			delete_transient( self::BATCH_ANALYSIS_KEY );
		}

		wp_send_json_success(
			array(
				'processed'     => $processed,
				'total'         => $total,
				'percent'       => $total > 0 ? (int) round( ( $processed / $total ) * 100 ) : 100,
				'done'          => $done,
				'current_title' => $current_title,
				'with_recs'     => (int) $state['with_recs'],
				'failed'        => (int) $state['failed'],
			)
		);
	}

	// -------------------------------------------------------------------
	// Autopilot: route recommendations through the decision engine
	// -------------------------------------------------------------------

	/**
	 * Route every recommendation through the decision engine so it is recorded
	 * in ai_decisions. When autopilot is on and the engine classifies a rec as
	 * auto_apply, the change is executed immediately within the daily budget.
	 *
	 * @param int   $post_id
	 * @param array $recommendations
	 * @param array $gsc_metrics  Current GSC page metrics (stored as before-snapshot).
	 * @param array $analysis     Analyzer output (for signal_data).
	 * @param bool  $autopilot
	 */
	/**
	 * Types the plugin can actually execute automatically.
	 * Content-generation types are included but subject to a tighter daily
	 * budget check inside apply_auto_decision() — they save a pending draft
	 * for human review rather than publishing anything directly.
	 */
	private static $autopilot_executable_types = array(
		'meta_update',
		'monitor_decline',
		'schema_update',
		'internal_link_needed',
		'content_expansion',
		'content_refresh_plan',
	);

	private function route_recommendations( $post_id, array $recommendations, array $gsc_metrics, array $analysis, $autopilot ) {
		$autopilot_threshold = (float) get_option( 'seo_agent_ai_autopilot_min_confidence', 0.7 );
		$max_daily           = (int) get_option( 'seo_agent_ai_autopilot_max_daily', 5 );
		$date_key            = self::DAILY_AP_TRANSIENT_PREFIX . gmdate( 'Y-m-d' );
		$applied_today       = (int) get_transient( $date_key );

		$signal_data = array(
			'signals'  => $analysis['signals'] ?? array(),
			'evidence' => $analysis['evidence'] ?? array(),
		);

		foreach ( $recommendations as $rec ) {
			// Record every rec in ai_decisions and classify it.
			$decision = $this->decision_engine->process( $post_id, $rec, $autopilot_threshold );

			if ( ! $autopilot || 'auto_apply' !== $decision['tier'] ) {
				continue;
			}

			$type = $rec['type'] ?? '';

			// Skip types that require content generation — they stay as pending_approval.
			if ( ! in_array( $type, self::$autopilot_executable_types, true ) ) {
				continue;
			}

			$is_safe = ( 'safe' === ( $decision['risk_level'] ?? 'risky' ) );

			// SAFE decisions are applied without counting against the daily budget.
			// RISKY decisions are gated by the daily budget to avoid over-changing.
			if ( ! $is_safe && $applied_today >= $max_daily ) {
				continue;
			}

			$result = $this->apply_auto_decision( $post_id, $rec, $decision, $gsc_metrics, $signal_data );
			if ( ! is_wp_error( $result ) && ! $is_safe ) {
				++$applied_today;
				set_transient( $date_key, $applied_today, DAY_IN_SECONDS );
			}
		}
	}

	/**
	 * Execute an auto_apply decision: run the appropriate action for the rec
	 * type, mark the decision as applied, and snapshot the before-metrics.
	 *
	 * @param int   $post_id
	 * @param array $rec       Full recommendation array.
	 * @param array $decision  Result from decision_engine->process().
	 * @param array $gsc_metrics GSC snapshot to store as before-metrics.
	 * @param array $signal_data Analyzer evidence for the activity log.
	 * @return true|WP_Error
	 */
	private function apply_auto_decision( $post_id, array $rec, array $decision, array $gsc_metrics, array $signal_data ) {
		$type = $rec['type'] ?? '';

		if ( 'internal_link_needed' === $type ) {
			$result = $this->internal_link_engine->run_for_post( $post_id );
		} elseif ( 'schema_update' === $type ) {
			update_post_meta( $post_id, '_seo_agent_ai_schema_approved', 1 );
			$result = true;
		} elseif ( 'content_expansion' === $type ) {
			$focus   = isset( $rec['proposed']['focus_topic'] ) ? (string) $rec['proposed']['focus_topic'] : '';
			$queries = isset( $rec['proposed']['gsc_queries'] ) && is_array( $rec['proposed']['gsc_queries'] ) ? $rec['proposed']['gsc_queries'] : array();
			$intent  = isset( $rec['proposed']['search_intent'] ) ? (string) $rec['proposed']['search_intent'] : '';
			$result  = $this->content_expander->expand( $post_id, $focus, $queries, $intent );
		} elseif ( 'content_refresh_plan' === $type ) {
			$queries = isset( $rec['proposed']['gsc_queries'] ) && is_array( $rec['proposed']['gsc_queries'] ) ? $rec['proposed']['gsc_queries'] : array();
			$intent  = isset( $rec['proposed']['search_intent'] ) ? (string) $rec['proposed']['search_intent'] : '';
			$result  = $this->content_expander->refresh( $post_id, $queries, $intent );
		} else {
			$result = $this->fix_executor->apply(
				$post_id,
				$rec,
				SEO_Agent_AI_Activity_Log::TRIGGER_AUTOPILOT,
				$signal_data
			);
		}

		if ( ! is_wp_error( $result ) ) {
			$decision_id = (int) ( $decision['decision_id'] ?? 0 );
			if ( $decision_id > 0 ) {
				$this->decision_engine->mark_applied( $decision_id );
				// Store current GSC metrics as the before-snapshot for the
				// observation pass that will run 7–28 days later.
				if ( ! empty( $gsc_metrics ) ) {
					SEO_Agent_AI_DB_Manager::update_decision_metrics( $decision_id, $gsc_metrics, null );
				}
			}
		}

		return $result;
	}

	// -------------------------------------------------------------------
	// Manual "Approve & Apply" handler
	// -------------------------------------------------------------------
	// -------------------------------------------------------------------
	// Autopilot: drain approved-but-not-yet-executed decisions
	// -------------------------------------------------------------------

	/**
	 * Find decisions stuck in STATUS_APPROVED and execute them.
	 *
	 * This heals a stuck state that can occur when the approve step
	 * succeeded but the execute step did not (e.g. a DB timeout, a PHP
	 * exception, or the decision being approved via WP-CLI without the
	 * execute call). Called at the end of every daily analysis run.
	 */
	private function drain_approved_decisions() {
		$approved = SEO_Agent_AI_DB_Manager::get_decisions(
			array(
				'status' => SEO_Agent_AI_DB_Manager::STATUS_APPROVED,
				'limit'  => 100,
			)
		);

		foreach ( $approved as $dec ) {
			$post_id = (int) $dec['post_id'];
			$type    = $dec['decision_type'] ?? '';
			$field   = $dec['field'] ?? '';
			$value   = $dec['proposed_value'] ?? '';
			$dec_id  = (int) $dec['id'];

			if ( 'schema_update' === $type ) {
				update_post_meta( $post_id, '_seo_agent_ai_schema_approved', 1 );
				$this->decision_engine->mark_applied( $dec_id );

			} elseif ( 'internal_link_needed' === $type ) {
				$this->internal_link_engine->run_for_post( $post_id );
				$this->decision_engine->mark_applied( $dec_id );

			} elseif ( in_array( $type, array( 'meta_update', 'monitor_decline' ), true ) ) {
				$proposed = array();
				if ( 'meta_title' === $field ) {
					$proposed['meta_title'] = $value;
				} elseif ( 'meta_description' === $field ) {
					$proposed['meta_description'] = $value;
				} else {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						$proposed = $decoded;
					}
				}

				if ( ! empty( $proposed ) ) {
					$result = $this->fix_executor->apply(
						$post_id,
						array(
							'type'       => $type,
							'risk'       => 'safe',
							'proposed'   => $proposed,
							'reason'     => $dec['reasoning'] ?? '',
							'confidence' => (float) ( $dec['confidence'] ?? 0.7 ),
						),
						SEO_Agent_AI_Activity_Log::TRIGGER_AUTOPILOT
					);
					if ( ! is_wp_error( $result ) ) {
						$this->decision_engine->mark_applied( $dec_id );
					}
				}
			} else {
				// Unknown/unexecutable type — mark applied so it doesn't block the queue.
				$this->decision_engine->mark_applied( $dec_id );
			}
		}

		if ( ! empty( $approved ) ) {
			$this->logger->info( sprintf( 'Drained %d approved-but-unexecuted decisions.', count( $approved ) ) );
		}
	}

	// -------------------------------------------------------------------
	// Autopilot: drain existing pending decisions
	// -------------------------------------------------------------------

	private function drain_pending_decisions() {
		$pending = SEO_Agent_AI_DB_Manager::get_decisions(
			array(
				'status' => SEO_Agent_AI_DB_Manager::STATUS_PENDING,
				'limit'  => 500,
			)
		);

		$processed = 0;

		foreach ( $pending as $dec ) {
			$dec_id  = (int) $dec['id'];
			$post_id = (int) $dec['post_id'];
			$type    = $dec['decision_type'] ?? '';
			$field   = $dec['field'] ?? '';
			$value   = $dec['proposed_value'] ?? '';

			if ( 'content_expansion' === $type || 'content_refresh_plan' === $type ) {
				// Skip if a draft already exists for this post.
				if ( get_post_meta( $post_id, '_seo_agent_ai_pending_draft_id', true ) ) {
					continue;
				}
				if ( 'content_expansion' === $type ) {
					$result = $this->content_expander->expand( $post_id );
				} else {
					$result = $this->content_expander->refresh( $post_id );
				}
				if ( ! is_wp_error( $result ) ) {
					$this->decision_engine->mark_applied( $dec_id );
					++$processed;
				}
				continue;
			}

			if ( 'internal_link_needed' === $type ) {
				$this->internal_link_engine->run_for_post( $post_id );
				$this->decision_engine->mark_applied( $dec_id );
				++$processed;
				continue;
			}

			if ( 'schema_update' === $type ) {
				update_post_meta( $post_id, '_seo_agent_ai_schema_approved', 1 );
				$this->decision_engine->mark_applied( $dec_id );
				++$processed;
				continue;
			}

			$proposed = array();
			if ( 'meta_title' === $field ) {
				$proposed['meta_title'] = $value;
			} elseif ( 'meta_description' === $field ) {
				$proposed['meta_description'] = $value;
			} else {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					$proposed = $decoded;
				}
			}

			if ( ! empty( $proposed ) ) {
				$result = $this->fix_executor->apply(
					$post_id,
					array(
						'type'       => $type,
						'risk'       => 'safe',
						'proposed'   => $proposed,
						'reason'     => $dec['reasoning'] ?? '',
						'confidence' => (float) ( $dec['confidence'] ?? 0.7 ),
					),
					SEO_Agent_AI_Activity_Log::TRIGGER_AUTOPILOT
				);
				if ( ! is_wp_error( $result ) ) {
					$this->decision_engine->mark_applied( $dec_id );
					++$processed;
				}
			}
		}

		$this->logger->info( sprintf( 'Autopilot drain: processed %d of %d pending decisions.', $processed, count( $pending ) ) );
	}

	// -------------------------------------------------------------------

	public function handle_apply_fix() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'seo-agent-ai' ) );
		}
		check_admin_referer( 'seo_agent_ai_apply_fix' );

		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$rec_index = isset( $_POST['rec_index'] ) ? absint( $_POST['rec_index'] ) : -1;

		if ( ! $post_id || $rec_index < 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'invalid_input', admin_url( 'admin.php?page=seo-agent-ai' ) ) );
			exit;
		}

		$recommendations = $this->data_store->get_recommendations( $post_id );
		if ( ! isset( $recommendations[ $rec_index ] ) ) {
			wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'recommendation_not_found', admin_url( 'admin.php?page=seo-agent-ai' ) ) );
			exit;
		}

		$metrics     = $this->data_store->get_post_metrics( $post_id );
		$analysis    = isset( $metrics['analysis'] ) ? $metrics['analysis'] : array();
		$signal_data = array(
			'signals'  => isset( $analysis['signals'] ) ? $analysis['signals'] : array(),
			'evidence' => isset( $analysis['evidence'] ) ? $analysis['evidence'] : array(),
		);

		$result = $this->fix_executor->apply(
			$post_id,
			$recommendations[ $rec_index ],
			SEO_Agent_AI_Activity_Log::TRIGGER_MANUAL,
			$signal_data
		);

		$notice = is_wp_error( $result ) ? 'apply_failed' : 'fix_applied';
		wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', $notice, admin_url( 'admin.php?page=seo-agent-ai' ) ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Rollback: post-meta backup (from Overview page)
	// -------------------------------------------------------------------

	public function handle_rollback_backup() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'seo-agent-ai' ) );
		}
		check_admin_referer( 'seo_agent_ai_rollback_backup' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'invalid_input', admin_url( 'admin.php?page=seo-agent-ai' ) ) );
			exit;
		}

		$result = $this->fix_executor->rollback( $post_id );
		$notice = is_wp_error( $result ) ? 'rollback_failed' : 'rollback_done';
		wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', $notice, admin_url( 'admin.php?page=seo-agent-ai' ) ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Rollback: activity log entry (from Report page)
	// -------------------------------------------------------------------

	public function handle_activity_rollback() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'seo-agent-ai' ) );
		}
		check_admin_referer( 'seo_agent_ai_rollback' );

		$log_id  = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $log_id || ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'invalid_input', admin_url( 'admin.php?page=seo-agent-ai-report' ) ) );
			exit;
		}

		$entry = $this->activity_log->get_entry( $log_id );
		if ( ! $entry || (int) $entry['post_id'] !== $post_id ) {
			wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'rollback_failed', admin_url( 'admin.php?page=seo-agent-ai-report' ) ) );
			exit;
		}

		$field  = (string) $entry['field_changed'];
		$before = (string) $entry['value_before'];

		$bridge_field_map = array(
			'meta_title'       => 'title',
			'meta_description' => 'description',
		);

		if ( isset( $bridge_field_map[ $field ] ) ) {
			$keys = $this->bridge->get_all_backup_keys( $bridge_field_map[ $field ] );
			foreach ( $keys as $meta_key ) {
				update_post_meta( $post_id, $meta_key, $before );
			}

			$this->activity_log->log(
				$post_id,
				SEO_Agent_AI_Activity_Log::TRIGGER_ROLLBACK,
				$field,
				(string) $entry['value_after'],
				$before,
				/* translators: %d: activity log entry id. */
				sprintf( __( 'Rolled back log entry #%d.', 'seo-agent-ai' ), $log_id ),
				array(),
				1.0,
				SEO_Agent_AI_Activity_Log::TRIGGER_ROLLBACK
			);

			$this->activity_log->update_status( $log_id, SEO_Agent_AI_Activity_Log::STATUS_ROLLED_BACK );
		}

		wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'rollback_done', admin_url( 'admin.php?page=seo-agent-ai-report' ) ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Settings save
	// -------------------------------------------------------------------

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'seo-agent-ai' ) );
		}
		check_admin_referer( 'seo_agent_ai_save_settings' );

		$client_id     = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
		$client_secret = isset( $_POST['google_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) : '';
		$gsc_site_url  = isset( $_POST['gsc_site_url'] ) ? sanitize_text_field( wp_unslash( $_POST['gsc_site_url'] ) ) : '';
		$ga4_property  = isset( $_POST['ga4_property_id'] ) ? preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['ga4_property_id'] ) ) ) : '';
		$gemini_key    = isset( $_POST['gemini_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_api_key'] ) ) : '';
		$autopilot     = ! empty( $_POST['autopilot_enabled'] );
		$max_daily     = isset( $_POST['autopilot_max_daily'] ) ? max( 1, min( 50, absint( $_POST['autopilot_max_daily'] ) ) ) : 5;
		$min_conf      = isset( $_POST['autopilot_min_confidence'] ) ? round( min( 1.0, max( 0.1, (float) sanitize_text_field( wp_unslash( $_POST['autopilot_min_confidence'] ) ) ) ), 2 ) : 0.7;
		$log_retention = isset( $_POST['log_retention_days'] ) ? max( 7, min( 730, absint( $_POST['log_retention_days'] ) ) ) : 90;
		$score_target  = isset( $_POST['score_target'] ) ? max( 1, min( 100, absint( $_POST['score_target'] ) ) ) : 70;

		$post_types_raw = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
			? array_map( 'sanitize_key', $_POST['post_types'] )
			: array( 'post' );
		// Allowlist against actually registered public post types.
		$valid_types = array_keys( get_post_types( array( 'public' => true ) ) );
		$post_types  = array_values( array_intersect( $post_types_raw, $valid_types ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		// OpenAI / AI provider settings.
		$ai_provider   = isset( $_POST['ai_provider'] ) ? sanitize_key( $_POST['ai_provider'] ) : 'gemini';
		$openai_key    = isset( $_POST['openai_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_api_key'] ) ) : '';
		$openai_url    = isset( $_POST['openai_base_url'] ) ? esc_url_raw( wp_unslash( $_POST['openai_base_url'] ) ) : '';
		$openai_model  = isset( $_POST['openai_model'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_model'] ) ) : '';
		$email_reports = ! empty( $_POST['email_reports'] );
		$email_address = isset( $_POST['email_address'] ) ? sanitize_email( wp_unslash( $_POST['email_address'] ) ) : '';

		if ( ! in_array( $ai_provider, array( 'gemini', 'openai', 'auto' ), true ) ) {
			$ai_provider = 'gemini';
		}

		if ( $client_id !== '' ) {
			update_option( SEO_Agent_AI_Google_OAuth::OPTION_CLIENT_ID, $client_id, false );
		}
		if ( $client_secret !== '' ) {
			update_option( SEO_Agent_AI_Google_OAuth::OPTION_CLIENT_SECRET, SEO_Agent_AI_Crypto::encrypt( $client_secret ), false );
		}
		if ( $gemini_key !== '' ) {
			update_option( SEO_Agent_AI_Gemini_Client::OPTION_API_KEY, SEO_Agent_AI_Crypto::encrypt( $gemini_key ), false );
		}
		if ( $openai_key !== '' ) {
			update_option( SEO_Agent_AI_OpenAI_Client::OPTION_API_KEY, SEO_Agent_AI_Crypto::encrypt( $openai_key ), false );
		}

		update_option( SEO_Agent_AI_GSC_Client::OPTION_GSC_SITE_URL, $gsc_site_url, false );
		update_option( SEO_Agent_AI_GA4_Client::OPTION_GA4_PROPERTY_ID, $ga4_property, false );
		$was_autopilot = (bool) get_option( 'seo_agent_ai_autopilot_enabled', false );
		update_option( 'seo_agent_ai_autopilot_enabled', $autopilot, false );

		// If autopilot was just switched ON, immediately drain the pending queue.
		if ( $autopilot && ! $was_autopilot ) {
			$this->drain_pending_decisions();
		}

		update_option( 'seo_agent_ai_autopilot_max_daily', $max_daily, false );
		update_option( 'seo_agent_ai_autopilot_min_confidence', $min_conf, false );
		update_option( 'seo_agent_ai_log_retention_days', $log_retention, false );
		update_option( 'seo_agent_ai_score_target', $score_target, false );
		update_option( 'seo_agent_ai_post_types', $post_types, false );
		update_option( 'seo_agent_ai_ai_provider', $ai_provider, false );
		update_option( SEO_Agent_AI_OpenAI_Client::OPTION_BASE_URL, $openai_url, false );
		update_option( SEO_Agent_AI_OpenAI_Client::OPTION_MODEL, $openai_model, false );
		update_option( 'seo_agent_ai_email_reports', $email_reports, false );
		update_option( 'seo_agent_ai_email_address', $email_address, false );

		wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'settings_saved', admin_url( 'admin.php?page=seo-agent-ai-settings' ) ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Connection test
	// -------------------------------------------------------------------

	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'seo-agent-ai' ) );
		}
		check_admin_referer( 'seo_agent_ai_test_connection' );

		$gsc_result       = $this->gsc_client->test_connection();
		$analytics_result = $this->ga4_client->test_connection();

		if ( ! is_wp_error( $gsc_result ) && ! is_wp_error( $analytics_result ) ) {
			delete_option( self::OPTION_API_FAILURES );
			delete_option( self::OPTION_LAST_API_ERROR );
			delete_transient( 'seo_agent_ai_auth_health' );
		}

		set_transient(
			self::CONNECTION_TEST_TRANSIENT,
			array(
				'gsc'       => is_wp_error( $gsc_result )
					? array(
						'success' => false,
						'message' => $gsc_result->get_error_message(),
					)
					: array(
						'success' => true,
						'message' => isset( $gsc_result['message'] ) ? (string) $gsc_result['message'] : __( 'Search Console connected.', 'seo-agent-ai' ),
					),
				'analytics' => is_wp_error( $analytics_result )
					? array(
						'success' => false,
						'message' => $analytics_result->get_error_message(),
					)
					: array(
						'success' => true,
						'message' => isset( $analytics_result['message'] ) ? (string) $analytics_result['message'] : __( 'Analytics connected.', 'seo-agent-ai' ),
					),
			),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'connection_tested', admin_url( 'admin.php?page=seo-agent-ai-settings' ) ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Google disconnect
	// -------------------------------------------------------------------

	public function handle_google_disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'seo-agent-ai' ) );
		}
		check_admin_referer( 'seo_agent_ai_google_disconnect' );
		$this->oauth->disconnect();
		wp_safe_redirect( add_query_arg( 'seo_agent_ai_notice', 'google_disconnected', admin_url( 'admin.php?page=seo-agent-ai-connect' ) ) );
		exit;
	}

	// -------------------------------------------------------------------
	// OAuth callback (admin_init — before any page HTML is output)
	// -------------------------------------------------------------------

	public function maybe_handle_oauth_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'seo-agent-ai-connect' !== $page ) {
			return;
		}

		$code  = filter_input( INPUT_GET, 'code', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$state = filter_input( INPUT_GET, 'state', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$error = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( ! $code && ! $error ) {
			return;
		}

		if ( $error ) {
			$msg = sanitize_text_field( wp_unslash( (string) $error ) );
			wp_safe_redirect(
				add_query_arg(
					'seo_agent_ai_oauth_error',
					rawurlencode( $msg ),
					admin_url( 'admin.php?page=seo-agent-ai-connect' )
				)
			);
			exit;
		}

		$result = $this->oauth->handle_callback(
			sanitize_text_field( wp_unslash( (string) $code ) ),
			sanitize_text_field( wp_unslash( (string) $state ) )
		);

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					'seo_agent_ai_oauth_error',
					rawurlencode( $result->get_error_message() ),
					admin_url( 'admin.php?page=seo-agent-ai-connect' )
				)
			);
		} else {
			wp_safe_redirect(
				add_query_arg(
					'seo_agent_ai_notice',
					'google_connected',
					admin_url( 'admin.php?page=seo-agent-ai-connect' )
				)
			);
		}
		exit;
	}

	// -------------------------------------------------------------------
	// AJAX: list Google Search Console properties
	// -------------------------------------------------------------------

	public function ajax_list_gsc_sites() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
			return;
		}
		check_ajax_referer( 'seo_agent_ai_property_list' );

		$sites = $this->gsc_client->list_sites();
		if ( is_wp_error( $sites ) ) {
			wp_send_json_error( $sites->get_error_message() );
			return;
		}
		wp_send_json_success( $sites );
	}

	// -------------------------------------------------------------------
	// AJAX: list Google Analytics 4 properties
	// -------------------------------------------------------------------

	public function ajax_list_ga4_properties() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
			return;
		}
		check_ajax_referer( 'seo_agent_ai_property_list' );

		$properties = $this->ga4_client->list_properties();
		if ( is_wp_error( $properties ) ) {
			wp_send_json_error( $properties->get_error_message() );
			return;
		}
		wp_send_json_success( $properties );
	}

	// -------------------------------------------------------------------
	// Lock helpers
	// -------------------------------------------------------------------

	private function acquire_lock() {
		if ( get_transient( self::ANALYSIS_LOCK_KEY ) ) {
			return false;
		}
		set_transient( self::ANALYSIS_LOCK_KEY, 1, self::ANALYSIS_LOCK_TTL );
		return true;
	}

	private function release_lock() {
		delete_transient( self::ANALYSIS_LOCK_KEY );
	}

	// -------------------------------------------------------------------
	// API failure tracking & persistent admin notice
	// -------------------------------------------------------------------

	private function update_api_failure_tracker( $processed, $failed ) {
		$all_failed = $processed > 0 && $failed === $processed;
		$current    = (int) get_option( self::OPTION_API_FAILURES, 0 );

		if ( $all_failed ) {
			update_option( self::OPTION_API_FAILURES, $current + 1, false );
		} else {
			if ( $current > 0 ) {
				update_option( self::OPTION_API_FAILURES, 0, false );
			}
			delete_option( self::OPTION_LAST_API_ERROR );
		}
	}

	public function maybe_render_api_failure_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$count = (int) get_option( self::OPTION_API_FAILURES, 0 );
		if ( $count < self::API_FAILURE_NOTICE_AFTER ) {
			return;
		}

		$msg = (string) get_option( self::OPTION_LAST_API_ERROR, '' );

		echo '<div class="notice notice-error"><p>';
		echo '<strong>' . esc_html__( 'SEO Agent AI:', 'seo-agent-ai' ) . '</strong> ';
		printf(
			/* translators: %d: number of consecutive failed analysis runs. */
			esc_html__( 'Search Console / Analytics calls failed on the last %d analysis runs.', 'seo-agent-ai' ),
			(int) $count
		);
		echo ' ';
		printf(
			/* translators: 1: opening anchor for Connect page, 2: closing anchor. */
			esc_html__( 'Reconnect your Google account on the %1$sConnect page%2$s, or check Settings for property selection.', 'seo-agent-ai' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=seo-agent-ai-connect' ) ) . '">',
			'</a>'
		);
		if ( $msg !== '' ) {
			echo '<br><em>' . esc_html__( 'Last error:', 'seo-agent-ai' ) . '</em> ' . esc_html( $msg );
		}
		echo '</p></div>';
	}

	// -------------------------------------------------------------------
	// Public analysis entry point for WP-CLI
	// -------------------------------------------------------------------

	public function analyze_post_for_cli( WP_Post $post, $autopilot = false, $dry_run = false ) {
		$url = get_permalink( $post );
		if ( ! $url ) {
			return array(
				'had_recommendations' => false,
				'had_api_failure'     => false,
				'signals'             => array(),
			);
		}

		$gsc_metrics = $this->gsc_client->get_page_metrics( $url );
		$ga4_metrics = $this->ga4_client->get_page_metrics( $url );
		$seo_audit   = $this->bridge->audit_post( (int) $post->ID, $post );

		$had_api_failure = is_wp_error( $gsc_metrics ) || is_wp_error( $ga4_metrics );
		$gsc_safe        = is_wp_error( $gsc_metrics ) ? array() : $gsc_metrics;
		$ga4_safe        = is_wp_error( $ga4_metrics ) ? array() : $ga4_metrics;

		$analysis        = $this->analyzer->analyze( $post, $gsc_safe, $ga4_safe, $seo_audit );
		$autopilot_conf  = (float) get_option( 'seo_agent_ai_autopilot_min_confidence', 0.7 );
		$recommendations = $this->recommendation_engine->generate(
			$post,
			$analysis,
			$gsc_safe,
			$ga4_safe,
			$seo_audit,
			$autopilot_conf,
			$dry_run
		);

		if ( ! $dry_run ) {
			$this->data_store->save_post_metrics(
				(int) $post->ID,
				array(
					'gsc'        => $gsc_safe,
					'ga4'        => $ga4_safe,
					'analysis'   => $analysis,
					'updated_at' => current_time( 'mysql' ),
				)
			);
			$this->data_store->save_recommendations( (int) $post->ID, $recommendations );
			update_post_meta( (int) $post->ID, '_seo_agent_ai_last_analyzed', current_time( 'mysql' ) );

			if ( ! empty( $recommendations ) ) {
				$this->route_recommendations( (int) $post->ID, $recommendations, $gsc_safe, $analysis, $autopilot );
			}
		}

		return array(
			'had_recommendations' => ! empty( $recommendations ),
			'had_api_failure'     => $had_api_failure,
			'signals'             => isset( $analysis['signals'] ) ? $analysis['signals'] : array(),
			'recommendations'     => $recommendations,
			'title'               => $post->post_title,
		);
	}

	// -------------------------------------------------------------------
	// Cron: orphan detection
	// -------------------------------------------------------------------

	/**
	 * Detect orphan pages — published posts with no inbound internal links.
	 * Processes up to 100 posts per run to avoid slow-query timeouts.
	 */
	public function run_detect_orphans() {
		global $wpdb;

		$this->logger->info( 'Starting orphan page detection.' );

		$posts   = $this->get_posts_for_analysis( 100 );
		$orphans = 0;
		$checked = 0;

		foreach ( $posts as $post ) {
			$permalink = get_permalink( $post );
			if ( ! $permalink ) {
				continue;
			}
			$permalink_path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
			if ( ! $permalink_path ) {
				continue;
			}

			// Count other published posts whose content contains a link to this post.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$link_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_status = 'publish'
				   AND ID != %d
				   AND post_content LIKE %s",
					$post->ID,
					'%' . $wpdb->esc_like( $permalink_path ) . '%'
				)
			);

			++$checked;

			if ( $link_count === 0 ) {
				++$orphans;
				$rec = array(
					'type'       => 'internal_linking',
					'field'      => 'content',
					'confidence' => 0.65,
					'reasoning'  => __( 'This post has no inbound internal links (orphan page). Adding internal links from related posts will help search engines discover and rank it.', 'seo-agent-ai' ),
					'risk_level' => 'safe',
				);

				$this->decision_engine->process( (int) $post->ID, $rec, 0.65, false );
			}
		}

		$this->logger->info( sprintf( 'Orphan detection complete. Checked: %d, Orphans found: %d', $checked, $orphans ) );
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_ORPHAN, current_time( 'mysql' ), false );
	}

	// -------------------------------------------------------------------
	// Cron: image alt text generation (daily, autopilot only)
	// -------------------------------------------------------------------

	/**
	 * Daily cron: bulk-generate missing image alt text.
	 * Only runs when autopilot is enabled so manual-review sites are unaffected.
	 * Processes up to 20 images per run to stay within execution time limits.
	 */
	public function run_generate_image_alts() {
		$autopilot = (bool) get_option( 'seo_agent_ai_autopilot_enabled', false );
		if ( ! $autopilot ) {
			return;
		}

		$this->logger->info( 'Starting daily image alt text generation pass.' );
		$result = $this->image_seo->bulk_generate_alt_text( 20 );
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_IMAGE_ALTS, current_time( 'mysql' ), false );
		$this->logger->info(
			sprintf(
				'Image alt generation complete. processed=%d success=%d failed=%d',
				(int) $result['processed'],
				(int) $result['success'],
				(int) $result['failed']
			)
		);
	}

	// -------------------------------------------------------------------
	// Cron: health check
	// -------------------------------------------------------------------

	/**
	 * Fire every ~12 hours. Send an alert email if the daily analysis has not
	 * run in the last 26 hours (i.e. a whole day has been missed).
	 */
	public function run_health_check() {
		$last_raw = get_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_DAILY, '' );
		if ( $last_raw === '' ) {
			return;
		}

		$last_ts = strtotime( $last_raw );
		$overdue = ( time() - $last_ts ) > ( 26 * HOUR_IN_SECONDS );
		if ( ! $overdue ) {
			return;
		}

		$to = (string) get_option( 'seo_agent_ai_email_address', '' );
		if ( $to === '' ) {
			$to = (string) get_option( 'admin_email', '' );
		}
		if ( $to === '' ) {
			return;
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: %s: site name. */
			__( '[%s] SEO Agent AI — Daily Analysis Missed', 'seo-agent-ai' ),
			$site_name
		);
		$message = sprintf(
			/* translators: 1: site name, 2: last run time. */
			__( "The daily SEO analysis on %1\$s has not run since %2\$s. This usually means WP-Cron is not firing.\n\nPlease check your hosting cron configuration or visit the Cron Status page in your WordPress admin.\n\n%3\$s", 'seo-agent-ai' ),
			$site_name,
			$last_raw,
			admin_url( 'admin.php?page=seo-agent-cron-status' )
		);

		wp_mail( sanitize_email( $to ), $subject, $message );
		$this->logger->warning( 'Health check: daily analysis overdue, alert email sent.' );
	}

	// -------------------------------------------------------------------
	// Cron: auto-resolve 404s
	// -------------------------------------------------------------------

	/**
	 * Weekly cron: automatically create 301 redirects for 404 URLs that have
	 * been hit 3+ times and can be matched to a live post by slug similarity.
	 */
	public function run_auto_redirect_404s() {
		$this->logger->info( 'Starting auto-redirect 404 resolution.' );
		$created = $this->redirect_manager->auto_resolve_404s();
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_AUTO_REDIRECT, current_time( 'mysql' ), false );
		$this->logger->info( sprintf( 'Auto-redirect 404 resolution complete. Redirects created: %d.', $created ) );
	}

	// -------------------------------------------------------------------
	// Cron: weekly ranking email
	// -------------------------------------------------------------------

	/**
	 * Weekly cron: send a rankings trend email digest when email reports are enabled.
	 */
	public function run_weekly_ranking_email() {
		if ( ! (bool) get_option( 'seo_agent_ai_email_reports', false ) ) {
			return;
		}
		$this->logger->info( 'Sending weekly ranking summary email.' );
		$this->report_engine->send_weekly_ranking_email();
		update_option( 'seo_agent_ai_last_run_' . self::CRON_HOOK_WEEKLY_EMAIL, current_time( 'mysql' ), false );
	}

	// -------------------------------------------------------------------
	// Accessor methods for WP-CLI and admin pages
	// -------------------------------------------------------------------

	public function get_gsc_client() {
		return $this->gsc_client; }
	public function get_ga4_client() {
		return $this->ga4_client; }
	public function get_analyzer() {
		return $this->analyzer; }
	public function get_recommendation_engine() {
		return $this->recommendation_engine; }
	public function get_fix_executor() {
		return $this->fix_executor; }
	public function get_scoring_engine() {
		return $this->scoring_engine; }
	public function get_decision_engine() {
		return $this->decision_engine; }
	public function get_report_engine() {
		return $this->report_engine; }
	public function get_queue_manager() {
		return $this->queue_manager; }
	public function get_logger() {
		return $this->logger; }
	public function get_oauth() {
		return $this->oauth; }
	public function get_gemini() {
		return $this->gemini; }
	public function get_openai() {
		return $this->openai; }
	public function get_data_store() {
		return $this->data_store; }
	public function get_activity_log() {
		return $this->activity_log; }
	public function get_bridge() {
		return $this->bridge; }
	public function get_gsc_opportunity_analyzer() {
		return $this->gsc_opportunity_analyzer; }
	public function get_image_seo() {
		return $this->image_seo; }
}
