<?php
/**
 * Plugin Name:       Ariham SEOAgent
 * Description:       Autonomous SEO growth engine — continuously analyzes Search Console and GA4 signals, then proposes prioritized SEO recommendations with full audit trail, optional autopilot, and AI-powered content intelligence.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Aditya Shah
 * Author URI:        https://adityashah.blog/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ariham-seoagent
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARIHAM_SEOAGENT_VERSION', '0.1.0' );
define( 'ARIHAM_SEOAGENT_PLUGIN_FILE', __FILE__ );
define( 'ARIHAM_SEOAGENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARIHAM_SEOAGENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Shared helpers.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-crypto.php';

// Core data layer.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-data-store.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-activity-log.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-db-manager.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-logger.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-feature-flags.php';

// Google API clients.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/clients/class-sitekit-bridge.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/clients/class-google-oauth.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/clients/class-gsc-client.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/clients/class-ga4-client.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/clients/class-pagespeed-client.php';

// AI clients.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/clients/class-gemini-client.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/clients/class-openai-client.php';

// SEO plugin integration bridge.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-seo-plugin-bridge.php';

// Core analysis engines.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-content-analyzer.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-keyword-cluster.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-seo-scoring-engine.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-seo-analyzer.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-decision-engine.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-schema-engine.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-recommendation-engine.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-fix-executor.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-gsc-opportunity-analyzer.php';

// Autonomous systems.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-search-intent.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-internal-link-engine.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-report-engine.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-queue-manager.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-content-expander.php';

// Admin pages.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-connect-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-report-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-dashboard-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-opportunities-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-rankings-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-pending-approvals-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-rollback-center-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-cron-status-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-image-seo-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-redirects-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-activity-log-page.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/admin/class-admin-page.php';

// Feature modules.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-image-seo.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-social-meta.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-taxonomy-seo.php';
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-redirect-manager.php';

// Plugin orchestrator.
require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Ariham_SEOAgent_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Ariham_SEOAgent_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Ariham_SEOAgent_Plugin', 'maybe_upgrade' ) );

Ariham_SEOAgent_Plugin::instance();

// WP-CLI command registration.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ARIHAM_SEOAGENT_PLUGIN_DIR . 'includes/class-cli.php';
	WP_CLI::add_command( 'ariham-seoagent', 'Ariham_SEOAgent_CLI' );
}
