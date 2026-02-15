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

<!-- hedaer -->
<div class="everyalt-header">
    <div class="everyalt-container">
		<div class="everyalt-logo">
			<img src=<?php echo $logo_url ?> alt="<?php _e( 'Every Alt', 'everyalt' )?>"> 
		</div>
		<nav class="everyalt-nav">
			<ul>
				<li class="<?php echo $active == 'settings' ? 'active':''?>"><a href="<?php echo admin_url()?>upload.php?page=everyalt&tab=settings"><?php _e( 'Settings', 'everyalt' )?></a></li>
				<li class="<?php echo $active == 'bulk' ? 'active':''?>"><a href="<?php echo admin_url()?>upload.php?page=everyalt&tab=bulk"><?php _e( 'Bulk Alt Text Generator', 'everyalt' )?></a></li>
				<li class="<?php echo $active == 'history' ? 'active':''?>"><a href="<?php echo admin_url()?>upload.php?page=everyalt&tab=history"><?php _e( 'History', 'everyalt' )?></a></li>
			</ul>
		</nav>
	</div>
</div>
<!-- hedaer -->



<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<?php if($active == 'settings'):?>
    <div class="everyalt-settings-page" 
    id="everyalt-plugin" 
    data-logo="<?php echo $logo_url?>"
    data-active="<?php echo $active ?>"
    data-admin="<?php echo admin_url()?>"
    >
</div>
<?php endif ?>

<?php if($active == 'bulk'):?>
    <div class="everyalt-settings-page" 
    id="everyalt-bulk" 
    data-logo="<?php echo $logo_url?>"
    data-active="<?php echo $active ?>"
    data-admin="<?php echo admin_url()?>"
    data-tokens="<?php echo isset($tokens) ? $tokens : false  ?>"
    data-used_tokens="<?php echo isset($used_tokens) ? $used_tokens : false ?>"
    data-images="<?php echo htmlspecialchars(json_encode($images), ENT_QUOTES, 'UTF-8') ?>"
    data-error="<?php echo isset($error) ? $error : false ?>"
    >
</div>


		
<?php endif ?>

<?php if($active == 'history'):?>
    <div class="everyalt-settings-page" 
    id="everyalt-history" 
    data-images="<?php echo htmlspecialchars(json_encode($images), ENT_QUOTES, 'UTF-8') ?>"
    data-logo="<?php echo $logo_url?>"
    data-active="<?php echo $active ?>"
    data-admin="<?php echo admin_url()?>"
    ></div>
    <?php if($images['pagination']):?>
    <div id="every_alt_pagination">
		<div class="pagination" >
			<?php echo $images['pagination']?>
	    </div>
    </div>	
    <?php endif ?>	
<?php endif ?>



