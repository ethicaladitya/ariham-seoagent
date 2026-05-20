<?php
/**
 * GPT-powered content expander.
 *
 * Takes a post marked for content expansion and uses the configured AI
 * provider (OpenAI or Gemini) to draft a new content section. The draft
 * is saved as a pending post revision so the site owner can review and
 * publish it with one click. Nothing is published automatically.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Content_Expander {

	/** @var SEO_Agent_AI_OpenAI_Client */
	private $openai;

	/** @var SEO_Agent_AI_Gemini_Client */
	private $gemini;

	public function __construct(
		SEO_Agent_AI_OpenAI_Client $openai,
		SEO_Agent_AI_Gemini_Client $gemini
	) {
		$this->openai = $openai;
		$this->gemini = $gemini;
	}

	/**
	 * Draft a content expansion for a post and store it as a pending revision.
	 *
	 * @param int    $post_id         Post to expand.
	 * @param string $expansion_focus Optional focus topic/keyword to expand around.
	 * @param array  $gsc_queries     Top GSC queries for context.
	 * @param string $search_intent   Intent (informational|commercial|transactional).
	 * @return true|WP_Error  true on success; WP_Error on failure.
	 */
	public function expand( $post_id, $expansion_focus = '', array $gsc_queries = array(), $search_intent = '' ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'seo-agent-ai' ) );
		}

		$draft = $this->draft_expansion( $post, $expansion_focus, $gsc_queries, $search_intent );
		if ( is_wp_error( $draft ) ) {
			return $draft;
		}
		if ( empty( $draft ) ) {
			return new WP_Error( 'empty_draft', __( 'AI returned an empty expansion draft.', 'seo-agent-ai' ) );
		}

		return $this->save_draft( $post, $draft );
	}

	/**
	 * Draft a content refresh (rewrite of stale/thin sections) for a post.
	 *
	 * @param int    $post_id
	 * @param array  $gsc_queries Top GSC queries.
	 * @param string $search_intent
	 * @return true|WP_Error
	 */
	public function refresh( $post_id, array $gsc_queries = array(), $search_intent = '' ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'seo-agent-ai' ) );
		}

		$draft = $this->draft_refresh( $post, $gsc_queries, $search_intent );
		if ( is_wp_error( $draft ) ) {
			return $draft;
		}
		if ( empty( $draft ) ) {
			return new WP_Error( 'empty_draft', __( 'AI returned an empty refresh draft.', 'seo-agent-ai' ) );
		}

		return $this->save_draft( $post, $draft );
	}

	// -------------------------------------------------------------------
	// AI drafting
	// -------------------------------------------------------------------

	/**
	 * Build and send the expansion prompt.
	 *
	 * @param WP_Post $post
	 * @param string  $focus
	 * @param array   $queries
	 * @param string  $intent
	 * @return string|WP_Error
	 */
	private function draft_expansion( WP_Post $post, $focus, array $queries, $intent ) {
		$excerpt      = $this->excerpt( $post, 600 );
		$queries_text = implode( ', ', array_slice( $queries, 0, 8 ) );
		$focus_line   = $focus ? "\nFocus topic to expand: {$focus}" : '';
		$intent_line  = $intent ? "\nSearch intent: {$intent} — tailor depth and style accordingly." : '';
		$queries_line = $queries_text ? "\nTop ranking queries: {$queries_text}" : '';

		$prompt = "You are an expert SEO content writer. A blog post needs a new content section to rank better and satisfy reader intent.{$focus_line}{$intent_line}{$queries_line}\n\n"
			. "Post title: {$post->post_title}\n"
			. "Existing content excerpt:\n{$excerpt}\n\n"
			. "Write a new, well-structured content section (300–600 words) that expands on the topic. "
			. "Use clear subheadings (H2 or H3). Write in plain HTML using only <h2>, <h3>, <p>, <ul>, <li>, <strong>. "
			. "Do NOT repeat content already in the excerpt. Do NOT include markdown. "
			. "Do NOT add an introduction paragraph — jump straight into the new section content.";

		return $this->ai_complete( $prompt, 800 );
	}

	/**
	 * Build and send the refresh prompt.
	 *
	 * @param WP_Post $post
	 * @param array   $queries
	 * @param string  $intent
	 * @return string|WP_Error
	 */
	private function draft_refresh( WP_Post $post, array $queries, $intent ) {
		$excerpt      = $this->excerpt( $post, 800 );
		$queries_text = implode( ', ', array_slice( $queries, 0, 8 ) );
		$intent_line  = $intent ? "\nSearch intent: {$intent}" : '';
		$queries_line = $queries_text ? "\nTop ranking queries: {$queries_text}" : '';

		$prompt = "You are an expert SEO content writer. A blog post has become stale or thin and needs a content refresh.{$intent_line}{$queries_line}\n\n"
			. "Post title: {$post->post_title}\n"
			. "Current content (may be outdated or thin):\n{$excerpt}\n\n"
			. "Rewrite and improve the content to be more comprehensive, up-to-date, and aligned with search intent. "
			. "Preserve the main topics but expand thin sections. "
			. "Output ONLY the improved content in plain HTML using <h2>, <h3>, <p>, <ul>, <li>, <strong>. "
			. "Do NOT include markdown, do NOT add an <h1>, do NOT add meta tags.";

		return $this->ai_complete( $prompt, 1200 );
	}

	/**
	 * Route a prompt to the best available AI provider.
	 *
	 * @param string $prompt
	 * @param int    $max_tokens
	 * @return string|WP_Error
	 */
	private function ai_complete( $prompt, $max_tokens ) {
		$provider = (string) get_option( 'seo_agent_ai_ai_provider', 'auto' );

		if ( 'openai' === $provider || ( 'auto' === $provider && $this->openai->is_configured() ) ) {
			$result = $this->openai->complete_long( $prompt, $max_tokens );
			if ( ! is_wp_error( $result ) && '' !== $result ) {
				return $result;
			}
		}

		if ( 'gemini' === $provider || 'auto' === $provider ) {
			return $this->gemini->complete( $prompt );
		}

		return new WP_Error( 'no_ai_configured', __( 'No AI provider configured. Add an OpenAI or Gemini API key in Settings.', 'seo-agent-ai' ) );
	}

	// -------------------------------------------------------------------
	// Draft saving (creates a pending child post for human review)
	// -------------------------------------------------------------------

	/**
	 * Save the AI draft as a 'pending' post with the original post as parent.
	 * The user sees it in WP Admin → Posts → Pending and can review/publish.
	 *
	 * @param WP_Post $original Original post.
	 * @param string  $draft    AI-generated HTML content.
	 * @return true|WP_Error
	 */
	private function save_draft( WP_Post $original, $draft ) {
		// Build the draft content — append after the original.
		$draft_content = $original->post_content . "\n\n<!-- SEO Agent AI expansion draft -->\n" . wp_kses_post( $draft );

		$draft_id = wp_insert_post(
			array(
				'post_parent'  => $original->ID,
				'post_author'  => $original->post_author,
				'post_title'   => $original->post_title . ' [AI Draft]',
				'post_content' => $draft_content,
				'post_status'  => 'pending',
				'post_type'    => $original->post_type,
				'post_name'    => $original->post_name . '-ai-draft-' . time(),
			),
			true
		);

		if ( is_wp_error( $draft_id ) ) {
			return $draft_id;
		}

		// Tag this draft so the plugin can find/display it.
		update_post_meta( $draft_id, '_seo_agent_ai_content_draft', $original->ID );
		update_post_meta( $original->ID, '_seo_agent_ai_pending_draft_id', $draft_id );

		return true;
	}

	// -------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------

	/**
	 * Return a plain-text excerpt from post content, capped at $max_chars.
	 *
	 * @param WP_Post $post
	 * @param int     $max_chars
	 * @return string
	 */
	private function excerpt( WP_Post $post, $max_chars ) {
		$text = wp_strip_all_tags( $post->post_content );
		$text = preg_replace( '/\s+/', ' ', trim( $text ) );
		return mb_substr( $text, 0, $max_chars );
	}
}
