<?php
/**
 * Admin options display - simple WordPress UI (no Vue/React).
 *
 * @package Every_Alt
 * @subpackage Every_Alt/admin/partials
 */
$base_url = admin_url( 'upload.php?page=everyalt' );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'EveryAlt', 'everyalt' ); ?></h1>
	<hr class="wp-header-end">
	<h2 class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu', 'everyalt' ); ?>">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $base_url ) ); ?>" class="nav-tab <?php echo $active === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'everyalt' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'bulk', $base_url ) ); ?>" class="nav-tab <?php echo $active === 'bulk' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Bulk Alt Text Generator', 'everyalt' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'review', $base_url ) ); ?>" class="nav-tab <?php echo $active === 'review' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Review Alt Text', 'everyalt' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'logs', $base_url ) ); ?>" class="nav-tab <?php echo $active === 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Logs', 'everyalt' ); ?></a>
	</h2>

<?php if ( isset( $_GET['updated'] ) && $_GET['updated'] === '1' ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'everyalt' ); ?></p></div>
<?php endif; ?>
<?php if ( isset( $_GET['error'] ) && $_GET['error'] === 'everyalt_invalid_key' ) : ?>
	<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The OpenAI API key you entered could not be validated. Please check the key and try again. It was not saved.', 'everyalt' ); ?></p></div>
<?php endif; ?>

<?php if ( $active === 'settings' ) : ?>
	<div class="everyalt-settings-wrap">
		<h2><?php esc_html_e( 'Settings', 'everyalt' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'everyalt_save_settings', 'everyalt_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="every_alt_openai_key"><?php esc_html_e( 'OpenAI API Key', 'everyalt' ); ?></label></th>
					<td>
						<span class="everyalt-key-row">
							<input type="password" name="every_alt_openai_key" id="every_alt_openai_key" value="" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Leave blank to keep existing key', 'everyalt' ); ?>">
							<button type="button" id="everyalt-validate-key" class="button"><?php esc_html_e( 'Validate key', 'everyalt' ); ?></button>
						</span>
						<p id="everyalt-validate-result" class="everyalt-validate-result" aria-live="polite" style="display:none; margin-top:0.5em;"></p>
						<p class="description"><?php esc_html_e( 'Alt text is generated via OpenAI (image is sent as base64, so it works on localhost and behind HTTP auth). Stored encrypted. You are charged by OpenAI for your usage; EveryAlt is free and never bills you.', 'everyalt' ); ?> <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Generate an API key', 'everyalt' ); ?></a></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-generate on upload', 'everyalt' ); ?></th>
					<td>
						<label><input type="checkbox" name="every_alt_auto" value="1" <?php checked( get_option( 'every_alt_auto', 0 ), 1 ); ?>><?php esc_html_e( 'Automatically generate alt text when images are uploaded', 'everyalt' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="every_alt_max_completion_tokens"><?php esc_html_e( 'Max completion tokens', 'everyalt' ); ?></label></th>
					<td>
						<input type="number" name="every_alt_max_completion_tokens" id="every_alt_max_completion_tokens" value="<?php echo esc_attr( get_option( 'every_alt_max_completion_tokens', '1024' ) ); ?>" min="1" max="128000" step="1" class="small-text">
						<p class="description"><?php esc_html_e( 'Maximum tokens the model can use for the response (including reasoning for models like gpt-5-nano). Default 1024. Leave empty to use default.', 'everyalt' ); ?></p>
						<?php
						$model_for_display = apply_filters( Every_Alt_OpenAI::FILTER_MODEL, 'gpt-5-nano' );
						$input_price       = (float) apply_filters( Every_Alt_OpenAI::FILTER_INPUT_PRICE_PER_MILLION, Every_Alt_OpenAI::DEFAULT_INPUT_PRICE_PER_MILLION );
						$output_price      = (float) apply_filters( Every_Alt_OpenAI::FILTER_OUTPUT_PRICE_PER_MILLION, Every_Alt_OpenAI::DEFAULT_OUTPUT_PRICE_PER_MILLION );
						$estimate_cents    = ( 200 * $input_price / 1000000 + 500 * $output_price / 1000000 ) * 100;
						?>
						<p class="description" style="margin-top:1em;">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: 1: model name, 2: cost in cents e.g. 0.0210¢ */
									__( 'Pricing: EveryAlt uses <strong>%1$s</strong>, currently the cheapest and most efficient model for this task. A reasonable estimate per image is about 200 input tokens and 500 output tokens, which works out to about <strong>%2$s</strong> per image.', 'everyalt' ),
									esc_html( $model_for_display ),
									number_format( $estimate_cents, 4 ) . '¢'
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="every_alt_vision_prompt"><?php esc_html_e( 'Alt text prompt', 'everyalt' ); ?></label></th>
					<td>
						<?php
						$default_prompt  = 'Describe this image in one short, clear sentence suitable for HTML alt text. Do not start with "This image shows" or similar. Output only the alt text, nothing else.';
						$current_prompt  = get_option( 'every_alt_vision_prompt', '' );
						$editable_prompt = $current_prompt !== '' ? $current_prompt : $default_prompt;
						?>
						<textarea name="every_alt_vision_prompt" id="every_alt_vision_prompt" class="large-text" rows="4"><?php echo esc_textarea( $editable_prompt ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Instruction sent to the AI with each image. Edit as needed. The model should return only the alt text, no extra wording.', 'everyalt' ); ?></p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<?php submit_button( __( 'Save Settings', 'everyalt' ), 'primary', 'submit', false ); ?>
			</p>
		</form>
	</div>
<?php endif; ?>

<?php if ( $active === 'bulk' ) : ?>
	<?php
	$error = isset( $this->error ) ? $this->error : false;
	?>
	<div class="everyalt-bulk-wrap">
		<h2><?php esc_html_e( 'Bulk Alt Text Generator', 'everyalt' ); ?></h2>
		<p class="description"><?php echo wp_kses_post( sprintf( __( 'This page finds all images in your media library that do not currently have alt text and lets you generate new alt text with EveryAlt quickly. To see existing images that already have alt text, go to the <a href="%s">Review Alt Text</a> tab.', 'everyalt' ), esc_url( add_query_arg( 'tab', 'review', $base_url ) ) ) ); ?></p>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php elseif ( ! $has_openai_key ) : ?>
			<p><?php esc_html_e( 'Please enter your OpenAI API key in the Settings tab first.', 'everyalt' ); ?></p>
		<?php elseif ( empty( $images_without_alt ) ) : ?>
			<p><?php esc_html_e( 'No images without alt text found.', 'everyalt' ); ?></p>
		<?php else : ?>
			<p class="everyalt-bulk-actions">
				<button type="button" id="everyalt-bulk-select-all" class="button"><?php esc_html_e( 'Select all', 'everyalt' ); ?></button>
				<button type="button" id="everyalt-bulk-select-none" class="button"><?php esc_html_e( 'Select none', 'everyalt' ); ?></button>
				<button type="button" id="everyalt-bulk-run" class="button button-primary"><?php esc_html_e( 'Generate alt text for selected', 'everyalt' ); ?></button>
			</p>
			<div id="everyalt-bulk-progress" class="everyalt-bulk-progress hidden">
				<p class="everyalt-bulk-progress-status"><strong><?php esc_html_e( 'Processing…', 'everyalt' ); ?></strong> <span id="everyalt-bulk-progress-text">0 / 0</span></p>
				<div class="everyalt-bulk-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><span id="everyalt-bulk-progress-fill"></span></div>
				<ul id="everyalt-bulk-progress-log" class="everyalt-bulk-progress-log" aria-live="polite"></ul>
			</div>
			<ul class="everyalt-bulk-grid" id="everyalt-bulk-grid">
				<?php foreach ( $images_without_alt as $image ) :
					$aid = (int) $image->ID;
					?>
					<li class="everyalt-bulk-item" data-media-id="<?php echo $aid; ?>">
						<label>
							<input type="checkbox" class="everyalt-bulk-checkbox" value="<?php echo $aid; ?>">
							<span class="everyalt-bulk-thumb"><?php echo wp_get_attachment_image( $aid, 'thumbnail' ); ?></span>
						</label>
						<?php
						$edit_link = get_edit_post_link( $aid, 'raw' );
						if ( $edit_link ) :
							?>
							<a href="<?php echo esc_url( $edit_link ); ?>" class="everyalt-bulk-edit-link"><?php esc_html_e( 'Edit', 'everyalt' ); ?></a>
						<?php endif; ?>
						<span class="everyalt-bulk-item-status" aria-live="polite"></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $active === 'review' ) : ?>
	<div class="everyalt-review-wrap">
		<h2><?php esc_html_e( 'Review Alt Text', 'everyalt' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Images that already have alt text. Edit and save, or regenerate with EveryAlt.', 'everyalt' ); ?></p>
		<?php if ( ! $has_openai_key ) : ?>
			<p><?php esc_html_e( 'Please enter your OpenAI API key in the Settings tab to use Regenerate.', 'everyalt' ); ?></p>
		<?php endif; ?>
		<?php if ( empty( $images_with_alt ) ) : ?>
			<p><?php esc_html_e( 'No images with alt text found.', 'everyalt' ); ?></p>
		<?php else : ?>
			<ul class="everyalt-review-grid" id="everyalt-review-grid">
				<?php foreach ( $images_with_alt as $image ) :
					$aid = (int) $image->ID;
					$alt = get_post_meta( $aid, '_wp_attachment_image_alt', true );
					$edit_link = get_edit_post_link( $aid, 'raw' );
					?>
					<li class="everyalt-review-item" data-media-id="<?php echo $aid; ?>">
						<span class="everyalt-review-thumb"><?php echo wp_get_attachment_image( $aid, 'thumbnail' ); ?></span>
						<?php if ( $edit_link ) : ?>
							<a href="<?php echo esc_url( $edit_link ); ?>" class="everyalt-review-edit-link"><?php esc_html_e( 'Edit', 'everyalt' ); ?></a>
						<?php endif; ?>
						<textarea class="everyalt-review-alt-field" rows="3" data-media-id="<?php echo $aid; ?>"><?php echo esc_textarea( $alt ); ?></textarea>
						<div class="everyalt-review-actions">
							<button type="button" class="button everyalt-review-save" data-media-id="<?php echo $aid; ?>"><?php esc_html_e( 'Save', 'everyalt' ); ?></button>
							<button type="button" class="button everyalt-review-regenerate" data-media-id="<?php echo $aid; ?>"><?php esc_html_e( 'Regenerate', 'everyalt' ); ?></button>
						</div>
						<span class="everyalt-review-status" aria-live="polite"></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $active === 'logs' ) : ?>
	<div class="everyalt-logs-wrap">
		<h2><?php esc_html_e( 'Generation Log', 'everyalt' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Last 100 alt text generation attempts (successes and failures).', 'everyalt' ); ?></p>
		<p>
			<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'logs', 'export_csv' => '1', '_wpnonce' => wp_create_nonce( 'everyalt_export_logs' ) ), $base_url ) ); ?>" class="button">
				<?php esc_html_e( 'Export as CSV', 'everyalt' ); ?>
			</a>
		</p>
		<?php if ( ! empty( $generation_log ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:140px"><?php esc_html_e( 'Time', 'everyalt' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Attachment', 'everyalt' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Status', 'everyalt' ); ?></th>
						<th><?php esc_html_e( 'Message / Alt text', 'everyalt' ); ?></th>
						<th style="width:140px"><?php esc_html_e( 'Usage', 'everyalt' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Cost', 'everyalt' ); ?></th>
						<th><?php esc_html_e( 'Details', 'everyalt' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $generation_log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ); ?></td>
							<td>
								<?php
								$aid = (int) $entry['attachment_id'];
								if ( $aid ) {
									$edit_link = get_edit_post_link( $aid );
									if ( $edit_link ) {
										echo '<a href="' . esc_url( $edit_link ) . '">#' . (int) $aid . '</a>';
									} else {
										echo '#' . (int) $aid;
									}
								} else {
									echo '—';
								}
								?>
							</td>
							<td>
								<?php
								$status = isset( $entry['status'] ) ? $entry['status'] : '';
								if ( $status === 'success' ) {
									echo '<span style="color:green">' . esc_html__( 'Success', 'everyalt' ) . '</span>';
								} else {
									echo '<span style="color:#b32d2e">' . esc_html__( 'Error', 'everyalt' ) . '</span>';
								}
								?>
							</td>
							<td><?php echo esc_html( isset( $entry['message'] ) ? $entry['message'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $entry['usage'] ) ? $entry['usage'] : '—' ); ?></td>
							<td><?php echo esc_html( isset( $entry['cost'] ) ? $entry['cost'] : '—' ); ?></td>
							<td class="everyalt-log-detail">
								<div class="everyalt-log-detail-inner"><?php echo esc_html( isset( $entry['detail'] ) ? $entry['detail'] : '' ); ?></div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No log entries yet. Generate alt text from the Bulk tab or when uploading images to populate this log.', 'everyalt' ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

</div><!-- .wrap -->
