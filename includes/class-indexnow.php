<?php
/**
 * IndexNow — instant URL submission to Google, Bing, and Yandex.
 *
 * IndexNow lets search engines know a URL has been updated so they can
 * crawl and index it within minutes rather than waiting for the next
 * scheduled crawl. The plugin submits a URL whenever:
 *   - A fix is auto-applied via the Fix Executor.
 *   - A post's meta title / description is updated.
 *   - A pending approval is approved and applied.
 *
 * The API key is stored in wp_options and a corresponding key file is
 * written to the web root on first use. Both Google and Bing honour
 * the same IndexNow protocol and endpoint format.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_IndexNow {

	const OPTION_KEY      = 'seo_agent_ai_indexnow_key';
	const OPTION_ENABLED  = 'seo_agent_ai_indexnow_enabled';
	const TRANSIENT_BATCH = 'seo_agent_ai_indexnow_batch';
	const BATCH_LIMIT     = 100; // Max URLs per IndexNow batch request.

	/** @var SEO_Agent_AI_Logger */
	private $logger;

	public function __construct( SEO_Agent_AI_Logger $logger ) {
		$this->logger = $logger;
	}

	// -------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------

	/**
	 * Submit a single URL to IndexNow endpoints.
	 * Queues the URL in a short-lived transient batch and flushes it
	 * immediately (or on shutdown to batch multiple calls in one request).
	 *
	 * @param string $url Absolute URL to submit.
	 * @return bool  True if queued successfully.
	 */
	public function ping( $url ) {
		if ( ! (bool) get_option( self::OPTION_ENABLED, true ) ) {
			return false;
		}
		$url = esc_url_raw( (string) $url );
		if ( ! $url ) {
			return false;
		}

		$batch   = (array) get_transient( self::TRANSIENT_BATCH );
		$batch[] = $url;
		$batch   = array_unique( $batch );

		if ( count( $batch ) >= self::BATCH_LIMIT ) {
			$this->flush_batch( $batch );
			delete_transient( self::TRANSIENT_BATCH );
		} else {
			set_transient( self::TRANSIENT_BATCH, $batch, 60 );
			// Register a shutdown flush so we don't leave URLs un-submitted.
			add_action( 'shutdown', array( $this, 'flush_pending' ) );
		}

		return true;
	}

	/**
	 * Flush any batched URLs that have not yet been submitted.
	 * Called on `shutdown` or by the daily cron cleanup.
	 */
	public function flush_pending() {
		$batch = (array) get_transient( self::TRANSIENT_BATCH );
		if ( ! empty( $batch ) ) {
			$this->flush_batch( $batch );
			delete_transient( self::TRANSIENT_BATCH );
		}
	}

	/**
	 * Get (or generate) the IndexNow API key.
	 *
	 * @return string
	 */
	public function get_key() {
		$key = (string) get_option( self::OPTION_KEY, '' );
		if ( $key === '' ) {
			// Use PHP-native randomness — wp_generate_password() is not
			// available this early in the load order (before pluggable.php).
			$key = bin2hex( random_bytes( 16 ) );
			update_option( self::OPTION_KEY, $key, false );
		}
		return $key;
	}

	/**
	 * Write the key verification file to the document root.
	 * Required once so search engines can verify ownership.
	 *
	 * @return bool
	 */
	public function write_key_file() {
		$key      = $this->get_key();
		$doc_root = rtrim( (string) ABSPATH, '/' );
		$file     = $doc_root . '/' . $key . '.txt';

		if ( file_exists( $file ) ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $file, $key );
		if ( false === $result ) {
			$this->logger->warning( 'IndexNow: could not write key file to ' . $file );
			return false;
		}
		return true;
	}

	// -------------------------------------------------------------------
	// Internal: batch flush
	// -------------------------------------------------------------------

	/**
	 * POST a batch of URLs to all IndexNow endpoints.
	 *
	 * @param string[] $urls
	 */
	private function flush_batch( array $urls ) {
		$urls = array_values( array_unique( array_filter( $urls ) ) );
		if ( empty( $urls ) ) {
			return;
		}

		$key      = $this->get_key();
		$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$key_url  = home_url( '/' . $key . '.txt' );

		$payload = wp_json_encode(
			array(
				'host'        => $host,
				'key'         => $key,
				'keyLocation' => $key_url,
				'urlList'     => array_slice( $urls, 0, self::BATCH_LIMIT ),
			)
		);

		$endpoints = (array) apply_filters(
			'seo_agent_ai_indexnow_endpoints',
			array(
				'https://api.indexnow.org/indexnow',
				'https://www.bing.com/indexnow',
			)
		);

		foreach ( $endpoints as $endpoint ) {
			$response = wp_remote_post(
				$endpoint,
				array(
					'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
					'body'        => $payload,
					'timeout'     => 10,
					'redirection' => 3,
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->logger->warning( 'IndexNow: ' . $endpoint . ' error — ' . $response->get_error_message() );
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code === 200 || $code === 202 ) {
				$this->logger->info(
					sprintf(
						'IndexNow: submitted %d URL(s) to %s (HTTP %d).',
						count( $urls ),
						$endpoint,
						$code
					)
				);
			} else {
				$this->logger->warning(
					sprintf(
						'IndexNow: unexpected HTTP %d from %s.',
						$code,
						$endpoint
					)
				);
			}
		}
	}
}
