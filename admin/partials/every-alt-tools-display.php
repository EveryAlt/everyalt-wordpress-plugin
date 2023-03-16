<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://hdc.dev
 * @since      0.0.1
 *
 * @package    Every_Alt
 * @subpackage Every_Alt/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap" id="every-alt-tools-page">

	<div class="every_alt_tools_header">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php if($secret_key && !$error):?>
		<p style="margin-bottom:0.25rem">
			<span id="every_alt_counter_percent"><?php echo $progress ?></span>% - <?php _e( 'You have used', 'every-alt' )?> <span id="every_alt_counter_total"><?php echo $used_tokens ?></span> <?php _e( 'of your', 'every-alt' )?> <span id="every_alt_counter_avilable"><?php echo $tokens ?></span>-<?php _e( 'image quota', 'every-alt' )?>
			<a href="https://everyalt.com" target="_blank" rel="noopener noreferrer"><?php _e( 'Get More', 'every-alt' )?></a>
		</p>
		<div class="every_alt_counter_bar">
			<div class="every_alt_counter_bar_progress" style="width:<?php echo $progress ?>%"></div>
		</div>
		<?php if(count($images_without_alt)): ?>
			<div id="every_alt_start_process_button">
				<button 
				data-media="<?php echo json_encode (wp_list_pluck($images_without_alt,'ID'))?>"
				data-nonce="<?php echo wp_create_nonce('every_alt_nonce'); ?>"
				data-url="<?php echo get_admin_url(); ?>"
				id="every_alt_bulk_alt_images" 
				class="button button-primary" 
				type="button">
				<?php _e( 'Automatically Add Alt Text for', 'every-alt' )?> <?php echo count($images_without_alt) ?> <?php echo count($images_without_alt) > 1 ? __( 'Images', 'every-alt' ):__( 'Image', 'every-alt' ) ?>
				</button>
				<?php if(($tokens - $used_tokens) < count($images_without_alt)):?>
					<p class="error"><strong><?php _e( 'ATTENTION', 'every-alt' )?></strong>: <?php _e( 'Your available tokens are not enough to complete the process.', 'every-alt' )?> - <a href="https://everyalt.com" target="_blank" rel="noopener noreferrer"><?php _e( 'Get More', 'every-alt' )?></a></p>
				<?php endif?>
			</div>
			<div id="every_alt_process_spinner" class="hidden">
				<img src="<?php echo esc_url( includes_url() . 'js/tinymce/skins/lightgray/img//loader.gif' ); ?>" />
				<p style="margin:0; margin-left:0.25rem"><strong><?php _e( 'Processing... Don\'t close this window', 'every-alt' )?></strong></p>
			</div>
			<div id="every_alt_progress_bar" class="hidden">
				<div id="every_altprogress_bar_progress"></div>
			</div>
		<?php endif ?>
	</div>

	<?php if(count($images['images'])):?>
		
		<div id="every_alt_images_generated">
			<h3><?php _e( 'Alt Generated so Far', 'every-alt' )?> </h3>
		
			<?php 
			$i = 0;
			
			foreach ($images['images'] as $media):?>
				<div class="every_alt_media_wrapper">
					<?php
					$thumbnail = wp_get_attachment_image( $media->media_id, 'thumbnail' );
					$admin_link = get_edit_post_link( $media->media_id );
					$alt_text = get_post_meta($media->media_id, '_wp_attachment_image_alt', true);
					?>
					<div>
						<a href="<?php echo get_edit_post_link($media->media_id)?>">
							<?php echo $thumbnail ?>
						</a>
					</div>
					<div style="width:100%">
						<textarea
						class="every_alt_textarea"
						data-old="<?php echo $alt_text ?>" 
						data-media="<?php echo $media->media_id ?>" 
						data-log-id="<?php echo $media->id ?>" 
						data-index="<?php echo $i ?>"
						data-nonce="<?php echo wp_create_nonce('every_alt_nonce'); ?>"
						data-url="<?php echo get_admin_url(); ?>"
						id="every_alt_textarea_<?php echo $i ?>" rows="4"><?php echo $alt_text ?></textarea>
					</div>
					
					
				</div>

			<?php 
			$i++; 
			endforeach; 
			?>
			<div id="every_alt_pagination">
				<div class="pagination" >
					<?php echo $images['pagination']?>
				</div>
			</div>			
		</div>
	<?php endif ?>

	



	<?php else:?>
		<p class="error"><?php echo $error ?></p>
	<?php endif ?>
	

	<!-- <div class="notice notice-success is-dismissible"><p>Success notice dismissible</p></div> -->


	


</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.css" integrity="sha512-8D+M+7Y6jVsEa7RD6Kv/Z7EImSpNpQllgaEIQAtqHcI0H6F4iZknRj0Nx1DCdB+TwBaS+702BGWYC0Ze2hpExQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js" integrity="sha512-zlWWyZq71UMApAjih4WkaRpikgY9Bz1oXIW5G0fED4vk14JjGlQ1UmkGM392jEULP8jbNMiwLWdM8Z87Hu88Fw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>