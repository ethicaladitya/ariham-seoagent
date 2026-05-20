<?php
/**
 * Search intent classifier.
 *
 * Classifies a search query into one of four intent types using a
 * modifier-word heuristic. No external API required.
 *
 * Intent types:
 *   informational  — user wants to learn something (how to, what is, guide, tutorial)
 *   commercial     — user is comparing or researching before buying (best, vs, review, top)
 *   transactional  — user is ready to act/buy (buy, price, deal, coupon, download, free)
 *   navigational   — user wants a specific site/page (login, official, site:)
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Search_Intent {

	const INFORMATIONAL = 'informational';
	const COMMERCIAL    = 'commercial';
	const TRANSACTIONAL = 'transactional';
	const NAVIGATIONAL  = 'navigational';
	const UNKNOWN       = 'unknown';

	/** @var string[] */
	private static $informational = array(
		'how to',
		'what is',
		'what are',
		'how does',
		'why is',
		'why does',
		'when is',
		'who is',
		'which is',
		'define',
		'definition',
		'meaning',
		'examples',
		'tutorial',
		'guide',
		'learn',
		'tips',
		'ideas',
		'ways to',
		'explained',
		'overview',
		'introduction',
		'beginner',
		'basics',
		'difference between',
		'vs',
		'versus',
		'compare',
		'comparison',
		'how much does',
		'how many',
	);

	/** @var string[] */
	private static $commercial = array(
		'best',
		'top',
		'review',
		'reviews',
		'rated',
		'ranking',
		'recommend',
		'alternative',
		'alternatives',
		'vs',
		'versus',
		'compare',
		'comparison',
		'worth it',
		'pros and cons',
		'should i',
		'is it worth',
		'better than',
		'cheap',
		'affordable',
		'budget',
	);

	/** @var string[] */
	private static $transactional = array(
		'buy',
		'purchase',
		'order',
		'shop',
		'get',
		'download',
		'install',
		'sign up',
		'sign-up',
		'signup',
		'register',
		'subscribe',
		'trial',
		'free trial',
		'discount',
		'coupon',
		'promo',
		'deal',
		'offer',
		'price',
		'pricing',
		'cost',
		'how much',
		'quote',
		'hire',
		'book',
		'booking',
		'schedule',
		'apply',
		'start',
		'join',
	);

	/** @var string[] */
	private static $navigational = array(
		'login',
		'log in',
		'sign in',
		'dashboard',
		'account',
		'portal',
		'official',
		'website',
		'site',
		'homepage',
		'contact',
		'support',
		'help',
		'docs',
		'documentation',
		'github',
		'linkedin',
		'twitter',
	);

	/**
	 * Classify a query string and return the intent constant.
	 *
	 * @param string $query The raw search query.
	 * @return string One of the class constants.
	 */
	public static function classify( $query ) {
		$q      = mb_strtolower( trim( (string) $query ) );
		$scores = array(
			self::TRANSACTIONAL => 0,
			self::COMMERCIAL    => 0,
			self::INFORMATIONAL => 0,
			self::NAVIGATIONAL  => 0,
		);

		foreach ( self::$transactional as $kw ) {
			if ( false !== strpos( $q, $kw ) ) {
				$scores[ self::TRANSACTIONAL ] += 2; // Stronger signal.
			}
		}
		foreach ( self::$navigational as $kw ) {
			if ( false !== strpos( $q, $kw ) ) {
				$scores[ self::NAVIGATIONAL ] += 2;
			}
		}
		foreach ( self::$commercial as $kw ) {
			if ( false !== strpos( $q, $kw ) ) {
				++$scores[ self::COMMERCIAL ];
			}
		}
		foreach ( self::$informational as $kw ) {
			if ( false !== strpos( $q, $kw ) ) {
				++$scores[ self::INFORMATIONAL ];
			}
		}

		$max = max( $scores );
		if ( 0 === $max ) {
			return self::UNKNOWN;
		}

		arsort( $scores );
		reset( $scores );
		return (string) key( $scores );
	}

	/**
	 * Classify the dominant intent from a list of queries.
	 * Useful for GSC keyword arrays — finds the most common intent.
	 *
	 * @param array $queries Array of query strings.
	 * @return string
	 */
	public static function classify_bulk( array $queries ) {
		$tally = array(
			self::INFORMATIONAL => 0,
			self::COMMERCIAL    => 0,
			self::TRANSACTIONAL => 0,
			self::NAVIGATIONAL  => 0,
			self::UNKNOWN       => 0,
		);
		foreach ( $queries as $q ) {
			++$tally[ self::classify( $q ) ];
		}
		unset( $tally[ self::UNKNOWN ] );
		arsort( $tally );
		reset( $tally );
		$top = key( $tally );
		return ( null !== $top ) ? (string) $top : self::UNKNOWN;
	}

	/**
	 * Human-readable label for an intent constant.
	 *
	 * @param string $intent
	 * @return string
	 */
	public static function label( $intent ) {
		$labels = array(
			self::INFORMATIONAL => __( 'Informational', 'seo-agent-ai' ),
			self::COMMERCIAL    => __( 'Commercial', 'seo-agent-ai' ),
			self::TRANSACTIONAL => __( 'Transactional', 'seo-agent-ai' ),
			self::NAVIGATIONAL  => __( 'Navigational', 'seo-agent-ai' ),
			self::UNKNOWN       => __( 'Unknown', 'seo-agent-ai' ),
		);
		return isset( $labels[ $intent ] ) ? $labels[ $intent ] : $labels[ self::UNKNOWN ];
	}
}
