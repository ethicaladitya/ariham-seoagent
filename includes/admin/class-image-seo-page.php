<?php
/**
 * Image SEO admin page — stats, missing alt text table, bulk generation.
 *
 * @package SEO_Agent_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO_Agent_AI_Image_SEO_Page {

	/** @var SEO_Agent_AI_Image_SEO */
	private $image_seo;

	public function __construct( SEO_Agent_AI_Image_SEO $image_seo ) {
		$this->image_seo = $image_seo;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stats   = $this->image_seo->get_image_stats();
		$missing = $this->image_seo->get_images_missing_alt( 50 );

		$coverage_pct = $stats['total'] > 0
			? round( ( ( $stats['total'] - $stats['missing_alt'] ) / $stats['total'] ) * 100 )
			: 100;
		?>
		<div class="wrap sai-page">
			<div class="sai-header">
				<div class="sai-header-left">
					<p class="sai-header-eyebrow"><span class="sai-dot"></span><?php esc_html_e( 'SEO Agent AI', 'seo-agent-ai' ); ?></p>
					<h1 class="sai-header-title"><?php esc_html_e( 'Image SEO', 'seo-agent-ai' ); ?></h1>
				</div>
				<div class="sai-header-actions">
					<?php if ( $stats['missing_alt'] > 0 ) : ?>
					<button id="seo-bulk-alt-btn" class="sai-btn sai-btn-primary">
						<span class="btn-label"><?php esc_html_e( 'Generate All Missing Alt Text', 'seo-agent-ai' ); ?></span>
					</button>
					<?php endif; ?>
				</div>
			</div>

			<div class="sai-body">
				<!-- Metrics row -->
				<div class="sai-metrics" style="margin-bottom:20px">
					<div class="sai-metric m-neutral">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Total Images', 'seo-agent-ai' ); ?></div>
						<div class="sai-metric-value"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></div>
					</div>
					<div class="sai-metric <?php echo $stats['missing_alt'] > 0 ? 'm-danger' : 'm-success'; ?>">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Missing Alt Text', 'seo-agent-ai' ); ?></div>
						<div class="sai-metric-value"><?php echo esc_html( number_format_i18n( $stats['missing_alt'] ) ); ?></div>
					</div>
					<div class="sai-metric m-primary">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'AI Generated', 'seo-agent-ai' ); ?></div>
						<div class="sai-metric-value"><?php echo esc_html( number_format_i18n( $stats['ai_generated'] ) ); ?></div>
					</div>
					<div class="sai-metric <?php echo $coverage_pct >= 90 ? 'm-success' : ( $coverage_pct >= 60 ? 'm-warning' : 'm-danger' ); ?>">
						<div class="sai-metric-stripe"></div>
						<div class="sai-metric-label"><?php esc_html_e( 'Alt Coverage', 'seo-agent-ai' ); ?></div>
						<div class="sai-metric-value"><?php echo esc_html( $coverage_pct . '%' ); ?></div>
					</div>
				</div>

				<?php if ( $stats['missing_alt'] > 0 ) : ?>
				<div id="seo-bulk-alt-status-wrap" style="margin-bottom:12px;display:none">
					<div class="sai-notice n-info">
						<p id="seo-bulk-alt-status"></p>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( empty( $missing ) ) : ?>
					<div class="sai-empty">
						<div class="sai-empty-icon">&#127881;</div>
						<h3><?php esc_html_e( 'All images have alt text!', 'seo-agent-ai' ); ?></h3>
						<p><?php esc_html_e( 'Great job — every image in your media library has descriptive alt text.', 'seo-agent-ai' ); ?></p>
					</div>
				<?php else : ?>
					<div class="sai-card">
						<div class="sai-card-header">
							<h2 class="sai-card-title"><?php esc_html_e( 'Images Missing Alt Text', 'seo-agent-ai' ); ?></h2>
						</div>
						<div class="sai-card-body" style="padding:0">
							<div class="sai-table-wrap">
								<table class="sai-table">
									<thead>
										<tr>
											<th style="width:80px"><?php esc_html_e( 'Preview', 'seo-agent-ai' ); ?></th>
											<th><?php esc_html_e( 'Filename', 'seo-agent-ai' ); ?></th>
											<th><?php esc_html_e( 'Parent Post', 'seo-agent-ai' ); ?></th>
											<th class="col-center col-num"><?php esc_html_e( 'Size', 'seo-agent-ai' ); ?></th>
											<th><?php esc_html_e( 'Action', 'seo-agent-ai' ); ?></th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ( $missing as $img ) : ?>
										<tr id="seo-img-row-<?php echo esc_attr( $img['id'] ); ?>">
											<td><?php echo wp_get_attachment_image( $img['id'], array( 60, 60 ) ); ?></td>
											<td>
												<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $img['id'] . '&action=edit' ) ); ?>">
													<?php echo esc_html( $img['filename'] ); ?>
												</a>
											</td>
											<td>
												<?php if ( $img['parent_post_id'] ) : ?>
													<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $img['parent_post_id'] . '&action=edit' ) ); ?>">
														<?php echo esc_html( $img['parent_post_title'] ?: __( 'View Post', 'seo-agent-ai' ) ); ?>
													</a>
												<?php else : ?>
													<span style="color:#787c82"><?php esc_html_e( 'Unattached', 'seo-agent-ai' ); ?></span>
												<?php endif; ?>
											</td>
											<td class="col-center col-num"><?php echo esc_html( $img['filesize_kb'] . ' KB' ); ?></td>
											<td>
												<button class="sai-btn sai-btn-ghost sai-btn-sm seo-gen-alt-btn"
													data-id="<?php echo esc_attr( $img['id'] ); ?>">
													<span class="btn-label"><?php esc_html_e( 'Generate Alt Text', 'seo-agent-ai' ); ?></span>
												</button>
												<span class="seo-alt-result" style="display:block;font-size:11px;color:#2271b1;margin-top:4px"></span>
											</td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<script>
		(function($){
			var nonce = '<?php echo esc_js( wp_create_nonce( 'seo_agent_ai_image_seo' ) ); ?>';

			function generateAlt( id, btn, resultEl, onDone ) {
				btn.prop('disabled', true).find('.btn-label').text('<?php echo esc_js( __( 'Generating…', 'seo-agent-ai' ) ); ?>');
				$.post(ajaxurl, {
					action: 'seo_agent_ai_generate_alt',
					attachment_id: id,
					nonce: nonce
				}, function(res){
					if ( res.success ) {
						resultEl.text(res.data.alt_text);
						btn.closest('tr').fadeOut(800, function(){ $(this).remove(); });
					} else {
						resultEl.css('color','#d63638').text(res.data || '<?php echo esc_js( __( 'Error', 'seo-agent-ai' ) ); ?>');
						btn.prop('disabled', false).find('.btn-label').text('<?php echo esc_js( __( 'Retry', 'seo-agent-ai' ) ); ?>');
					}
					if ( onDone ) { onDone( !! res.success ); }
				}).fail(function(){
					resultEl.css('color','#d63638').text('<?php echo esc_js( __( 'Request failed', 'seo-agent-ai' ) ); ?>');
					btn.prop('disabled', false).find('.btn-label').text('<?php echo esc_js( __( 'Retry', 'seo-agent-ai' ) ); ?>');
					if ( onDone ) { onDone(false); }
				});
			}

			// Single image alt generation.
			$(document).on('click', '.seo-gen-alt-btn', function(){
				var btn = $(this);
				generateAlt( btn.data('id'), btn, btn.siblings('.seo-alt-result') );
			});

			// Bulk: process one image at a time so no request times out.
			$('#seo-bulk-alt-btn').on('click', function(){
				var btn    = $(this);
				var status = $('#seo-bulk-alt-status');
				var wrap   = $('#seo-bulk-alt-status-wrap');
				var ids    = [];
				$('.seo-gen-alt-btn:not([disabled])').each(function(){ ids.push($(this).data('id')); });
				var total = ids.length, done = 0, success = 0;

				if ( ! total ) { return; }
				btn.prop('disabled', true);
				wrap.show();

				function next() {
					if ( ! ids.length ) {
						status.css('color','').text(
							'<?php echo esc_js( __( 'Done!', 'seo-agent-ai' ) ); ?> ' +
							success + ' / ' + total + ' <?php echo esc_js( __( 'generated', 'seo-agent-ai' ) ); ?>'
						);
						btn.prop('disabled', false);
						return;
					}
					var id  = ids.shift();
					var row = $('#seo-img-row-' + id);
					var b   = row.find('.seo-gen-alt-btn');
					var r   = row.find('.seo-alt-result');
					done++;
					status.text(
						'<?php echo esc_js( __( 'Processing', 'seo-agent-ai' ) ); ?> ' + done + ' / ' + total + '…'
					);
					generateAlt( id, b, r, function(ok){
						if ( ok ) { success++; }
						next();
					});
				}
				next();
			});
		}(jQuery));
		</script>
		<?php
	}
}
