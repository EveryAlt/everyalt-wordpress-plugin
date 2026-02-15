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
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" class="nav-tab <?php echo $active === 'history' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'History', 'everyalt' ); ?></a>
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
						<input type="password" name="every_alt_openai_key" id="every_alt_openai_key" value="" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Leave blank to keep existing key', 'everyalt' ); ?>">
						<p class="description"><?php esc_html_e( 'Your OpenAI API key. Alt text is generated via OpenAI (image is sent as base64, so it works on localhost and behind HTTP auth). Stored encrypted.', 'everyalt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-generate on upload', 'everyalt' ); ?></th>
					<td>
						<label><input type="checkbox" name="every_alt_auto" value="1" <?php checked( get_option( 'every_alt_auto', 0 ), 1 ); ?>><?php esc_html_e( 'Automatically generate alt text when images are uploaded', 'everyalt' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="every_alt_vision_prompt"><?php esc_html_e( 'Alt text prompt', 'everyalt' ); ?></label></th>
					<td>
						<?php
						$current_prompt = get_option( 'every_alt_vision_prompt', '' );
						$placeholder    = 'Describe this image in one short, clear sentence suitable for HTML alt text. Do not start with "This image shows" or similar. Output only the alt text, nothing else.';
						?>
						<textarea name="every_alt_vision_prompt" id="every_alt_vision_prompt" class="large-text" rows="4" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $current_prompt ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Instruction sent to the AI with each image. Leave blank to use the default prompt. The model should return only the alt text, no extra wording.', 'everyalt' ); ?></p>
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
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php elseif ( $has_openai_key ) : ?>
			<?php
			$count = count( $bulk_image_ids );
			if ( $count > 0 ) :
				?>
				<p>
					<button type="button" id="everyalt-bulk-run" class="button button-primary" data-ids="<?php echo esc_attr( wp_json_encode( array_values( $bulk_image_ids ) ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'every_alt_nonce' ) ); ?>" data-rest="<?php echo esc_attr( rest_url( 'everyalt-api/v1/bulk_generate_alt' ) ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of images */
								_n( 'Automatically Add Alt Text for %d Image', 'Automatically Add Alt Text for %d Images', $count, 'everyalt' ),
								$count
							)
						);
						?>
					</button>
				</p>
				<div id="everyalt-bulk-progress" class="everyalt-progress hidden">
					<p><strong><?php esc_html_e( 'Processing… Don\'t close this window.', 'everyalt' ); ?></strong></p>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No images without alt text found.', 'everyalt' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $history['images'] ) ) : ?>
				<h2><?php esc_html_e( 'Alt generated so far', 'everyalt' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Image', 'everyalt' ); ?></th>
							<th><?php esc_html_e( 'Alt text', 'everyalt' ); ?></th>
							<th style="width:100px"><?php esc_html_e( 'Actions', 'everyalt' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $history['images'] as $item ) : ?>
							<tr data-media-id="<?php echo (int) $item['media_id']; ?>" data-log-id="<?php echo (int) $item['id']; ?>">
								<td>
									<a href="<?php echo esc_url( $item['media_link'] ); ?>"><?php echo wp_get_attachment_image( $item['media_id'], 'thumbnail' ); ?></a>
								</td>
								<td>
									<textarea class="everyalt-alt-field large-text" rows="3" data-media-id="<?php echo (int) $item['media_id']; ?>" data-log-id="<?php echo (int) $item['id']; ?>"><?php echo esc_textarea( $item['alt_text'] ); ?></textarea>
								</td>
								<td>
									<button type="button" class="button everyalt-save-alt" data-nonce="<?php echo esc_attr( wp_create_nonce( 'every_alt_nonce' ) ); ?>"><?php esc_html_e( 'Save', 'everyalt' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( ! empty( $history['pagination'] ) ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages"><?php echo $history['pagination']; ?></div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Please enter your OpenAI API key in the Settings tab first.', 'everyalt' ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $active === 'logs' ) : ?>
	<div class="everyalt-logs-wrap">
		<h2><?php esc_html_e( 'Generation Log', 'everyalt' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Last 100 alt text generation attempts (successes and failures).', 'everyalt' ); ?></p>
		<?php if ( ! empty( $generation_log ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:140px"><?php esc_html_e( 'Time', 'everyalt' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Attachment', 'everyalt' ); ?></th>
						<th style="width:80px"><?php esc_html_e( 'Status', 'everyalt' ); ?></th>
						<th><?php esc_html_e( 'Message / Alt text', 'everyalt' ); ?></th>
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
							<td class="everyalt-log-detail" style="max-width:320px; overflow-wrap: break-word;"><?php echo esc_html( isset( $entry['detail'] ) ? $entry['detail'] : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No log entries yet. Generate alt text from the Bulk tab or when uploading images to populate this log.', 'everyalt' ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $active === 'history' ) : ?>
	<div class="everyalt-history-wrap">
		<h2><?php esc_html_e( 'History', 'everyalt' ); ?></h2>
		<?php if ( ! empty( $images['images'] ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Image', 'everyalt' ); ?></th>
						<th><?php esc_html_e( 'Alt text', 'everyalt' ); ?></th>
						<th style="width:100px"><?php esc_html_e( 'Actions', 'everyalt' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $images['images'] as $item ) : ?>
						<tr data-media-id="<?php echo (int) $item['media_id']; ?>" data-log-id="<?php echo (int) $item['id']; ?>">
							<td>
								<a href="<?php echo esc_url( $item['media_link'] ); ?>"><?php echo wp_get_attachment_image( $item['media_id'], 'thumbnail' ); ?></a>
							</td>
							<td>
								<textarea class="everyalt-alt-field large-text" rows="3" data-media-id="<?php echo (int) $item['media_id']; ?>" data-log-id="<?php echo (int) $item['id']; ?>"><?php echo esc_textarea( $item['alt_text'] ); ?></textarea>
							</td>
							<td>
								<button type="button" class="button everyalt-save-alt" data-nonce="<?php echo esc_attr( wp_create_nonce( 'every_alt_nonce' ) ); ?>"><?php esc_html_e( 'Save', 'everyalt' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( ! empty( $images['pagination'] ) ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages"><?php echo $images['pagination']; ?></div>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No alt text generated yet.', 'everyalt' ); ?></p>
		<?php endif; ?>
	</div>
<?php endif; ?>
</div><!-- .wrap -->
