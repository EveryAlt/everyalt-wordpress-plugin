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

<div id="everyalt-custom-media-button" 
    data-admin="<?php echo admin_url()?>"
    data-media="<?php echo $post->ID ?>"
    >
</div>