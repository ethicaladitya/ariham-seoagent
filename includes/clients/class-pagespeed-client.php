<?php
/**
 * Google PageSpeed Insights API v5 client.
 *
 * Fetches Core Web Vitals and Lighthouse scores for any URL.
 * The API is free for up to 25,000 requests/day without an API key.
 * With a key (stored as ariham_seoagent_pagespeed_api_key) the quota is higher.
 *
 * Metrics returned:
 *   lcp_ms        — Largest Contentful Paint in milliseconds
 *   fid_ms        — First Input Delay in milliseconds (may be absent on newer reports)
 *   cls           — Cumulative Layout Shift score (float)
 *   fcp_ms        — First Contentful Paint in milliseconds
 *   ttfb_ms       — Time to First Byte in milliseconds
 *   performance   — Lighthouse performance score 0-100
 *   accessibility — Lighthouse accessibility score 0-100
 *   seo_score     — Lighthouse SEO score 0-100
 *   strategy      — 'mobile' or 'desktop'
 *
 * Results are cached as transients (24h by default) per URL+strategy.
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ariham_SEOAgent_PageSpeed_Client {

	const API_ENDPOINT    = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
	const OPTION_API_KEY  = 'ariham_seoagent_pagespeed_api_key';
	const CACHE_TTL       = DAY_IN_SECONDS;
	const REQUEST_TIMEOUT = 30;

	/** LCP threshold for a "needs improvement" flag (ms). */
	const LCP_NEEDS_IMPROVEMENT = 2500;
	/** LCP threshold for "poor" (ms). */
	const LCP_POOR              = 4000;
	/** CLS threshold for "needs improvement". */
	const CLS_NEEDS_IMPROVEMENT = 0.1;
	/** CLS threshold for "poor". */
	const CLS_POOR              = 0.25;

	/**
	 * Fetch PageSpeed data for a URL.
	 *
	 * @param string $url      Absolute URL to test.
	 * @param string $strategy 'mobile' or 'desktop' (default 'mobile').
	 * @param bool   $force    Skip cache and fetch fresh data.
	 * @return array|WP_Error Associative metrics array or WP_Error on failure.
	 */
	public function get_metrics( $url, $strategy = 'mobile', $force = false ) {
		$url      = (string) $url;
		$strategy = in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : 'mobile';

		$cache_key = 'ariham_seoagent_psi_' . md5( $url . $strategy );

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$query_args = array(
			'url'      => $url,
			'strategy' => $strategy,
			'category' => array( 'performance', 'seo', 'accessibility' ),
		);

		$api_key = $this->get_api_key();
		if ( $api_key !== '' ) {
			$query_args['key'] = $api_key;
		}

		$api_url = add_query_arg( $query_args, self::API_ENDPOINT );

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => self::REQUEST_TIMEOUT,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'psi_http_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'psi_api_error',
				/* translators: %d: HTTP status code returned by the PageSpeed API. */
				sprintf( __( 'PageSpeed API returned HTTP %d', 'ariham-seoagent' ), $code )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'psi_parse_error', __( 'Could not parse PageSpeed API response.', 'ariham-seoagent' ) );
		}

		$metrics = $this->parse_metrics( $data, $strategy );
		set_transient( $cache_key, $metrics, self::CACHE_TTL );

		return $metrics;
	}

	/**
	 * Quick check — is a URL's LCP within the "good" threshold?
	 *
	 * @param string $url
	 * @return bool|WP_Error true = good, false = needs improvement, WP_Error on API failure.
	 */
	public function is_lcp_good( $url ) {
		$metrics = $this->get_metrics( $url );
		if ( is_wp_error( $metrics ) ) {
			return $metrics;
		}
		return ( $metrics['lcp_ms'] ?? 0 ) < self::LCP_NEEDS_IMPROVEMENT;
	}

	/**
	 * Returns a CWV verdict string for use in recommendations.
	 *
	 * @param array $metrics Result from get_metrics().
	 * @return string 'good' | 'needs_improvement' | 'poor'
	 */
	public static function cwv_verdict( array $metrics ) {
		$lcp = (int) ( $metrics['lcp_ms'] ?? 0 );
		$cls = (float) ( $metrics['cls'] ?? 0 );

		if ( $lcp >= self::LCP_POOR || $cls >= self::CLS_POOR ) {
			return 'poor';
		}
		if ( $lcp >= self::LCP_NEEDS_IMPROVEMENT || $cls >= self::CLS_NEEDS_IMPROVEMENT ) {
			return 'needs_improvement';
		}
		return 'good';
	}

	// -------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------

	/**
	 * Parse raw API response into a normalised metrics array.
	 *
	 * @param array  $data     Decoded JSON from the API.
	 * @param string $strategy 'mobile' or 'desktop'.
	 * @return array
	 */
	private function parse_metrics( array $data, $strategy ) {
		$audits = isset( $data['lighthouseResult']['audits'] ) ? $data['lighthouseResult']['audits'] : array();
		$cats   = isset( $data['lighthouseResult']['categories'] ) ? $data['lighthouseResult']['categories'] : array();
		$field  = isset( $data['loadingExperience']['metrics'] ) ? $data['loadingExperience']['metrics'] : array();

		return array(
			'lcp_ms'        => $this->field_metric( $field, 'LARGEST_CONTENTFUL_PAINT_MS' ),
			'fid_ms'        => $this->field_metric( $field, 'FIRST_INPUT_DELAY_MS' ),
			'cls'           => $this->field_metric_float( $field, 'CUMULATIVE_LAYOUT_SHIFT_SCORE' ),
			'fcp_ms'        => (int) round( isset( $audits['first-contentful-paint']['numericValue'] ) ? (float) $audits['first-contentful-paint']['numericValue'] : 0 ),
			'ttfb_ms'       => (int) round( isset( $audits['server-response-time']['numericValue'] ) ? (float) $audits['server-response-time']['numericValue'] : 0 ),
			'performance'   => (int) round( ( isset( $cats['performance']['score'] ) ? (float) $cats['performance']['score'] : 0 ) * 100 ),
			'accessibility' => (int) round( ( isset( $cats['accessibility']['score'] ) ? (float) $cats['accessibility']['score'] : 0 ) * 100 ),
			'seo_score'     => (int) round( ( isset( $cats['seo']['score'] ) ? (float) $cats['seo']['score'] : 0 ) * 100 ),
			'strategy'      => $strategy,
			'fetched_at'    => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Extract an integer field metric (e.g. LCP ms) from the loadingExperience block.
	 *
	 * @param array  $field Field metrics array from the API.
	 * @param string $key   Metric key, e.g. 'LARGEST_CONTENTFUL_PAINT_MS'.
	 * @return int
	 */
	private function field_metric( array $field, $key ) {
		if ( ! isset( $field[ $key ] ) ) {
			return 0;
		}
		$entry = $field[ $key ];
		if ( isset( $entry['percentile'] ) ) {
			return (int) $entry['percentile'];
		}
		if ( isset( $entry['median'] ) ) {
			return (int) $entry['median'];
		}
		return 0;
	}

	/**
	 * Extract a float field metric (e.g. CLS) from the loadingExperience block.
	 * CLS is stored *100 in the API response so we divide back.
	 *
	 * @param array  $field Field metrics array from the API.
	 * @param string $key   Metric key, e.g. 'CUMULATIVE_LAYOUT_SHIFT_SCORE'.
	 * @return float
	 */
	private function field_metric_float( array $field, $key ) {
		if ( ! isset( $field[ $key ] ) ) {
			return 0.0;
		}
		$entry = $field[ $key ];
		$raw   = isset( $entry['percentile'] ) ? $entry['percentile'] : ( isset( $entry['median'] ) ? $entry['median'] : 0 );
		return round( (float) $raw / 100, 3 );
	}

	/**
	 * Retrieve the configured API key, decrypting if necessary.
	 *
	 * @return string Empty string if no key is configured.
	 */
	private function get_api_key() {
		if ( defined( 'ARIHAM_SEOAGENT_PAGESPEED_API_KEY' ) ) {
			return (string) ARIHAM_SEOAGENT_PAGESPEED_API_KEY;
		}
		$stored = (string) get_option( self::OPTION_API_KEY, '' );
		if ( '' === $stored ) {
			return ''; // Will work without a key (rate-limited to 25k/day).
		}
		// Decrypt if stored encrypted.
		if ( class_exists( 'Ariham_SEOAgent_Crypto' ) ) {
			return (string) Ariham_SEOAgent_Crypto::decrypt( $stored );
		}
		return $stored;
	}
}
