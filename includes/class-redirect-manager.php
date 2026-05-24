<?php
/**
 * Redirect Manager — 301/302 redirects and 404 logging.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Redirect_Manager {

	const TABLE_REDIRECTS  = 'seo_agent_redirects';
	const TABLE_404_LOG    = 'seo_agent_404_log';
	const TABLE_SMARTCRAWL = 'smartcrawl_redirects';

	const REDIRECT_CACHE_KEY = 'seo_agent_ai_redirect_list';
	const REDIRECT_CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/** @var bool|null  Cached SmartCrawl availability. */
	private $sc_active = null;

	// -------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------

	public function init_hooks() {
		// Only run our own redirect engine when SmartCrawl isn't handling it.
		if ( ! $this->smartcrawl_active() ) {
			add_action( 'template_redirect', array( $this, 'process_redirects' ), 1 );
		}
		add_action( 'wp', array( $this, 'init_404_logging' ) );
	}

	// -------------------------------------------------------------------
	// SmartCrawl adapter
	// -------------------------------------------------------------------

	/**
	 * Returns true when SmartCrawl's redirect table exists.
	 *
	 * @return bool
	 */
	private function smartcrawl_active() {
		if ( $this->sc_active !== null ) {
			return $this->sc_active;
		}
		global $wpdb;
		$table           = $wpdb->prefix . self::TABLE_SMARTCRAWL;
		$this->sc_active = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $this->sc_active;
	}

	/**
	 * Insert a redirect into SmartCrawl's table.
	 *
	 * @param string $source Full URL of the source.
	 * @param string $target Target URL.
	 * @param int    $type   HTTP code (301|302).
	 * @return int|false Inserted row ID or false.
	 */
	private function sc_add_redirect( $source, $target, $type ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SMARTCRAWL;

		// Avoid duplicates.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE source = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source
			)
		);
		if ( $existing_id ) {
			return (int) $existing_id;
		}

		// Derive path: relative URL portion with no leading slash.
		$path = ltrim( (string) wp_parse_url( $source, PHP_URL_PATH ), '/' );

		// SmartCrawl json_decode-fallbacks a plain URL correctly.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'title'       => substr( 'SEO Agent: /' . $path, 0, 200 ),
				'source'      => substr( $source, 0, 500 ),
				'path'        => substr( $path, 0, 500 ),
				'destination' => substr( $target, 0, 200 ),
				'type'        => $type,
				'options'     => '',
				'rules'       => 'null',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Read redirects from SmartCrawl's table, normalised to our display format.
	 *
	 * @param int $limit  Rows to return.
	 * @param int $offset Offset.
	 * @return array[]
	 */
	private function sc_get_redirects( $limit, $offset ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SMARTCRAWL;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, source, destination, type FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $limit,
				(int) $offset
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			// SmartCrawl stores destination as JSON-encoded string or plain URL.
			$target = json_decode( $row['destination'], true );
			if ( ! is_string( $target ) ) {
				// Array means post-ID reference; resolve it.
				if ( is_array( $target ) && ! empty( $target['id'] ) ) {
					$permalink = get_permalink( (int) $target['id'] );
					$target    = $permalink ? $permalink : '';
				} else {
					$target = (string) $row['destination'];
				}
			}
			$out[] = array(
				'id'            => $row['id'],
				'source_url'    => $row['source'],
				'target_url'    => $target,
				'redirect_type' => $row['type'],
				'hit_count'     => null,
				'last_hit'      => null,
				'via'           => 'smartcrawl',
			);
		}
		return $out;
	}

	/**
	 * Count all redirects in SmartCrawl's table.
	 *
	 * @return int
	 */
	private function sc_count_redirects() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_SMARTCRAWL;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Delete a redirect from SmartCrawl's table.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	private function sc_delete_redirect( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete(
			$wpdb->prefix . self::TABLE_SMARTCRAWL,
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	// -------------------------------------------------------------------
	// Table creation
	// -------------------------------------------------------------------

	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$cc = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}" . self::TABLE_REDIRECTS . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_url varchar(500) NOT NULL DEFAULT '',
			target_url varchar(500) NOT NULL DEFAULT '',
			redirect_type smallint(5) unsigned NOT NULL DEFAULT 301,
			hit_count int(10) unsigned NOT NULL DEFAULT 0,
			last_hit datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			notes varchar(500) DEFAULT '',
			PRIMARY KEY  (id),
			KEY source_url (source_url(191))
		) $cc;"
		);

		dbDelta(
			"CREATE TABLE {$wpdb->prefix}" . self::TABLE_404_LOG . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url varchar(500) NOT NULL DEFAULT '',
			referrer varchar(500) DEFAULT NULL,
			hit_count int(10) unsigned NOT NULL DEFAULT 1,
			first_seen datetime NOT NULL,
			last_seen datetime NOT NULL,
			redirect_created tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY url (url(191))
		) $cc;"
		);
	}

	// -------------------------------------------------------------------
	// 404 logging
	// -------------------------------------------------------------------

	/**
	 * Log a 404 URL.
	 *
	 * @param string $url      The requested URL.
	 * @param string $referrer HTTP referrer.
	 */
	public function log_404( $url, $referrer = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_404_LOG;
		$url   = substr( sanitize_text_field( $url ), 0, 500 );
		$ref   = $referrer ? substr( sanitize_text_field( $referrer ), 0, 500 ) : null;
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, hit_count FROM `{$table}` WHERE url = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$url
			)
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array(
					'hit_count' => $existing->hit_count + 1,
					'last_seen' => $now,
					'referrer'  => $ref,
				),
				array( 'id' => $existing->id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'url'        => $url,
					'referrer'   => $ref,
					'hit_count'  => 1,
					'first_seen' => $now,
					'last_seen'  => $now,
				),
				array( '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Get 404 log entries, ordered by hit count.
	 *
	 * @param int $limit  Number of rows.
	 * @param int $offset Offset.
	 * @return array[]
	 */
	public function get_404_log( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_404_LOG;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` ORDER BY hit_count DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $limit,
				(int) $offset
			),
			ARRAY_A
		);
	}

	// -------------------------------------------------------------------
	// Redirects CRUD
	// -------------------------------------------------------------------

	/**
	 * Add a redirect rule — writes to SmartCrawl when available, falls back to own table.
	 *
	 * @param string $source Source URL path or full URL.
	 * @param string $target Target URL.
	 * @param int    $type   HTTP status code (301 or 302).
	 * @param string $notes  Optional notes (own table only).
	 * @return int|false Inserted row ID or false on error.
	 */
	public function add_redirect( $source, $target, $type = 301, $notes = '' ) {
		$source = esc_url_raw( $source );
		$target = esc_url_raw( $target );
		$type   = in_array( (int) $type, array( 301, 302 ), true ) ? (int) $type : 301;

		if ( $this->smartcrawl_active() ) {
			return $this->sc_add_redirect( $source, $target, $type );
		}

		global $wpdb;
		$notes = substr( sanitize_text_field( $notes ), 0, 500 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE_REDIRECTS,
			array(
				'source_url'    => substr( $source, 0, 500 ),
				'target_url'    => substr( $target, 0, 500 ),
				'redirect_type' => $type,
				'hit_count'     => 0,
				'created_at'    => current_time( 'mysql' ),
				'notes'         => $notes,
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( $result ) {
			delete_transient( self::REDIRECT_CACHE_KEY );
			return (int) $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Get redirect rules — reads from SmartCrawl when available.
	 *
	 * @param int $limit  Number of rows.
	 * @param int $offset Offset.
	 * @return array[]
	 */
	public function get_redirects( $limit = 50, $offset = 0 ) {
		if ( $this->smartcrawl_active() ) {
			return $this->sc_get_redirects( (int) $limit, (int) $offset );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_REDIRECTS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $limit,
				(int) $offset
			),
			ARRAY_A
		);
	}

	/**
	 * Delete a redirect rule by ID — deletes from SmartCrawl when available.
	 *
	 * @param int $id Row ID.
	 * @return bool True on success.
	 */
	public function delete_redirect( $id ) {
		if ( $this->smartcrawl_active() ) {
			return $this->sc_delete_redirect( (int) $id );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$wpdb->prefix . self::TABLE_REDIRECTS,
			array( 'id' => (int) $id ),
			array( '%d' )
		);

		if ( $result ) {
			delete_transient( self::REDIRECT_CACHE_KEY );
		}

		return (bool) $result;
	}

	// -------------------------------------------------------------------
	// Runtime redirect processing
	// -------------------------------------------------------------------

	/**
	 * Process redirects on every request. Hooked on template_redirect, priority 1.
	 */
	public function process_redirects() {
		global $wpdb;

		// Load redirect list from cache or DB.
		$redirects = get_transient( self::REDIRECT_CACHE_KEY );

		if ( false === $redirects ) {
			$table = $wpdb->prefix . self::TABLE_REDIRECTS;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$redirects = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				"SELECT id, source_url, target_url, redirect_type FROM `{$table}` ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
			set_transient( self::REDIRECT_CACHE_KEY, $redirects, self::REDIRECT_CACHE_TTL );
		}

		if ( empty( $redirects ) ) {
			return;
		}

		$current_url = home_url( add_query_arg( null, null ) );
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		foreach ( $redirects as $redirect ) {
			$source = $redirect['source_url'];

			// Match against full URL or just the path.
			if ( $current_url === $source || $request_uri === $source || untrailingslashit( $current_url ) === untrailingslashit( $source ) ) {
				// Update hit count asynchronously (best-effort).
				$table = $wpdb->prefix . self::TABLE_REDIRECTS;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE `{$table}` SET hit_count = hit_count + 1, last_hit = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						current_time( 'mysql' ),
						(int) $redirect['id']
					)
				);

				wp_safe_redirect( esc_url_raw( $redirect['target_url'] ), (int) $redirect['redirect_type'] );
				exit;
			}
		}
	}

	// -------------------------------------------------------------------
	// 404 logging hook
	// -------------------------------------------------------------------

	/**
	 * Log 404 requests and attempt an immediate redirect when the threshold is met.
	 * Hooked on `wp` action — fires before `template_redirect`, so wp_safe_redirect() still works.
	 */
	public function init_404_logging() {
		if ( ! is_404() ) {
			return;
		}

		$url      = home_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

		$this->log_404( $url, $referrer );
		$this->maybe_auto_redirect_now( $url );
	}

	/**
	 * Immediately redirect a 404 URL if it has crossed the hit threshold and a
	 * matching post can be found. Called inline on every 404 request so visitors
	 * are redirected as soon as the threshold is met, without waiting for the cron.
	 *
	 * @param string $url Full URL of the 404 request.
	 */
	private function maybe_auto_redirect_now( string $url ): void {
		global $wpdb;

		$log_table = $wpdb->prefix . self::TABLE_404_LOG;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, hit_count, redirect_created FROM `{$log_table}` WHERE url = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$url
			)
		);

		if ( ! $row || (int) $row->hit_count < 3 || (int) $row->redirect_created === 1 ) {
			return;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || $path === '' ) {
			return;
		}

		$slug = sanitize_title( basename( untrailingslashit( $path ) ) );
		if ( $slug === '' ) {
			return;
		}

		$post = $this->find_post_by_slug_similarity( $slug );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$target = get_permalink( $post );
		if ( ! $target ) {
			return;
		}

		$redirect_id = $this->add_redirect( $url, $target, 301, 'Auto-created (real-time) from 404.' );
		if ( $redirect_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$log_table,
				array( 'redirect_created' => 1 ),
				array( 'id' => (int) $row->id ),
				array( '%d' ),
				array( '%d' )
			);

			wp_safe_redirect( esc_url_raw( $target ), 301 );
			exit;
		}
	}

	// -------------------------------------------------------------------
	// Auto-resolve 404s
	// -------------------------------------------------------------------

	/**
	 * Automatically create redirects for 404 URLs that have been hit 3+ times
	 * and can be matched to an existing published post via slug similarity.
	 *
	 * Safe to call from a cron — idempotent via redirect_created flag.
	 *
	 * @return int Number of redirects created.
	 */
	public function auto_resolve_404s() {
		global $wpdb;

		$log_table = $wpdb->prefix . self::TABLE_404_LOG;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, url FROM `{$log_table}` WHERE hit_count >= %d AND redirect_created = 0 ORDER BY hit_count DESC LIMIT 500", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				3
			),
			ARRAY_A
		);

		if ( empty( $candidates ) ) {
			return 0;
		}

		$created = 0;

		foreach ( $candidates as $row ) {
			$source_url = (string) $row['url'];
			$path       = wp_parse_url( $source_url, PHP_URL_PATH );
			if ( ! is_string( $path ) || $path === '' ) {
				continue;
			}

			// Derive a slug guess from the last path segment.
			$slug = sanitize_title( basename( untrailingslashit( $path ) ) );
			if ( $slug === '' ) {
				continue;
			}

			// Search for a published post whose slug closely matches.
			$post = $this->find_post_by_slug_similarity( $slug );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$target = get_permalink( $post );
			if ( ! $target ) {
				continue;
			}

			$redirect_id = $this->add_redirect( $source_url, $target, 301, 'Auto-created from 404 log.' );
			if ( $redirect_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$log_table,
					array( 'redirect_created' => 1 ),
					array( 'id' => (int) $row['id'] ),
					array( '%d' ),
					array( '%d' )
				);
				++$created;
			}
		}

		return $created;
	}

	/**
	 * Find a published post whose slug matches $slug or is the closest Levenshtein match.
	 *
	 * @param string $slug Candidate slug.
	 * @return WP_Post|null
	 */
	private function find_post_by_slug_similarity( $slug ) {
		// Exact match first.
		$exact = get_posts(
			array(
				'name'           => $slug,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);
		if ( ! empty( $exact ) ) {
			return $exact[0];
		}

		// Fuzzy match: find the post with the lowest Levenshtein distance.
		$all_posts = get_posts(
			array(
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);

		$best_post     = null;
		$best_distance = PHP_INT_MAX;
		$max_distance  = (int) max( 3, floor( strlen( $slug ) * 0.3 ) );

		foreach ( $all_posts as $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$distance = levenshtein( $slug, $post->post_name );
			if ( $distance < $best_distance && $distance <= $max_distance ) {
				$best_distance = $distance;
				$best_post     = $post;
			}
		}

		return $best_post;
	}

	// -------------------------------------------------------------------
	// Stats
	// -------------------------------------------------------------------

	/**
	 * Get overall stats.
	 *
	 * @return array{total_redirects: int, total_404s: int, unresolved_404s: int}
	 */
	public function get_stats() {
		global $wpdb;

		$l_table = $wpdb->prefix . self::TABLE_404_LOG;

		$total_redirects = $this->smartcrawl_active()
			? $this->sc_count_redirects()
			: (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $wpdb->prefix . self::TABLE_REDIRECTS . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_404s = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$l_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$unresolved_404s = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$l_table}` WHERE redirect_created = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return compact( 'total_redirects', 'total_404s', 'unresolved_404s' );
	}
}
