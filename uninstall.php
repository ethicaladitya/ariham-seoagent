<?php
/**
 * Ariham SEOAgent — uninstall handler.
 *
 * Triggered by WordPress when the user deletes the plugin from the admin UI.
 * Removes every artifact this plugin ever wrote: custom DB tables, options,
 * transients, post meta keys, scheduled cron events.
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Wrap the uninstall logic in an anonymous function so file-scope variables
 * stay out of the global namespace (Plugin Check flags them otherwise).
 */
( function () {
	global $wpdb;

	// 1. Drop all custom tables (original + v3.0 tables + redirect/404 tables).
	$ariham_seoagent_tables = array(
		$wpdb->prefix . 'ariham_seoagent_activity',
		$wpdb->prefix . 'ariham_seoagent_keyword_history',
		$wpdb->prefix . 'ariham_seoagent_page_insights',
		$wpdb->prefix . 'ariham_seoagent_decisions',
		$wpdb->prefix . 'ariham_seoagent_daily_reports',
		$wpdb->prefix . 'ariham_seoagent_internal_links',
		$wpdb->prefix . 'ariham_seoagent_redirects',
		$wpdb->prefix . 'ariham_seoagent_404_log',
	);
	foreach ( $ariham_seoagent_tables as $ariham_seoagent_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$ariham_seoagent_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	// 2. Delete every option this plugin ever wrote.
	$ariham_seoagent_options = array(
		// Google OAuth.
		'ariham_seoagent_google_client_id',
		'ariham_seoagent_google_client_secret',
		'ariham_seoagent_google_refresh_token',
		'ariham_seoagent_google_access_token',
		'ariham_seoagent_google_access_token_expires_at',
		'ariham_seoagent_google_connected_email',
		'ariham_seoagent_gsc_site_url',
		'ariham_seoagent_ga4_property_id',
		// AI providers.
		'ariham_seoagent_gemini_api_key',
		'ariham_seoagent_openai_api_key',
		'ariham_seoagent_openai_base_url',
		'ariham_seoagent_openai_model',
		'ariham_seoagent_ai_provider',
		// Autopilot / settings.
		'ariham_seoagent_autopilot_enabled',
		'ariham_seoagent_autopilot_max_daily',
		'ariham_seoagent_autopilot_min_confidence',
		'ariham_seoagent_log_retention_days',
		'ariham_seoagent_email_reports',
		'ariham_seoagent_email_address',
		'ariham_seoagent_cwv_enabled',
		// Score improvement target.
		'ariham_seoagent_score_target',
		// Debug / mode.
		'ariham_seoagent_debug_mode',
		'ariham_seoagent_verbose_mode',
		// Runtime state.
		'ariham_seoagent_last_run',
		'ariham_seoagent_activity_db_v',
		'ariham_seoagent_db_version',
		'ariham_seoagent_consecutive_api_failures',
		'ariham_seoagent_last_api_error',
		'ariham_seoagent_auth_health',
		'ariham_seoagent_queue',
		// Analysis rotation.
		'ariham_seoagent_analysis_offset',
		'ariham_seoagent_post_types',
		'ariham_seoagent_db_manager_v',
		// Social meta / webmaster verification.
		'ariham_seoagent_google_verification',
		'ariham_seoagent_bing_verification',
		'ariham_seoagent_yandex_verification',
		'ariham_seoagent_homepage_title',
		'ariham_seoagent_homepage_description',
		'ariham_seoagent_social_meta_enabled',
		'ariham_seoagent_homepage_og_title',
		'ariham_seoagent_homepage_og_description',
		'ariham_seoagent_homepage_og_image',
	);

	foreach ( $ariham_seoagent_options as $ariham_seoagent_option ) {
		delete_option( $ariham_seoagent_option );
		delete_site_option( $ariham_seoagent_option );
	}

	// 3. Delete transients (well-known keys + prefix-keyed daily counters).
	$ariham_seoagent_transients = array(
		'ariham_seoagent_oauth_state',
		'ariham_seoagent_analysis_lock',
		'ariham_seoagent_connection_test_result',
		'ariham_seoagent_batch_state',
		'ariham_seoagent_cron_checked',
		'ariham_seoagent_site_opportunities',
		'ariham_seoagent_redirect_list',
	);
	foreach ( $ariham_seoagent_transients as $ariham_seoagent_transient ) {
		delete_transient( $ariham_seoagent_transient );
		delete_site_transient( $ariham_seoagent_transient );
	}

	// 3b. Sweep all prefix-keyed transients, last-run options, and feature flags.
	// The two _transient_ wildcards cover every value + timeout row this plugin
	// ever set (ap_count, schema, gsc_page, ga4_page, psi, etc.).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_ariham_seoagent_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ariham_seoagent_' ) . '%',
			$wpdb->esc_like( 'ariham_seoagent_last_run_' ) . '%',
			$wpdb->esc_like( 'ariham_seoagent_flag_' ) . '%'
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// 4. Delete every post-meta key this plugin ever wrote.
	$ariham_seoagent_meta_keys = array(
		'_ariham_seoagent_metrics',
		'_ariham_seoagent_recommendations',
		'_ariham_seoagent_backups',
		'_ariham_seoagent_last_applied_at',
		'_ariham_seoagent_meta_title',
		'_ariham_seoagent_meta_description',
		'_ariham_seoagent_last_analyzed',
		'_ariham_seoagent_score',
		// Metabox fields.
		'_ariham_seoagent_focus_keyword',
		'_ariham_seoagent_custom_title',
		'_ariham_seoagent_custom_description',
		'_ariham_seoagent_canonical',
		'_ariham_seoagent_robots_noindex',
		'_ariham_seoagent_robots_nofollow',
		'_ariham_seoagent_robots_noarchive',
		'_ariham_seoagent_robots_nosnippet',
		'_ariham_seoagent_og_title',
		'_ariham_seoagent_og_description',
		'_ariham_seoagent_og_image_id',
		// Image SEO.
		'_ariham_seoagent_alt_generated',
	);

	foreach ( $ariham_seoagent_meta_keys as $ariham_seoagent_meta_key ) {
		delete_post_meta_by_key( $ariham_seoagent_meta_key );
	}

	// 4b. Wildcard term meta cleanup.
	$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_ariham_seoagent_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

	// 5. Unschedule all cron events.
	$ariham_seoagent_cron_hooks = array(
		'ariham_seoagent_daily_analysis',
		'ariham_seoagent_run_manual_analysis',
		'ariham_seoagent_fetch_gsc_data',
		'ariham_seoagent_fetch_ga4_data',
		'ariham_seoagent_generate_report',
		'ariham_seoagent_score_pages',
		'ariham_seoagent_detect_decay',
		'ariham_seoagent_run_internal_links',
		'ariham_seoagent_purge_old_data',
		'ariham_seoagent_detect_cannibalization',
		'ariham_seoagent_score_and_improve',
		'ariham_seoagent_detect_orphans',
		'ariham_seoagent_generate_image_alts',
		'ariham_seoagent_observe_results',
		'ariham_seoagent_fetch_cwv_data',
		'ariham_seoagent_health_check',
		'ariham_seoagent_auto_redirect_404s',
		'ariham_seoagent_weekly_ranking_email',
	);
	foreach ( $ariham_seoagent_cron_hooks as $ariham_seoagent_hook ) {
		wp_clear_scheduled_hook( $ariham_seoagent_hook );
	}

	// 6. Remove the log directory written to uploads/ariham-seoagent/ via WP_Filesystem.
	$ariham_seoagent_uploads = wp_upload_dir( null, false );
	if ( is_array( $ariham_seoagent_uploads ) && ! empty( $ariham_seoagent_uploads['basedir'] ) ) {
		$ariham_seoagent_log_dir = trailingslashit( $ariham_seoagent_uploads['basedir'] ) . 'ariham-seoagent';

		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		if ( WP_Filesystem() && $wp_filesystem->is_dir( $ariham_seoagent_log_dir ) ) {
			$wp_filesystem->delete( $ariham_seoagent_log_dir, true );
		}
	}
} )();
