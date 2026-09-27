<?php
/**
 * Admin options display - simple WordPress UI (no Vue/React).
 *
 * @package EveryAlt
 * @subpackage EveryAlt/admin/partials
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
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'bulk_title', $base_url ) ); ?>" class="nav-tab <?php echo $active === 'bulk_title' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Bulk Image Title Generator', 'everyalt' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'review_title', $base_url ) ); ?>" class="nav-tab <?php echo $active === 'review_title' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Review Image Titles', 'everyalt' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'logs', $base_url ) ); ?>" class="nav-tab <?php echo $active === 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Logs', 'everyalt' ); ?></a>
	</h2>

	<div class="notice notice-info everyalt-intro-notice" style="margin-top:1em;">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: opening link to everyalt.com, 2: closing link tag, 3: opening link to hdc.net, 4: closing link tag */
					__( '%1$sEveryAlt%2$s is a free, open-source project created by %3$sHDC%4$s, a web dev firm for high-stakes projects and AI builds.', 'everyalt' ),
					'<a href="' . esc_url( 'https://everyalt.com' ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>',
					'<a href="' . esc_url( 'https://hdc.net' ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				),
				array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) )
			);
			?>
		</p>
	</div>

<?php if ( isset( $_GET['updated'] ) && $_GET['updated'] === '1' ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'everyalt' ); ?></p></div>
<?php endif; ?>
<?php if ( isset( $_GET['error'] ) && $_GET['error'] === 'everyalt_invalid_key' ) : ?>
	<?php
	$everyalt_bad_provider = Every_Alt_Providers::provider( isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : 'openai' );
	?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sprintf( /* translators: %s: provider name, e.g. OpenAI */ __( 'The %s API key you entered could not be validated. Please check the key and try again. No settings were saved.', 'everyalt' ), $everyalt_bad_provider ? $everyalt_bad_provider['label'] : 'API' ) ); ?></p></div>
<?php endif; ?>

<?php if ( $active === 'settings' ) : ?>
	<div class="everyalt-settings-wrap">
		<h2><?php esc_html_e( 'Settings', 'everyalt' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'everyalt_save_settings', 'everyalt_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<?php
				$everyalt_models         = Every_Alt_Providers::models();
				$everyalt_providers      = Every_Alt_Providers::providers();
				$everyalt_selected_model = Every_Alt_Providers::selected_model();
				$everyalt_link           = function ( $url, $text ) {
					return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a>';
				};
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'AI model', 'everyalt' ); ?></th>
					<td>
						<fieldset class="everyalt-model-list">
							<legend class="screen-reader-text"><?php esc_html_e( 'AI model', 'everyalt' ); ?></legend>
							<?php foreach ( $everyalt_models as $model_slug => $model ) :
								$provider = $everyalt_providers[ $model['provider'] ];
								?>
								<label class="everyalt-model-option">
									<input type="radio" name="<?php echo esc_attr( Every_Alt_Providers::MODEL_OPTION ); ?>" value="<?php echo esc_attr( $model_slug ); ?>" data-provider="<?php echo esc_attr( $model['provider'] ); ?>" <?php checked( $everyalt_selected_model['slug'], $model_slug ); ?>>
									<strong><?php echo esc_html( $model['label'] ); ?></strong>
									<span class="everyalt-model-provider"><?php echo esc_html( $provider['label'] ); ?></span>
									<span class="everyalt-model-price">
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: input price in USD, 2: output price in USD */
												__( '$%1$s input · $%2$s output per 1M tokens', 'everyalt' ),
												Every_Alt_Providers::format_price( $model['input_price'] ),
												Every_Alt_Providers::format_price( $model['output_price'] )
											)
										);
										?>
									</span>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'All models read the image and write alt text and titles the same way. Prices are each provider’s published rates as of September 2026 and may change. You pay the provider directly; EveryAlt never bills you. The actual cost of each image is recorded on the Logs tab.', 'everyalt' ); ?>
						</p>
						<p class="description">
							<?php
							$everyalt_pricing_links = array();
							foreach ( $everyalt_providers as $provider ) {
								/* translators: %s: provider name, e.g. OpenAI */
								$everyalt_pricing_links[] = $everyalt_link( $provider['pricing_url'], sprintf( __( '%s pricing', 'everyalt' ), $provider['label'] ) );
							}
							echo wp_kses_post( implode( ' · ', $everyalt_pricing_links ) );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'API key', 'everyalt' ); ?></th>
					<td>
						<?php foreach ( $everyalt_providers as $provider_slug => $provider ) :
							$field     = $provider['key_option'];
							$has_saved = Every_Alt_Providers::get_key( $provider_slug ) !== '';
							?>
							<div class="everyalt-provider-key" data-provider="<?php echo esc_attr( $provider_slug ); ?>">
								<h3 class="everyalt-provider-key-title">
									<label for="<?php echo esc_attr( $field ); ?>">
										<?php
										/* translators: %s: provider name, e.g. OpenAI */
										echo esc_html( sprintf( __( '%s API key', 'everyalt' ), $provider['label'] ) );
										?>
									</label>
									<span class="everyalt-key-status <?php echo $has_saved ? 'is-saved' : 'is-missing'; ?>">
										<?php echo $has_saved ? esc_html__( 'Key saved', 'everyalt' ) : esc_html__( 'No key saved', 'everyalt' ); ?>
									</span>
								</h3>
								<span class="everyalt-key-row">
									<input type="password" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $has_saved ? esc_attr__( 'Leave blank to keep existing key', 'everyalt' ) : esc_attr__( 'Paste your API key', 'everyalt' ); ?>">
									<button type="button" class="button everyalt-validate-key" data-provider="<?php echo esc_attr( $provider_slug ); ?>"><?php esc_html_e( 'Validate key', 'everyalt' ); ?></button>
								</span>
								<p class="everyalt-validate-result" aria-live="polite" style="display:none; margin-top:0.5em;"></p>

								<?php if ( $provider_slug === 'openai' ) : ?>
									<ol class="everyalt-key-steps">
										<li><?php echo wp_kses_post( sprintf( /* translators: %s: link to OpenAI API keys page */ __( 'Sign in at %s.', 'everyalt' ), $everyalt_link( $provider['key_url'], 'platform.openai.com/api-keys' ) ) ); ?></li>
										<li><?php esc_html_e( 'Click “Create new secret key”, give it a name (e.g. “EveryAlt”), and copy the key. It is only shown once.', 'everyalt' ); ?></li>
										<li><?php esc_html_e( 'Add a payment method or prepaid credits in your OpenAI billing settings; generation fails until your account has credit.', 'everyalt' ); ?></li>
									</ol>
									<p class="description"><?php echo wp_kses_post( sprintf( /* translators: %s: link to OpenAI data usage policy */ __( 'OpenAI does not use API data to train its models by default. See %s.', 'everyalt' ), $everyalt_link( $provider['privacy_urls']['OpenAI API data usage'], __( 'OpenAI API data usage', 'everyalt' ) ) ) ); ?></p>
								<?php elseif ( $provider_slug === 'gemini' ) : ?>
									<ol class="everyalt-key-steps">
										<li><?php echo wp_kses_post( sprintf( /* translators: %s: link to Google AI Studio API keys page */ __( 'Sign in to Google AI Studio at %s.', 'everyalt' ), $everyalt_link( $provider['key_url'], 'aistudio.google.com/apikey' ) ) ); ?></li>
										<li><?php esc_html_e( 'Click “Create API key”, choose or create a Google Cloud project, and copy the key.', 'everyalt' ); ?></li>
										<li><?php esc_html_e( 'Optional: set up billing on the project to move from the free tier to the paid tier.', 'everyalt' ); ?></li>
									</ol>
									<p class="description"><?php echo wp_kses_post( sprintf( /* translators: %s: link to Gemini API terms */ __( 'Gemini has a free tier, but Google may use free-tier prompts and images to improve its products. On the paid tier (billing enabled), your content is not used that way. See the %s.', 'everyalt' ), $everyalt_link( $provider['privacy_urls']['Gemini API terms'], __( 'Gemini API terms', 'everyalt' ) ) ) ); ?></p>
								<?php elseif ( $provider_slug === 'deepinfra' ) : ?>
									<ol class="everyalt-key-steps">
										<li><?php echo wp_kses_post( sprintf( /* translators: %s: link to DeepInfra API keys page */ __( 'Sign in or create an account at %s.', 'everyalt' ), $everyalt_link( $provider['key_url'], 'deepinfra.com/dash/api_keys' ) ) ); ?></li>
										<li><?php esc_html_e( 'Click “New API key”, name it (e.g. “EveryAlt”), and copy the key.', 'everyalt' ); ?></li>
										<li><?php esc_html_e( 'Add a payment method or credits in your DeepInfra billing settings. One DeepInfra key works for both DeepSeek V4.1 Flash and GLM-5.3-Flash.', 'everyalt' ); ?></li>
									</ol>
									<div class="everyalt-privacy-note">
										<p>
											<strong><?php esc_html_e( 'Your images stay private.', 'everyalt' ); ?></strong>
											<?php esc_html_e( 'DeepInfra runs these models on its own infrastructure in data centers in the US and Canada, with a zero data retention policy: your images and the generated text are processed in memory and not stored, the content of requests is not logged, and your data is never used to train models.', 'everyalt' ); ?>
										</p>
										<p>
											<?php
											$everyalt_privacy_links = array(
												$everyalt_link( $provider['privacy_urls']['Data privacy'], __( 'DeepInfra data privacy', 'everyalt' ) ),
												$everyalt_link( $provider['privacy_urls']['Privacy policy'], __( 'Privacy policy', 'everyalt' ) ),
												$everyalt_link( $provider['privacy_urls']['Trust center'], __( 'Trust center (SOC 2, ISO 27001)', 'everyalt' ) ),
											);
											echo wp_kses_post( implode( ' · ', $everyalt_privacy_links ) );
											?>
										</p>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Keys are stored encrypted in your WordPress database. The image is sent to the provider as base64, so generation works on localhost and behind HTTP auth.', 'everyalt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-generate on upload', 'everyalt' ); ?></th>
					<td>
						<label><input type="checkbox" name="every_alt_auto" value="1" <?php checked( get_option( 'every_alt_do_auto_default' ) || get_option( 'every_alt_auto', 0 ) ); ?>><?php esc_html_e( 'Automatically generate alt text when images are uploaded', 'everyalt' ); ?></label>
						<br>
						<label><input type="checkbox" name="every_alt_auto_title" value="1" <?php checked( get_option( 'every_alt_auto_title', 0 ), 1 ); ?>><?php esc_html_e( 'Automatically generate image titles when images are uploaded', 'everyalt' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="every_alt_max_completion_tokens"><?php esc_html_e( 'Max completion tokens', 'everyalt' ); ?></label></th>
					<td>
						<input type="number" name="every_alt_max_completion_tokens" id="every_alt_max_completion_tokens" value="<?php echo esc_attr( get_option( 'every_alt_max_completion_tokens', '1024' ) ); ?>" min="1" max="128000" step="1" class="small-text">
						<p class="description"><?php esc_html_e( 'Maximum tokens the model can use for its response, including any reasoning. Default 1024. Leave empty to use default.', 'everyalt' ); ?></p>
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
				<tr>
					<th scope="row"><label for="every_alt_title_prompt"><?php esc_html_e( 'Image title prompt', 'everyalt' ); ?></label></th>
					<td>
						<?php
						$default_title_prompt  = Every_Alt_OpenAI::DEFAULT_TITLE_PROMPT;
						$current_title_prompt  = get_option( 'every_alt_title_prompt', '' );
						$editable_title_prompt = $current_title_prompt !== '' ? $current_title_prompt : $default_title_prompt;
						?>
						<textarea name="every_alt_title_prompt" id="every_alt_title_prompt" class="large-text" rows="4"><?php echo esc_textarea( $editable_title_prompt ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Instruction sent to the AI when generating image titles. Titles are usually short (a few words). The model should return only the title, no extra wording.', 'everyalt' ); ?></p>
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
	<div class="everyalt-bulk-wrap">
		<h2><?php esc_html_e( 'Bulk Alt Text Generator', 'everyalt' ); ?></h2>
		<p class="description"><?php echo wp_kses_post( sprintf( __( 'This page finds all images in your media library that do not currently have alt text and lets you generate new alt text with EveryAlt quickly. To see existing images that already have alt text, go to the <a href="%s">Review Alt Text</a> tab.', 'everyalt' ), esc_url( add_query_arg( 'tab', 'review', $base_url ) ) ) ); ?></p>
		<?php if ( ! $has_api_key ) : ?>
			<p><?php esc_html_e( 'Please add an API key for the selected AI model in the Settings tab first.', 'everyalt' ); ?></p>
		<?php elseif ( empty( $image_page['images'] ) ) : ?>
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
			<p class="everyalt-page-count"><?php echo esc_html( sprintf( /* translators: %s: number of images */ _n( '%s image', '%s images', $image_page['total'], 'everyalt' ), number_format_i18n( $image_page['total'] ) ) ); ?></p>
			<ul class="everyalt-bulk-grid" id="everyalt-bulk-grid">
				<?php foreach ( $image_page['images'] as $image ) :
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
			<?php if ( $pagination_links ) : ?>
				<div class="everyalt-pagination"><?php echo wp_kses_post( $pagination_links ); ?></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $active === 'review' ) : ?>
	<div class="everyalt-review-wrap">
		<h2><?php esc_html_e( 'Review Alt Text', 'everyalt' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Images that already have alt text. Edit and save, or regenerate with EveryAlt.', 'everyalt' ); ?></p>
		<?php if ( ! $has_api_key ) : ?>
			<p><?php esc_html_e( 'Please add an API key for the selected AI model in the Settings tab to use Regenerate.', 'everyalt' ); ?></p>
		<?php endif; ?>
		<?php if ( empty( $image_page['images'] ) ) : ?>
			<p><?php esc_html_e( 'No images with alt text found.', 'everyalt' ); ?></p>
		<?php else : ?>
			<p class="everyalt-page-count"><?php echo esc_html( sprintf( /* translators: %s: number of images */ _n( '%s image', '%s images', $image_page['total'], 'everyalt' ), number_format_i18n( $image_page['total'] ) ) ); ?></p>
			<ul class="everyalt-review-grid" id="everyalt-review-grid">
				<?php foreach ( $image_page['images'] as $image ) :
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
			<?php if ( $pagination_links ) : ?>
				<div class="everyalt-pagination"><?php echo wp_kses_post( $pagination_links ); ?></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $active === 'bulk_title' ) : ?>
	<div class="everyalt-bulk-wrap">
		<h2><?php esc_html_e( 'Bulk Image Title Generator', 'everyalt' ); ?></h2>
		<p class="description"><?php echo wp_kses_post( sprintf( __( 'This page finds images whose title is still the raw upload filename (e.g. "IMG_1234") and lets you generate descriptive titles with EveryAlt quickly. To see images that already have a custom title, go to the <a href="%s">Review Image Titles</a> tab.', 'everyalt' ), esc_url( add_query_arg( 'tab', 'review_title', $base_url ) ) ) ); ?></p>
		<?php if ( ! $has_api_key ) : ?>
			<p><?php esc_html_e( 'Please add an API key for the selected AI model in the Settings tab first.', 'everyalt' ); ?></p>
		<?php elseif ( empty( $image_page['images'] ) ) : ?>
			<p><?php esc_html_e( 'No images needing a title found.', 'everyalt' ); ?></p>
		<?php else : ?>
			<p class="everyalt-bulk-actions">
				<button type="button" id="everyalt-bulk-title-select-all" class="button"><?php esc_html_e( 'Select all', 'everyalt' ); ?></button>
				<button type="button" id="everyalt-bulk-title-select-none" class="button"><?php esc_html_e( 'Select none', 'everyalt' ); ?></button>
				<button type="button" id="everyalt-bulk-title-run" class="button button-primary"><?php esc_html_e( 'Generate titles for selected', 'everyalt' ); ?></button>
			</p>
			<div id="everyalt-bulk-title-progress" class="everyalt-bulk-progress hidden">
				<p class="everyalt-bulk-progress-status"><strong><?php esc_html_e( 'Processing…', 'everyalt' ); ?></strong> <span id="everyalt-bulk-title-progress-text">0 / 0</span></p>
				<div class="everyalt-bulk-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"><span id="everyalt-bulk-title-progress-fill"></span></div>
				<ul id="everyalt-bulk-title-progress-log" class="everyalt-bulk-progress-log" aria-live="polite"></ul>
			</div>
			<p class="everyalt-page-count"><?php echo esc_html( sprintf( /* translators: %s: number of images */ _n( '%s image', '%s images', $image_page['total'], 'everyalt' ), number_format_i18n( $image_page['total'] ) ) ); ?></p>
			<ul class="everyalt-bulk-grid" id="everyalt-bulk-title-grid">
				<?php foreach ( $image_page['images'] as $image ) :
					$aid = (int) $image->ID;
					?>
					<li class="everyalt-bulk-title-item" data-media-id="<?php echo $aid; ?>">
						<label>
							<input type="checkbox" class="everyalt-bulk-title-checkbox" value="<?php echo $aid; ?>">
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
			<?php if ( $pagination_links ) : ?>
				<div class="everyalt-pagination"><?php echo wp_kses_post( $pagination_links ); ?></div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( $active === 'review_title' ) : ?>
	<div class="everyalt-review-wrap">
		<h2><?php esc_html_e( 'Review Image Titles', 'everyalt' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Images that already have a custom title. Edit and save, or regenerate with EveryAlt.', 'everyalt' ); ?></p>
		<?php if ( ! $has_api_key ) : ?>
			<p><?php esc_html_e( 'Please add an API key for the selected AI model in the Settings tab to use Regenerate.', 'everyalt' ); ?></p>
		<?php endif; ?>
		<?php if ( empty( $image_page['images'] ) ) : ?>
			<p><?php esc_html_e( 'No images with a custom title found.', 'everyalt' ); ?></p>
		<?php else : ?>
			<p class="everyalt-page-count"><?php echo esc_html( sprintf( /* translators: %s: number of images */ _n( '%s image', '%s images', $image_page['total'], 'everyalt' ), number_format_i18n( $image_page['total'] ) ) ); ?></p>
			<ul class="everyalt-review-grid" id="everyalt-review-title-grid">
				<?php foreach ( $image_page['images'] as $image ) :
					$aid = (int) $image->ID;
					$title = get_the_title( $aid );
					$edit_link = get_edit_post_link( $aid, 'raw' );
					?>
					<li class="everyalt-review-title-item" data-media-id="<?php echo $aid; ?>">
						<span class="everyalt-review-thumb"><?php echo wp_get_attachment_image( $aid, 'thumbnail' ); ?></span>
						<?php if ( $edit_link ) : ?>
							<a href="<?php echo esc_url( $edit_link ); ?>" class="everyalt-review-edit-link"><?php esc_html_e( 'Edit', 'everyalt' ); ?></a>
						<?php endif; ?>
						<textarea class="everyalt-review-title-field" rows="2" data-media-id="<?php echo $aid; ?>"><?php echo esc_textarea( $title ); ?></textarea>
						<div class="everyalt-review-actions">
							<button type="button" class="button everyalt-review-title-save" data-media-id="<?php echo $aid; ?>"><?php esc_html_e( 'Save', 'everyalt' ); ?></button>
							<button type="button" class="button everyalt-review-title-regenerate" data-media-id="<?php echo $aid; ?>"><?php esc_html_e( 'Regenerate', 'everyalt' ); ?></button>
						</div>
						<span class="everyalt-review-status" aria-live="polite"></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $pagination_links ) : ?>
				<div class="everyalt-pagination"><?php echo wp_kses_post( $pagination_links ); ?></div>
			<?php endif; ?>
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
						<th style="width:160px"><?php esc_html_e( 'Model', 'everyalt' ); ?></th>
						<th><?php esc_html_e( 'Message / Alt text', 'everyalt' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Cost', 'everyalt' ); ?></th>
						<th><?php esc_html_e( 'Details', 'everyalt' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $generation_log as $entry ) : ?>
						<?php
						$status   = isset( $entry['status'] ) ? $entry['status'] : '';
						$detail   = isset( $entry['detail'] ) ? $entry['detail'] : '';
						$usage    = isset( $entry['usage'] ) ? $entry['usage'] : '';
						$details_display = $detail;
						if ( $status === 'success' && $usage !== '' ) {
							$details_display = ( $details_display !== '' ? $usage . "\n\n" . $details_display : $usage );
						}
						?>
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
								if ( $status === 'success' ) {
									echo '<span style="color:green">' . esc_html__( 'Success', 'everyalt' ) . '</span>';
								} else {
									echo '<span style="color:#b32d2e">' . esc_html__( 'Error', 'everyalt' ) . '</span>';
								}
								?>
							</td>
							<td><?php echo esc_html( isset( $entry['model'] ) ? $entry['model'] : '—' ); ?></td>
							<td><?php echo esc_html( isset( $entry['message'] ) ? $entry['message'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $entry['cost'] ) ? $entry['cost'] : '—' ); ?></td>
							<td class="everyalt-log-detail">
								<div class="everyalt-log-detail-inner"><?php echo esc_html( $details_display ); ?></div>
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
