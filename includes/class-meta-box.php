<?php
/**
 * Per-post SEO metabox — Focus/Score, Meta overrides, Advanced/Robots.
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ariham_SEOAgent_Meta_Box {

	// -------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------

	public function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_meta_box_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'wp_ajax_ariham_seoagent_analyze_single_post', array( $this, 'ajax_analyze_single_post' ) );
	}

	// -------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------

	// -------------------------------------------------------------------
	// Asset enqueue (post edit screens)
	// -------------------------------------------------------------------

	public function enqueue_meta_box_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_style(
			'ariham-seoagent-admin',
			ARIHAM_SEOAGENT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ARIHAM_SEOAGENT_VERSION
		);
	}

	public function register_meta_boxes() {
		$post_types = (array) get_option( 'ariham_seoagent_post_types', array( 'post', 'page' ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'ariham_seoagent_meta_box',
				__( 'Ariham SEOAgent', 'ariham-seoagent' ),
				array( $this, 'render_meta_box' ),
				sanitize_key( $post_type ),
				'normal',
				'high'
			);
		}
	}

	// -------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------

	public function render_meta_box( WP_Post $post ) {
		wp_nonce_field( 'ariham_seoagent_meta_box_' . $post->ID, 'ariham_seoagent_meta_box_nonce' );

		// Read stored values.
		$score         = (int) get_post_meta( $post->ID, '_ariham_seoagent_score', true );
		$keyword       = (string) get_post_meta( $post->ID, '_ariham_seoagent_focus_keyword', true );
		$last_analyzed = (string) get_post_meta( $post->ID, '_ariham_seoagent_last_analyzed', true );
		$custom_title  = (string) get_post_meta( $post->ID, '_ariham_seoagent_custom_title', true );
		$custom_desc   = (string) get_post_meta( $post->ID, '_ariham_seoagent_custom_description', true );
		$canonical     = (string) get_post_meta( $post->ID, '_ariham_seoagent_canonical', true );
		$noindex       = (bool) get_post_meta( $post->ID, '_ariham_seoagent_robots_noindex', true );
		$nofollow      = (bool) get_post_meta( $post->ID, '_ariham_seoagent_robots_nofollow', true );
		$noarchive     = (bool) get_post_meta( $post->ID, '_ariham_seoagent_robots_noarchive', true );
		$nosnippet     = (bool) get_post_meta( $post->ID, '_ariham_seoagent_robots_nosnippet', true );
		$og_title      = (string) get_post_meta( $post->ID, '_ariham_seoagent_og_title', true );
		$og_desc       = (string) get_post_meta( $post->ID, '_ariham_seoagent_og_description', true );

		// Score badge colour.
		if ( $score >= 70 ) {
			$badge_color = '#46b450';
		} elseif ( $score >= 50 ) {
			$badge_color = '#f0b849';
		} else {
			$badge_color = '#dc3232';
		}

		$score_label = $score ? (string) $score : __( 'N/A', 'ariham-seoagent' );
		?>
		<div class="sai-tabs">
			<button type="button" class="sai-tab-btn active" data-tab="sai-tab-score"><?php esc_html_e( 'Focus & Score', 'ariham-seoagent' ); ?></button>
			<button type="button" class="sai-tab-btn" data-tab="sai-tab-meta"><?php esc_html_e( 'Meta', 'ariham-seoagent' ); ?></button>
			<button type="button" class="sai-tab-btn" data-tab="sai-tab-advanced"><?php esc_html_e( 'Advanced', 'ariham-seoagent' ); ?></button>
		</div>

		<div id="sai-tab-score" class="sai-tab-panel active">
			<div class="sai-row">
				<label><?php esc_html_e( 'SEO Score', 'ariham-seoagent' ); ?></label>
				<span class="sai-score-badge" style="background:<?php echo esc_attr( $badge_color ); ?>"><?php echo esc_html( $score_label ); ?></span>
			</div>
			<div class="sai-row">
				<label for="ariham_seoagent_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'ariham-seoagent' ); ?></label>
				<input type="text" id="ariham_seoagent_focus_keyword" name="ariham_seoagent_focus_keyword" value="<?php echo esc_attr( $keyword ); ?>" />
			</div>
			<?php if ( $last_analyzed ) : ?>
			<div class="sai-row">
				<label><?php esc_html_e( 'Last Analyzed', 'ariham-seoagent' ); ?></label>
				<span><?php echo esc_html( $last_analyzed ); ?></span>
			</div>
			<?php endif; ?>
			<div class="sai-row">
				<button type="button" id="sai-analyze-btn" class="button button-secondary" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					<?php esc_html_e( 'Analyze Now', 'ariham-seoagent' ); ?>
				</button>
				<span id="sai-analyze-status" style="margin-left:8px;"></span>
			</div>
		</div>

		<div id="sai-tab-meta" class="sai-tab-panel">
			<div class="sai-row">
				<label for="ariham_seoagent_custom_title"><?php esc_html_e( 'SEO Title Override', 'ariham-seoagent' ); ?></label>
				<input type="text" id="ariham_seoagent_custom_title" name="ariham_seoagent_custom_title"
					value="<?php echo esc_attr( $custom_title ); ?>"
					placeholder="<?php echo esc_attr( get_the_title( $post->ID ) ); ?>" />
			</div>
			<div class="sai-row">
				<label for="ariham_seoagent_custom_description"><?php esc_html_e( 'Meta Description', 'ariham-seoagent' ); ?></label>
				<textarea id="ariham_seoagent_custom_description" name="ariham_seoagent_custom_description" rows="3"><?php echo esc_textarea( $custom_desc ); ?></textarea>
				<span class="sai-char-count" id="sai-desc-count"><?php echo esc_html( mb_strlen( $custom_desc ) ); ?> / 160</span>
			</div>
			<div class="sai-row">
				<label for="ariham_seoagent_canonical"><?php esc_html_e( 'Canonical URL', 'ariham-seoagent' ); ?></label>
				<input type="url" id="ariham_seoagent_canonical" name="ariham_seoagent_canonical"
					value="<?php echo esc_url( $canonical ); ?>"
					placeholder="<?php echo esc_attr( get_permalink( $post->ID ) ); ?>" />
			</div>
		</div>

		<div id="sai-tab-advanced" class="sai-tab-panel">
			<div class="sai-row">
				<label><?php esc_html_e( 'Robots Directives', 'ariham-seoagent' ); ?></label>
				<label><input type="checkbox" name="ariham_seoagent_robots_noindex"   value="1" <?php checked( $noindex ); ?>> <?php esc_html_e( 'noindex', 'ariham-seoagent' ); ?></label><br>
				<label><input type="checkbox" name="ariham_seoagent_robots_nofollow"  value="1" <?php checked( $nofollow ); ?>> <?php esc_html_e( 'nofollow', 'ariham-seoagent' ); ?></label><br>
				<label><input type="checkbox" name="ariham_seoagent_robots_noarchive" value="1" <?php checked( $noarchive ); ?>> <?php esc_html_e( 'noarchive', 'ariham-seoagent' ); ?></label><br>
				<label><input type="checkbox" name="ariham_seoagent_robots_nosnippet" value="1" <?php checked( $nosnippet ); ?>> <?php esc_html_e( 'nosnippet', 'ariham-seoagent' ); ?></label>
			</div>
			<div class="sai-row">
				<label for="ariham_seoagent_og_title"><?php esc_html_e( 'Social OG Title Override', 'ariham-seoagent' ); ?></label>
				<input type="text" id="ariham_seoagent_og_title" name="ariham_seoagent_og_title" value="<?php echo esc_attr( $og_title ); ?>" />
			</div>
			<div class="sai-row">
				<label for="ariham_seoagent_og_description"><?php esc_html_e( 'Social OG Description Override', 'ariham-seoagent' ); ?></label>
				<textarea id="ariham_seoagent_og_description" name="ariham_seoagent_og_description" rows="2"><?php echo esc_textarea( $og_desc ); ?></textarea>
			</div>
		</div>

		<?php ob_start(); ?>
		(function(){
			var tabs = document.querySelectorAll('.sai-tab-btn');
			tabs.forEach(function(btn){
				btn.addEventListener('click', function(){
					tabs.forEach(function(b){ b.classList.remove('active'); });
					document.querySelectorAll('.sai-tab-panel').forEach(function(p){ p.classList.remove('active'); });
					btn.classList.add('active');
					document.getElementById(btn.dataset.tab).classList.add('active');
				});
			});

			var descArea = document.getElementById('ariham_seoagent_custom_description');
			var descCount = document.getElementById('sai-desc-count');
			if(descArea && descCount){
				descArea.addEventListener('input', function(){
					descCount.textContent = descArea.value.length + ' / 160';
				});
			}

			var analyzeBtn = document.getElementById('sai-analyze-btn');
			if(analyzeBtn){
				analyzeBtn.addEventListener('click', function(){
					var status = document.getElementById('sai-analyze-status');
					status.textContent = '<?php echo esc_js( __( 'Analyzing…', 'ariham-seoagent' ) ); ?>';
					analyzeBtn.disabled = true;
					var data = new FormData();
					data.append('action', 'ariham_seoagent_analyze_single_post');
					data.append('post_id', analyzeBtn.dataset.postId);
					data.append('nonce', '<?php echo esc_js( wp_create_nonce( 'ariham_seoagent_analyze_post' ) ); ?>');
					fetch(ajaxurl, { method:'POST', body:data, credentials:'same-origin' })
						.then(function(r){ return r.json(); })
						.then(function(resp){
							if(resp.success){
								status.textContent = '<?php echo esc_js( __( 'Done! Score: ', 'ariham-seoagent' ) ); ?>' + (resp.data.score || '?');
								setTimeout(function(){ location.reload(); }, 1500);
							} else {
								status.textContent = resp.data || '<?php echo esc_js( __( 'Error.', 'ariham-seoagent' ) ); ?>';
							}
						})
						.catch(function(){ status.textContent = '<?php echo esc_js( __( 'Network error.', 'ariham-seoagent' ) ); ?>'; })
						.finally(function(){ analyzeBtn.disabled = false; });
				});
			}
		})();
		<?php wp_add_inline_script( 'jquery', ob_get_clean() ); ?>
		<?php
	}

	// -------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------

	public function save_meta_box( $post_id, WP_Post $post ) {
		// Bail on autosave, revisions, or missing nonce.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $_POST['ariham_seoagent_meta_box_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ariham_seoagent_meta_box_nonce'] ) ), 'ariham_seoagent_meta_box_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array(
			'ariham_seoagent_focus_keyword' => '_ariham_seoagent_focus_keyword',
			'ariham_seoagent_custom_title'  => '_ariham_seoagent_custom_title',
			'ariham_seoagent_canonical'     => '_ariham_seoagent_canonical',
			'ariham_seoagent_og_title'      => '_ariham_seoagent_og_title',
		);

		foreach ( $text_fields as $field => $meta_key ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = $field === 'ariham_seoagent_canonical'
					? esc_url_raw( wp_unslash( $_POST[ $field ] ) )
					: sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		$textarea_fields = array(
			'ariham_seoagent_custom_description' => '_ariham_seoagent_custom_description',
			'ariham_seoagent_og_description'     => '_ariham_seoagent_og_description',
		);

		foreach ( $textarea_fields as $field => $meta_key ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		$checkbox_fields = array(
			'ariham_seoagent_robots_noindex'   => '_ariham_seoagent_robots_noindex',
			'ariham_seoagent_robots_nofollow'  => '_ariham_seoagent_robots_nofollow',
			'ariham_seoagent_robots_noarchive' => '_ariham_seoagent_robots_noarchive',
			'ariham_seoagent_robots_nosnippet' => '_ariham_seoagent_robots_nosnippet',
		);

		foreach ( $checkbox_fields as $field => $meta_key ) {
			update_post_meta( $post_id, $meta_key, ! empty( $_POST[ $field ] ) ? '1' : '0' );
		}
	}

	// -------------------------------------------------------------------
	// AJAX — single-post analysis
	// -------------------------------------------------------------------

	public function ajax_analyze_single_post() {
		check_ajax_referer( 'ariham_seoagent_analyze_post', 'nonce' );

		$post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'Unauthorized or invalid post.', 'ariham-seoagent' ), 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( __( 'Post not found.', 'ariham-seoagent' ) );
		}

		$result = Ariham_SEOAgent_Plugin::instance()->analyze_post_for_cli( $post, false, true );

		$score = (int) get_post_meta( $post_id, '_ariham_seoagent_score', true );
		$recs  = isset( $result['recommendations'] ) ? count( $result['recommendations'] ) : 0;

		$top_issues = array();
		if ( ! empty( $result['recommendations'] ) ) {
			$slice = array_slice( $result['recommendations'], 0, 3 );
			foreach ( $slice as $rec ) {
				$top_issues[] = isset( $rec['reasoning'] ) ? $rec['reasoning'] : '';
			}
		}

		wp_send_json_success(
			array(
				'score'                 => $score,
				'recommendations_count' => $recs,
				'top_issues'            => $top_issues,
			)
		);
	}
}
