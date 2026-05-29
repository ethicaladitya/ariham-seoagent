<?php
/**
 * Redirects & 404s admin page.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Redirects_Page {

	/** @var SEO_Agent_AI_Redirect_Manager */
	private $redirect_manager;

	public function __construct( SEO_Agent_AI_Redirect_Manager $redirect_manager ) {
		$this->redirect_manager = $redirect_manager;
	}

	/**
	 * Handle add/delete redirect form POST actions.
	 */
	public function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ariham-seoagent' ) );
		}

		$action = isset( $_POST['seo_redirect_action'] ) ? sanitize_key( $_POST['seo_redirect_action'] ) : '';

		if ( 'add' === $action ) {
			check_admin_referer( 'seo_agent_ai_add_redirect' );
			$source = isset( $_POST['source_url'] ) ? sanitize_text_field( wp_unslash( $_POST['source_url'] ) ) : '';
			$target = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';
			$type   = isset( $_POST['redirect_type'] ) ? absint( $_POST['redirect_type'] ) : 301;
			$notes  = isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : '';

			if ( $source && $target ) {
				$this->redirect_manager->add_redirect( $source, $target, $type, $notes );
			}
		} elseif ( 'delete' === $action ) {
			check_admin_referer( 'seo_agent_ai_delete_redirect' );
			$id = isset( $_POST['redirect_id'] ) ? absint( $_POST['redirect_id'] ) : 0;
			if ( $id ) {
				$this->redirect_manager->delete_redirect( $id );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=seo-agent-redirects' ) );
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'redirects'; // phpcs:ignore WordPress.Security.NonceVerification
		$stats     = $this->redirect_manager->get_stats();
		$redirects = $this->redirect_manager->get_redirects( 100 );
		$log_404   = $this->redirect_manager->get_404_log( 100 );
		?>
		<div class="wrap sai-page">
			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow"><span class="sai-dot"></span><?php esc_html_e( 'SEO Agent AI', 'ariham-seoagent' ); ?></p>
					<h1 class="sai-header-title"><?php esc_html_e( 'Redirects &amp; 404 Monitor', 'ariham-seoagent' ); ?></h1>
				</div>
				<div class="sai-header-actions">
					<nav class="sai-nav">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-redirects&tab=redirects' ) ); ?>"
							class="sai-nav-tab<?php echo 'redirects' === $tab ? ' active' : ''; ?>">
							<?php esc_html_e( 'Redirects', 'ariham-seoagent' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-redirects&tab=404s' ) ); ?>"
							class="sai-nav-tab<?php echo '404s' === $tab ? ' active' : ''; ?>">
							<?php esc_html_e( '404 Log', 'ariham-seoagent' ); ?>
						</a>
					</nav>
				</div>
			</div>

			<div class="sai-body">
				<!-- Metrics -->
				<div class="sai-metrics" style="margin-bottom:20px">
					<div class="sai-metric m-primary">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Active Redirects', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value"><?php echo esc_html( number_format_i18n( $stats['total_redirects'] ) ); ?></div>
					</div>
					<div class="sai-metric m-danger">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Total 404s Logged', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value"><?php echo esc_html( number_format_i18n( $stats['total_404s'] ) ); ?></div>
					</div>
					<div class="sai-metric m-warning">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Unresolved 404s', 'ariham-seoagent' ); ?></div>
						<div class="sai-metric-value"><?php echo esc_html( number_format_i18n( $stats['unresolved_404s'] ) ); ?></div>
					</div>
				</div>

				<?php if ( 'redirects' === $tab ) : ?>

				<!-- Add Redirect Form -->
				<div class="sai-card" style="margin-bottom:20px">
					<div class="sai-card-header"><h2 class="sai-card-title"><?php esc_html_e( 'Add Redirect', 'ariham-seoagent' ); ?></h2></div>
					<div class="sai-card-body">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'seo_agent_ai_add_redirect' ); ?>
							<input type="hidden" name="action" value="seo_agent_ai_manage_redirect">
							<input type="hidden" name="seo_redirect_action" value="add">
							<div class="sai-field">
								<label class="sai-field-label" for="source_url"><?php esc_html_e( 'Source URL / Path', 'ariham-seoagent' ); ?></label>
								<div class="sai-field-control">
									<input type="text" id="source_url" name="source_url" class="regular-text" placeholder="/old-page/" required>
								</div>
							</div>
							<div class="sai-field">
								<label class="sai-field-label" for="target_url"><?php esc_html_e( 'Target URL', 'ariham-seoagent' ); ?></label>
								<div class="sai-field-control">
									<input type="url" id="target_url" name="target_url" class="regular-text" placeholder="https://example.com/new-page/" required>
								</div>
							</div>
							<div class="sai-field">
								<label class="sai-field-label" for="redirect_type"><?php esc_html_e( 'Type', 'ariham-seoagent' ); ?></label>
								<div class="sai-field-control">
									<select id="redirect_type" name="redirect_type">
										<option value="301">301 &mdash; <?php esc_html_e( 'Permanent', 'ariham-seoagent' ); ?></option>
										<option value="302">302 &mdash; <?php esc_html_e( 'Temporary', 'ariham-seoagent' ); ?></option>
										<option value="307">307 &mdash; <?php esc_html_e( 'Temporary (preserve method)', 'ariham-seoagent' ); ?></option>
									</select>
								</div>
							</div>
							<div class="sai-field">
								<label class="sai-field-label" for="notes"><?php esc_html_e( 'Notes', 'ariham-seoagent' ); ?></label>
								<div class="sai-field-control">
									<input type="text" id="notes" name="notes" class="regular-text" placeholder="<?php esc_attr_e( 'Optional reason', 'ariham-seoagent' ); ?>">
								</div>
							</div>
							<div style="margin-top:12px">
								<button type="submit" class="sai-btn sai-btn-primary"><span class="btn-label"><?php esc_html_e( 'Add Redirect', 'ariham-seoagent' ); ?></span></button>
							</div>
						</form>
					</div>
				</div>

				<!-- Redirects table -->
					<?php if ( $redirects ) : ?>
				<div class="sai-card">
					<div class="sai-card-header"><h2 class="sai-card-title"><?php esc_html_e( 'Active Redirects', 'ariham-seoagent' ); ?></h2></div>
					<div class="sai-card-body" style="padding:0">
						<div class="sai-table-wrap">
							<table class="sai-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Source', 'ariham-seoagent' ); ?></th>
										<th><?php esc_html_e( 'Target', 'ariham-seoagent' ); ?></th>
										<th class="col-center"><?php esc_html_e( 'Type', 'ariham-seoagent' ); ?></th>
										<th class="col-center col-num"><?php esc_html_e( 'Hits', 'ariham-seoagent' ); ?></th>
												<th><?php esc_html_e( 'Action', 'ariham-seoagent' ); ?></th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $redirects as $r ) : ?>
									<tr>
										<td>
										<code><?php echo esc_html( $r['source_url'] ); ?></code>
										<?php if ( ! empty( $r['via'] ) && 'own' !== $r['via'] ) : ?>
											<span class="sai-badge b-info" style="margin-left:6px;vertical-align:middle"><?php echo esc_html( $this->redirect_manager->get_backend_label() ); ?></span>
										<?php endif; ?>
									</td>
										<td class="col-trunc"><a href="<?php echo esc_url( $r['target_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['target_url'] ); ?></a></td>
										<td class="col-center">
											<span class="sai-redirect-type"><?php echo esc_html( $r['redirect_type'] ); ?></span>
										</td>
										<td class="col-center col-num">
										<?php echo ( isset( $r['hit_count'] ) && null !== $r['hit_count'] ) ? esc_html( number_format_i18n( (int) $r['hit_count'] ) ) : '&mdash;'; ?>
									</td>
										<td>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
												<?php wp_nonce_field( 'seo_agent_ai_delete_redirect' ); ?>
												<input type="hidden" name="action" value="seo_agent_ai_manage_redirect">
												<input type="hidden" name="seo_redirect_action" value="delete">
												<input type="hidden" name="redirect_id" value="<?php echo esc_attr( $r['id'] ); ?>">
												<button type="submit" class="sai-btn sai-btn-danger sai-btn-sm sai-delete-redirect"
													onclick="return confirm('<?php echo esc_js( __( 'Delete this redirect?', 'ariham-seoagent' ) ); ?>')">
													<span class="btn-label"><?php esc_html_e( 'Delete', 'ariham-seoagent' ); ?></span>
												</button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<?php else : ?>
					<div class="sai-empty">
						<div class="sai-empty-icon">&#10145;</div>
						<h3><?php esc_html_e( 'No redirects yet', 'ariham-seoagent' ); ?></h3>
						<p><?php esc_html_e( 'No redirects configured yet.', 'ariham-seoagent' ); ?></p>
					</div>
				<?php endif; ?>

				<?php else : ?>

				<!-- 404 Log -->
					<?php if ( $log_404 ) : ?>
				<div class="sai-card">
					<div class="sai-card-header"><h2 class="sai-card-title"><?php esc_html_e( '404 Error Log', 'ariham-seoagent' ); ?></h2></div>
					<div class="sai-card-body" style="padding:0">
						<div class="sai-table-wrap">
							<table class="sai-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'URL', 'ariham-seoagent' ); ?></th>
										<th class="col-center col-num"><?php esc_html_e( 'Hits', 'ariham-seoagent' ); ?></th>
										<th><?php esc_html_e( 'First Seen', 'ariham-seoagent' ); ?></th>
										<th><?php esc_html_e( 'Last Seen', 'ariham-seoagent' ); ?></th>
										<th><?php esc_html_e( 'Action', 'ariham-seoagent' ); ?></th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $log_404 as $e ) : ?>
									<tr>
										<td>
											<code><?php echo esc_html( $e['url'] ); ?></code>
											<?php if ( $e['referrer'] ) : ?>
												<br><small style="color:#787c82"><?php esc_html_e( 'Referrer:', 'ariham-seoagent' ); ?> <?php echo esc_html( $e['referrer'] ); ?></small>
											<?php endif; ?>
										</td>
										<td class="col-center col-num"><?php echo esc_html( number_format_i18n( (int) $e['hit_count'] ) ); ?></td>
										<td>
											<?php if ( ! $e['redirect_created'] ) : ?>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=seo-agent-redirects&tab=redirects&prefill=' . rawurlencode( $e['url'] ) ) ); ?>"
												class="sai-btn sai-btn-ghost sai-btn-sm">
												<span class="btn-label"><?php esc_html_e( '+ Create Redirect', 'ariham-seoagent' ); ?></span>
											</a>
											<?php else : ?>
												<span class="sai-badge b-success">&#10003; <?php esc_html_e( 'Redirected', 'ariham-seoagent' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				<?php else : ?>
					<div class="sai-empty">
						<div class="sai-empty-icon">&#128077;</div>
						<h3><?php esc_html_e( 'No 404 errors', 'ariham-seoagent' ); ?></h3>
						<p><?php esc_html_e( 'No 404 errors logged yet. They appear here automatically when visitors hit broken URLs.', 'ariham-seoagent' ); ?></p>
					</div>
				<?php endif; ?>

				<?php endif; ?>
			</div>
		</div>

		<?php ob_start(); ?>
		(function($){
			// Pre-fill source URL from 404 log link.
			var params = new URLSearchParams(window.location.search);
			var prefill = params.get('prefill');
			if ( prefill ) {
				$('input[name="source_url"]').val(decodeURIComponent(prefill));
			}
		}(jQuery));
		<?php wp_add_inline_script( 'seo-agent-ai-admin', ob_get_clean() ); ?>
		<?php
	}
}
