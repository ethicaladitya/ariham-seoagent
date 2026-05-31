<?php
/**
 * Internal Link Engine.
 *
 * Detects orphan pages, finds contextual anchor opportunities in other posts,
 * and inserts links safely (max 3 per post per run, no forced insertions).
 *
 * All inserted links are stored in the ariham_seoagent_internal_links table for
 * full reversibility. Never modifies posts with status != 'publish'.
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ariham_SEOAgent_Internal_Link_Engine {

	/** Maximum links to add to a single source post per run. */
	const MAX_LINKS_PER_POST = 3;

	/** Minimum word length for an anchor phrase match. */
	const MIN_ANCHOR_WORDS = 2;

	/** @var Ariham_SEOAgent_Logger */
	private $logger;

	public function __construct( Ariham_SEOAgent_Logger $logger ) {
		$this->logger = $logger;
	}

	// -------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------

	/**
	 * Detect all orphan posts (published posts with zero inbound internal links).
	 *
	 * @param int $limit Max orphan posts to return.
	 * @return WP_Post[]
	 */
	public function get_orphan_posts( $limit = 50 ) {
		$all_posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bulk orphan scan needs all published posts.
				'fields'         => 'ids',
			)
		);

		$orphans = array();
		foreach ( $all_posts as $post_id ) {
			$inbound = Ariham_SEOAgent_DB_Manager::get_post_links( $post_id, 'target' );
			if ( empty( $inbound ) ) {
				$post = get_post( $post_id );
				if ( $post instanceof WP_Post ) {
					$orphans[] = $post;
					if ( count( $orphans ) >= (int) $limit ) {
						break;
					}
				}
			}
		}

		return $orphans;
	}

	/**
	 * Find link insertion opportunities across the entire site for a target post.
	 *
	 * Scans all published posts for natural occurrences of the target post's
	 * keywords in their content, and returns candidate source posts with context.
	 *
	 * @param WP_Post $target_post  The post we want to build links to.
	 * @param array   $gsc_queries  GSC query data for the target post (for anchor terms).
	 * @param int     $limit        Max candidate sources to return.
	 * @return array  Array of candidate records: [source_post, anchor, context_snippet].
	 */
	public function find_link_opportunities( WP_Post $target_post, array $gsc_queries = array(), $limit = 10 ) {
		$target_url = get_permalink( $target_post );

		// Build a list of anchor phrases to look for: top queries + post title words.
		$anchors = $this->build_anchor_phrases( $target_post, $gsc_queries );
		if ( empty( $anchors ) ) {
			return array();
		}

		// Get all published posts except the target itself.
		$source_ids = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- scanning candidate sources for internal link opportunities.
				'fields'         => 'ids',
				'exclude'        => array( $target_post->ID ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			)
		);

		$candidates = array();

		foreach ( $source_ids as $source_id ) {
			// Skip if there is already a link from this source to the target.
			if ( $this->link_already_exists( $source_id, $target_post->ID ) ) {
				continue;
			}

			// Skip if source already has MAX_LINKS_PER_POST plugin-added links.
			$existing_plugin_links = Ariham_SEOAgent_DB_Manager::get_post_links( $source_id, 'source' );
			if ( count( $existing_plugin_links ) >= self::MAX_LINKS_PER_POST ) {
				continue;
			}

			$source_post = get_post( $source_id );
			if ( ! $source_post instanceof WP_Post ) {
				continue;
			}

			$result = $this->find_anchor_in_content( $source_post->post_content, $anchors, $target_url );
			if ( $result !== null ) {
				$candidates[] = array(
					'source_post'     => $source_post,
					'anchor'          => $result['anchor'],
					'context_snippet' => $result['snippet'],
					'target_post'     => $target_post,
					'target_url'      => $target_url,
				);

				if ( count( $candidates ) >= (int) $limit ) {
					break;
				}
			}
		}

		return $candidates;
	}

	/**
	 * Insert a link into a source post's content and record it in the DB.
	 *
	 * @param WP_Post $source_post
	 * @param WP_Post $target_post
	 * @param string  $anchor        Anchor text to wrap with the link.
	 * @param string  $context_snippet Short excerpt showing the insertion point.
	 * @param bool    $dry_run        If true, validate but do not write.
	 * @return true|WP_Error
	 */
	public function insert_link( WP_Post $source_post, WP_Post $target_post, $anchor, $context_snippet, $dry_run = false ) {
		if ( $source_post->post_status !== 'publish' ) {
			return new WP_Error( 'ariham_seoagent_il_not_published', __( 'Source post is not published.', 'ariham-seoagent' ) );
		}

		// Safety: never link to the same post.
		if ( $source_post->ID === $target_post->ID ) {
			return new WP_Error( 'ariham_seoagent_il_self_link', __( 'Cannot link a post to itself.', 'ariham-seoagent' ) );
		}

		// Safety: check per-post plugin link cap.
		$existing = Ariham_SEOAgent_DB_Manager::get_post_links( $source_post->ID, 'source' );
		if ( count( $existing ) >= self::MAX_LINKS_PER_POST ) {
			return new WP_Error( 'ariham_seoagent_il_cap_reached', __( 'Maximum plugin links already added to this post.', 'ariham-seoagent' ) );
		}

		// Safety: ensure the link does not already exist.
		if ( $this->link_already_exists( $source_post->ID, $target_post->ID ) ) {
			return new WP_Error( 'ariham_seoagent_il_duplicate', __( 'A link from this source to this target already exists.', 'ariham-seoagent' ) );
		}

		$target_url  = get_permalink( $target_post );
		$anchor_safe = esc_html( $anchor );

		// Find and replace the first natural occurrence of the anchor text in content.
		$new_content = $this->inject_link( $source_post->post_content, $anchor, $target_url );
		if ( $new_content === null ) {
			return new WP_Error( 'ariham_seoagent_il_anchor_not_found', __( 'Anchor text not found naturally in post content.', 'ariham-seoagent' ) );
		}

		if ( $dry_run ) {
			$this->logger->debug( sprintf( '[DRY-RUN] Would insert link "%s" → %s in post %d', $anchor, $target_url, $source_post->ID ) );
			return true;
		}

		// Write the updated content.
		wp_update_post(
			array(
				'ID'           => $source_post->ID,
				'post_content' => $new_content,
			)
		);

		// Record in DB.
		Ariham_SEOAgent_DB_Manager::insert_internal_link(
			$source_post->ID,
			$target_post->ID,
			$anchor,
			$context_snippet,
			'plugin'
		);

		$this->logger->info( sprintf( 'Inserted internal link "%s" from post %d to post %d', $anchor, $source_post->ID, $target_post->ID ) );

		return true;
	}

	/**
	 * Run a full pass: find orphan pages, score link candidates, insert up to cap.
	 *
	 * @param int   $max_orphans  How many orphan posts to process per run.
	 * @param bool  $dry_run
	 * @return array  Summary: [processed, inserted, skipped, errors].
	 */
	public function run_pass( $max_orphans = 5, $dry_run = false ) {
		$orphans   = $this->get_orphan_posts( $max_orphans );
		$processed = 0;
		$inserted  = 0;
		$skipped   = 0;
		$errors    = 0;

		foreach ( $orphans as $target ) {
			++$processed;

			// Fetch GSC queries for this post from the keyword_history table (latest snapshot).
			$gsc_rows = Ariham_SEOAgent_DB_Manager::get_keyword_trend( $target->ID, 90 );
			$queries  = array_map(
				fn( $r ) => array(
					'query'       => $r['keyword'],
					'impressions' => $r['impressions'],
				),
				$gsc_rows
			);

			$candidates = $this->find_link_opportunities( $target, $queries, self::MAX_LINKS_PER_POST );

			if ( empty( $candidates ) ) {
				++$skipped;
				continue;
			}

			// Insert up to MAX_LINKS_PER_POST for this target.
			$count = 0;
			foreach ( $candidates as $candidate ) {
				if ( $count >= self::MAX_LINKS_PER_POST ) {
					break;
				}

				$result = $this->insert_link(
					$candidate['source_post'],
					$candidate['target_post'],
					$candidate['anchor'],
					$candidate['context_snippet'],
					$dry_run
				);

				if ( is_wp_error( $result ) ) {
					++$errors;
					$this->logger->warning( 'Internal link insertion failed: ' . $result->get_error_message() );
				} else {
					++$inserted;
					++$count;
				}
			}
		}

		return array(
			'processed' => $processed,
			'inserted'  => $inserted,
			'skipped'   => $skipped,
			'errors'    => $errors,
		);
	}

	/**
	 * Run link insertion for one specific target post (used when admin approves a decision).
	 *
	 * Finds up to MAX_LINKS_PER_POST source posts that can naturally link to $target_post_id,
	 * then inserts those links into the source post content.
	 *
	 * @param int  $target_post_id The post that needs inbound internal links.
	 * @param bool $dry_run
	 * @return array Summary: [inserted, skipped, errors]
	 */
	public function run_for_post( $target_post_id, $dry_run = false ) {
		$target = get_post( $target_post_id );
		if ( ! $target instanceof WP_Post || $target->post_status !== 'publish' ) {
			return array(
				'inserted' => 0,
				'skipped'  => 0,
				'errors'   => 1,
				'message'  => 'Post not found or not published.',
			);
		}

		$gsc_rows = Ariham_SEOAgent_DB_Manager::get_keyword_trend( $target_post_id, 90 );
		$queries  = array_map(
			fn( $r ) => array(
				'query'       => $r['keyword'],
				'impressions' => (int) ( $r['impressions'] ?? 0 ),
			),
			$gsc_rows
		);

		$candidates = $this->find_link_opportunities( $target, $queries, self::MAX_LINKS_PER_POST );

		$inserted = 0;
		$skipped  = 0;
		$errors   = 0;

		foreach ( $candidates as $candidate ) {
			if ( $inserted >= self::MAX_LINKS_PER_POST ) {
				break;
			}

			$result = $this->insert_link(
				$candidate['source_post'],
				$candidate['target_post'],
				$candidate['anchor'],
				$candidate['context_snippet'],
				$dry_run
			);

			if ( is_wp_error( $result ) ) {
				++$errors;
				$this->logger->warning( 'run_for_post link error: ' . $result->get_error_message() );
			} else {
				++$inserted;
			}
		}

		if ( empty( $candidates ) ) {
			$skipped = 1;
			$this->logger->info( sprintf( 'run_for_post: no link candidates found for post %d', $target_post_id ) );
		}

		return array(
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	// -------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------

	/**
	 * Build a list of anchor phrases for a target post, prioritized by GSC impressions.
	 */
	private function build_anchor_phrases( WP_Post $post, array $gsc_queries ) {
		$phrases = array();

		// 1. Top GSC queries (by impressions).
		usort( $gsc_queries, fn( $a, $b ) => (int) ( $b['impressions'] ?? 0 ) - (int) ( $a['impressions'] ?? 0 ) );
		foreach ( array_slice( $gsc_queries, 0, 5 ) as $q ) {
			$kw = trim( (string) ( $q['query'] ?? '' ) );
			if ( str_word_count( $kw ) >= self::MIN_ANCHOR_WORDS ) {
				$phrases[] = $kw;
			}
		}

		// 2. Post title n-grams (bigrams + trigrams from title).
		$title_words = preg_split( '/\s+/', strtolower( wp_strip_all_tags( $post->post_title ) ) );
		$title_words = array_values( array_filter( $title_words, fn( $w ) => strlen( $w ) > 3 ) );

		$title_word_count = count( $title_words ) - 1;
		for ( $i = 0; $i < $title_word_count; $i++ ) {
			$phrases[] = $title_words[ $i ] . ' ' . $title_words[ $i + 1 ];
			if ( isset( $title_words[ $i + 2 ] ) ) {
				$phrases[] = $title_words[ $i ] . ' ' . $title_words[ $i + 1 ] . ' ' . $title_words[ $i + 2 ];
			}
		}

		return array_unique( array_filter( $phrases ) );
	}

	/**
	 * Check if an anchor phrase appears naturally in content and return context.
	 *
	 * Returns null if not found or if already linked to the target URL.
	 *
	 * @param string   $content
	 * @param string[] $anchors   Candidate anchor phrases (longest first preferred).
	 * @param string   $target_url
	 * @return array|null  ['anchor' => string, 'snippet' => string] or null.
	 */
	private function find_anchor_in_content( $content, array $anchors, $_target_url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- reserved for future use (e.g. excluding self-referential links).
		// Sort anchors longest-first for greedy matching.
		usort( $anchors, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );

		$stripped = wp_strip_all_tags( $content );

		foreach ( $anchors as $anchor ) {
			// Case-insensitive search.
			$pos = mb_stripos( $stripped, $anchor );
			if ( $pos === false ) {
				continue;
			}

			// Verify this text doesn't already appear inside an <a> tag in the original content.
			if ( $this->is_already_linked( $content, $anchor ) ) {
				continue;
			}

			// Build a context snippet (50 chars before + anchor + 50 chars after).
			$start   = max( 0, $pos - 50 );
			$snippet = '...' . trim( mb_substr( $stripped, $start, strlen( $anchor ) + 100 ) ) . '...';

			return array(
				'anchor'  => $anchor,
				'snippet' => $snippet,
			);
		}

		return null;
	}

	/**
	 * Wrap the first natural occurrence of $anchor in $content with an <a> tag.
	 *
	 * Uses DOMDocument for safe HTML-aware injection. Returns null if anchor is
	 * not found in a text node outside of existing <a> elements.
	 *
	 * @param string $content    Post content HTML.
	 * @param string $anchor     Anchor text to link.
	 * @param string $target_url Destination URL.
	 * @return string|null  Modified content, or null if anchor was not injected.
	 */
	private function inject_link( $content, $anchor, $target_url ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			// Fallback for environments without DOM extension.
			return null;
		}

		$doc     = new DOMDocument( '1.0', 'UTF-8' );
		$wrapped = '<div id="sai-il-root">' . $content . '</div>';
		libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<?xml encoding="UTF-8">' . $wrapped,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$xpath    = new DOMXPath( $doc );
		$injected = false;

		// Walk all text nodes NOT inside <a> tags.
		$text_nodes = $xpath->query( '//text()[not(ancestor::a)]' );
		foreach ( $text_nodes as $text_node ) {
			$node_text = $text_node->nodeValue; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$pos       = mb_stripos( $node_text, $anchor );
			if ( false === $pos ) {
				continue;
			}

			// Split the text node into three parts: before, anchor, after.
			$before = mb_substr( $node_text, 0, $pos );
			$match  = mb_substr( $node_text, $pos, mb_strlen( $anchor ) );
			$after  = mb_substr( $node_text, $pos + mb_strlen( $anchor ) );

			$parent = $text_node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$frag   = $doc->createDocumentFragment();

			if ( '' !== $before ) {
				$frag->appendChild( $doc->createTextNode( $before ) );
			}

			$link = $doc->createElement( 'a' );
			$link->setAttribute( 'href', esc_url( $target_url ) );
			$link->appendChild( $doc->createTextNode( $match ) );
			$frag->appendChild( $link );

			if ( '' !== $after ) {
				$frag->appendChild( $doc->createTextNode( $after ) );
			}

			$parent->replaceChild( $frag, $text_node );
			$injected = true;
			break; // Only inject once.
		}

		if ( ! $injected ) {
			return null;
		}

		// Extract only the inner content of our root div.
		$root = $doc->getElementById( 'sai-il-root' );
		if ( ! $root ) {
			return null;
		}

		$result = '';
		foreach ( $root->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$result .= $doc->saveHTML( $child );
		}

		// Remove any XML declaration DOMDocument may have prepended.
		$result = preg_replace( '/^<\?xml[^?]*\?>\s*/i', '', $result );

		return ( '' !== $result ) ? $result : null;
	}

	/**
	 * Check if the anchor text already appears inside an <a> tag in the content.
	 *
	 * Uses DOMDocument for accurate HTML parsing.
	 *
	 * @param string $content
	 * @param string $anchor
	 * @return bool
	 */
	private function is_already_linked( $content, $anchor ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return false;
		}
		$doc = new DOMDocument( '1.0', 'UTF-8' );
		libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<?xml encoding="UTF-8"><div>' . $content . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		$xpath = new DOMXPath( $doc );
		$links = $xpath->query( '//a' );
		foreach ( $links as $link ) {
			if ( mb_stripos( $link->textContent, $anchor ) !== false ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a plugin-inserted link from $source_id to $target_id already exists.
	 */
	private function link_already_exists( $source_id, $target_id ) {
		$links = Ariham_SEOAgent_DB_Manager::get_post_links( $source_id, 'source' );
		foreach ( $links as $link ) {
			if ( (int) ( $link['target_post_id'] ?? 0 ) === (int) $target_id ) {
				return true;
			}
		}

		// Also check raw content for any existing hyperlink to the target URL.
		$source = get_post( $source_id );
		if ( $source instanceof WP_Post ) {
			$target_url = get_permalink( $target_id );
			if ( $target_url && strpos( $source->post_content, $target_url ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
