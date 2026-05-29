<?php
/**
 * Taxonomy SEO — custom title/description/noindex for term archive pages.
 *
 * @package Ariham_SEOAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ariham_SEOAgent_Taxonomy_SEO {

	/** @var string[] Taxonomies to add SEO fields to. */
	private $taxonomies = array( 'category', 'post_tag' );

	// -------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------

	public function init_hooks() {
		foreach ( $this->taxonomies as $taxonomy ) {
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_fields' ), 10, 1 );
			add_action( 'edited_' . $taxonomy, array( $this, 'save_term_meta' ), 10, 1 );
		}

		// Output meta on term archive pages (priority 2 — after Social Meta at priority 1).
		add_action( 'wp_head', array( $this, 'output_term_meta' ), 2 );
		add_action( 'wp_head', array( $this, 'init_homepage_meta' ), 3 );
	}

	// -------------------------------------------------------------------
	// Admin form fields
	// -------------------------------------------------------------------

	/**
	 * Render SEO fields on term edit screen.
	 *
	 * @param WP_Term $term Current term.
	 */
	public function render_term_fields( WP_Term $term ) {
		wp_nonce_field( 'ariham_seoagent_term_' . $term->term_id, 'ariham_seoagent_term_nonce' );

		$seo_title = (string) get_term_meta( $term->term_id, '_ariham_seoagent_term_title', true );
		$seo_desc  = (string) get_term_meta( $term->term_id, '_ariham_seoagent_term_description', true );
		$noindex   = (bool) get_term_meta( $term->term_id, '_ariham_seoagent_term_noindex', true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="ariham_seoagent_term_title"><?php esc_html_e( 'SEO Title', 'ariham-seoagent' ); ?></label>
			</th>
			<td>
				<input type="text" id="ariham_seoagent_term_title" name="ariham_seoagent_term_title"
					value="<?php echo esc_attr( $seo_title ); ?>" style="width:100%" />
				<p class="description"><?php esc_html_e( 'Custom title tag for this term archive page. Leave empty to use the default.', 'ariham-seoagent' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="ariham_seoagent_term_description"><?php esc_html_e( 'Meta Description', 'ariham-seoagent' ); ?></label>
			</th>
			<td>
				<textarea id="ariham_seoagent_term_description" name="ariham_seoagent_term_description" rows="3" style="width:100%"><?php echo esc_textarea( $seo_desc ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Custom meta description for this term archive page. Max 160 characters recommended.', 'ariham-seoagent' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row">
				<label for="ariham_seoagent_term_noindex"><?php esc_html_e( 'Robots', 'ariham-seoagent' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="ariham_seoagent_term_noindex" name="ariham_seoagent_term_noindex" value="1" <?php checked( $noindex ); ?>>
					<?php esc_html_e( 'noindex — prevent search engines from indexing this term archive.', 'ariham-seoagent' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	// -------------------------------------------------------------------
	// Save term meta
	// -------------------------------------------------------------------

	/**
	 * Save term SEO meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_term_meta( $term_id ) {
		if ( empty( $_POST['ariham_seoagent_term_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ariham_seoagent_term_nonce'] ) ), 'ariham_seoagent_term_' . $term_id ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		if ( isset( $_POST['ariham_seoagent_term_title'] ) ) {
			update_term_meta( $term_id, '_ariham_seoagent_term_title', sanitize_text_field( wp_unslash( $_POST['ariham_seoagent_term_title'] ) ) );
		}

		if ( isset( $_POST['ariham_seoagent_term_description'] ) ) {
			update_term_meta( $term_id, '_ariham_seoagent_term_description', sanitize_textarea_field( wp_unslash( $_POST['ariham_seoagent_term_description'] ) ) );
		}

		update_term_meta( $term_id, '_ariham_seoagent_term_noindex', ! empty( $_POST['ariham_seoagent_term_noindex'] ) ? '1' : '0' );
	}

	// -------------------------------------------------------------------
	// Frontend output
	// -------------------------------------------------------------------

	/**
	 * Output custom title / description / robots for term archives.
	 */
	public function output_term_meta() {
		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$seo_title = (string) get_term_meta( $term->term_id, '_ariham_seoagent_term_title', true );
		$seo_desc  = (string) get_term_meta( $term->term_id, '_ariham_seoagent_term_description', true );
		$noindex   = (bool) get_term_meta( $term->term_id, '_ariham_seoagent_term_noindex', true );

		if ( $seo_title ) {
			echo '<title>' . esc_html( $seo_title ) . '</title>' . "\n";
		}

		if ( $seo_desc ) {
			echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '" />' . "\n";
		}

		if ( $noindex ) {
			echo '<meta name="robots" content="noindex" />' . "\n";
		}
	}

	/**
	 * Output custom title / description for the homepage.
	 */
	public function init_homepage_meta() {
		if ( ! is_front_page() ) {
			return;
		}

		$title = (string) get_option( 'ariham_seoagent_homepage_title', '' );
		$desc  = (string) get_option( 'ariham_seoagent_homepage_description', '' );

		if ( $title ) {
			echo '<title>' . esc_html( $title ) . '</title>' . "\n";
		}

		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
	}
}
