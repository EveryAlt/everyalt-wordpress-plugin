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
	</h2>

<?php if ( isset( $_GET['updated'] ) && $_GET['updated'] === '1' ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'everyalt' ); ?></p></div>
<?php endif; ?>

<?php if ( $active === 'settings' ) : ?>
	<div class="everyalt-settings-wrap">
		<h2><?php esc_html_e( 'Settings', 'everyalt' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'everyalt_save_settings', 'everyalt_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="every_alt_secret"><?php esc_html_e( 'API Key', 'everyalt' ); ?></label></th>
					<td>
						<input type="password" name="every_alt_secret" id="every_alt_secret" value="<?php echo esc_attr( get_option( 'every_alt_secret', '' ) ); ?>" class="regular-text" autocomplete="off">
						<p class="description"><?php esc_html_e( 'Your EveryAlt API key from everyalt.com', 'everyalt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-generate on upload', 'everyalt' ); ?></th>
					<td>
						<label><input type="checkbox" name="every_alt_auto" value="1" <?php checked( get_option( 'every_alt_auto', 0 ), 1 ); ?>><?php esc_html_e( 'Automatically generate alt text when images are uploaded', 'everyalt' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Full text', 'everyalt' ); ?></th>
					<td>
						<label><input type="checkbox" name="every_alt_fulltext" value="1" <?php checked( get_option( 'every_alt_fulltext', 0 ), 1 ); ?>><?php esc_html_e( 'Use full descriptive text for alt (if supported by your plan)', 'everyalt' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="every_alt_httpuser"><?php esc_html_e( 'HTTP Auth Username', 'everyalt' ); ?></label></th>
					<td>
						<input type="text" name="every_alt_httpuser" id="every_alt_httpuser" value="<?php echo esc_attr( get_option( 'every_alt_httpuser', '' ) ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Optional: if your site is behind HTTP authentication', 'everyalt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="every_alt_httpassword"><?php esc_html_e( 'HTTP Auth Password', 'everyalt' ); ?></label></th>
					<td>
						<input type="password" name="every_alt_httpassword" id="every_alt_httpassword" value="<?php echo esc_attr( get_option( 'every_alt_httpassword', '' ) ); ?>" class="regular-text" autocomplete="off">
					</td>
				</tr>
			</table>
			<p class="submit">
				<?php submit_button( __( 'Save Settings', 'everyalt' ), 'primary', 'submit', false ); ?>
			</p>
		</form>
		<?php if ( ! empty( $secret_key ) && isset( $tokens ) ) : ?>
			<p>
				<?php
				printf(
					/* translators: 1: used tokens, 2: total tokens */
					esc_html__( 'Usage: %1$s of %2$s image quota used.', 'everyalt' ),
					(int) $used_tokens,
					(int) $tokens
				);
				?>
				<a href="https://everyalt.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get More', 'everyalt' ); ?></a>
			</p>
		<?php endif; ?>
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
		<?php elseif ( ! empty( $secret_key ) ) : ?>
			<p>
				<?php
				$progress_val = isset( $progress ) ? (int) $progress : 0;
				$used_val = isset( $used_tokens ) ? (int) $used_tokens : 0;
				$total_val = isset( $tokens ) ? (int) $tokens : 0;
				printf(
					/* translators: 1: progress percent, 2: used tokens, 3: total tokens */
					esc_html__( '%1$s%% - You have used %2$s of %3$s image quota.', 'everyalt' ),
					$progress_val,
					$used_val,
					$total_val
				);
				?>
				<a href="https://everyalt.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get More', 'everyalt' ); ?></a>
			</p>
			<?php
			$count = count( $bulk_image_ids );
			if ( $count > 0 ) :
				$insufficient = isset( $tokens, $used_tokens ) && ( (int) $tokens - (int) $used_tokens ) < $count;
				?>
				<p>
					<button type="button" id="everyalt-bulk-run" class="button button-primary" data-ids="<?php echo esc_attr( wp_json_encode( array_values( $bulk_image_ids ) ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'every_alt_nonce' ) ); ?>" data-rest="<?php echo esc_attr( rest_url( 'everyalt-api/v1/bulk_generate_alt' ) ); ?>" <?php echo $insufficient ? ' disabled' : ''; ?>>
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
				<?php if ( $insufficient ) : ?>
					<p class="description"><?php esc_html_e( 'Your available tokens are not enough to complete the process.', 'everyalt' ); ?> <a href="https://everyalt.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get More', 'everyalt' ); ?></a></p>
				<?php endif; ?>
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
			<p><?php esc_html_e( 'Please enter your API key in the Settings tab first.', 'everyalt' ); ?></p>
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
