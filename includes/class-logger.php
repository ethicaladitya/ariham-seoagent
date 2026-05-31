<?php
/**
 * Debug / verbose logger.
 *
 * Writes to a dedicated log file inside a plugin-slug folder in the WordPress
 * uploads directory (never the plugin folder, which is wiped on upgrade) and
 * optionally mirrors to the native WordPress debug log. The directory is hardened
 * against public access. Rotation kicks in when the file exceeds 5 MB.
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ariham_SEOAgent_Logger {

	const OPTION_DEBUG_MODE   = 'ariham_seoagent_debug_mode';
	const OPTION_VERBOSE_MODE = 'ariham_seoagent_verbose_mode';

	const LEVEL_DEBUG   = 'DEBUG';
	const LEVEL_INFO    = 'INFO';
	const LEVEL_WARNING = 'WARNING';
	const LEVEL_ERROR   = 'ERROR';

	const LOG_BASENAME = 'ariham-seoagent-debug.log';
	const LOG_DIRNAME  = 'ariham-seoagent';
	const MAX_BYTES    = 5242880; // 5 MB

	/** @var bool */
	private $debug;
	/** @var bool */
	private $verbose;
	/** @var string */
	private $log_path;

	/**
	 * @param bool|null $debug   Override option (useful for WP-CLI --verbose flag).
	 * @param bool|null $verbose Override option.
	 */
	public function __construct( $debug = null, $verbose = null ) {
		$this->debug    = $debug !== null ? (bool) $debug : (bool) get_option( self::OPTION_DEBUG_MODE, false );
		$this->verbose  = $verbose !== null ? (bool) $verbose : (bool) get_option( self::OPTION_VERBOSE_MODE, false );
		$this->log_path = self::resolve_log_path();
	}

	/**
	 * Build the absolute path to the log file inside the uploads directory.
	 *
	 * Stored under uploads/<plugin-slug>/ so it survives plugin upgrades and is
	 * compatible with multisite. No filesystem writes happen here.
	 *
	 * @return string
	 */
	private static function resolve_log_path() {
		$uploads = wp_upload_dir( null, false );
		$base    = ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : '';
		if ( empty( $base ) ) {
			return '';
		}
		return trailingslashit( $base ) . self::LOG_DIRNAME . '/' . self::LOG_BASENAME;
	}

	/**
	 * Ensure the log directory exists and is hardened against public access.
	 * Cheap to call repeatedly — bails as soon as the guard files are present.
	 */
	private function ensure_log_dir() {
		$dir = dirname( $this->log_path );

		if ( empty( $this->log_path ) ) {
			return;
		}

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) && ! empty( $wp_filesystem ) ) {
			$wp_filesystem->put_contents( $htaccess, "Order allow,deny\nDeny from all\n", FS_CHMOD_FILE );
		}

		$index = $dir . '/index.html';
		if ( ! file_exists( $index ) && ! empty( $wp_filesystem ) ) {
			$wp_filesystem->put_contents( $index, '', FS_CHMOD_FILE );
		}
	}

	// -------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------

	public function info( $message, array $context = array() ) {
		$this->write( self::LEVEL_INFO, $message, $context );
	}

	public function debug( $message, array $context = array() ) {
		if ( $this->debug ) {
			$this->write( self::LEVEL_DEBUG, $message, $context );
		}
	}

	public function verbose( $message, array $context = array() ) {
		if ( $this->verbose || $this->debug ) {
			$this->write( self::LEVEL_DEBUG, '[verbose] ' . $message, $context );
		}
	}

	public function warning( $message, array $context = array() ) {
		$this->write( self::LEVEL_WARNING, $message, $context );
	}

	public function error( $message, array $context = array() ) {
		$this->write( self::LEVEL_ERROR, $message, $context );
	}

	// -------------------------------------------------------------------
	// Log reading (for WP-CLI + admin Cron Status page)
	// -------------------------------------------------------------------

	/**
	 * Return the last N lines from the log file, optionally filtered by level.
	 *
	 * @param int    $lines
	 * @param string $level_filter  One of DEBUG, INFO, WARNING, ERROR, or '' for all.
	 * @return string[]
	 */
	public function tail( $lines = 50, $level_filter = '' ) {
		if ( ! file_exists( $this->log_path ) ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local log file, not a remote URL; wp_remote_get() is inappropriate here.
		$raw = file_get_contents( $this->log_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- file_exists checked above.
		if ( ! $raw ) {
			return array();
		}

		$all = array_filter( explode( "\n", $raw ) );

		if ( $level_filter ) {
			$tag = '[' . strtoupper( $level_filter ) . ']';
			$all = array_filter(
				$all,
				function ( $l ) use ( $tag ) {
					return strpos( $l, $tag ) !== false;
				}
			);
		}

		return array_values( array_slice( array_values( $all ), -$lines ) );
	}

	/**
	 * Return full path to the current log file.
	 */
	public function get_log_path() {
		return $this->log_path;
	}

	/**
	 * Enable debug mode at runtime (e.g. from WP-CLI --verbose).
	 */
	public function enable_debug() {
		$this->debug   = true;
		$this->verbose = true;
	}

	// -------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------

	private function write( $level, $message, array $context = array() ) {
		$this->ensure_log_dir();
		$this->maybe_rotate();

		$ts   = gmdate( 'Y-m-d H:i:s' );
		$line = "[{$ts}] [SEO-AGENT] [{$level}] {$message}";

		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		if ( $this->log_path ) {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( ! empty( $wp_filesystem ) && $wp_filesystem->is_writable( $this->log_path ) ) {
				$existing = (string) $wp_filesystem->get_contents( $this->log_path );
				$wp_filesystem->put_contents( $this->log_path, $existing . $line . "\n", FS_CHMOD_FILE );
			}
		}

		// Mirror to native WP debug log.
		if (
			$this->debug &&
			defined( 'WP_DEBUG' ) && WP_DEBUG &&
			defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG
		) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $line );
		}
	}

	private function maybe_rotate() {
		if ( ! file_exists( $this->log_path ) ) {
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions -- filesize/rename on a local log file; WP_Filesystem is inappropriate in a passive write context.
		if ( filesize( $this->log_path ) > self::MAX_BYTES ) {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			if ( ! empty( $wp_filesystem ) && $wp_filesystem->is_writable( $this->log_path ) ) {
				$wp_filesystem->move( $this->log_path, $this->log_path . '.1', true );
			}
		}
	}
}
