<?php
/*
Plugin Name: SocialCompare embed
Plugin URI: http://socialcompare.com/
Description: Allows to easily embed a SocialCompare comparison within a post. [socialcompare]http://socialcompare.com/en/comparison/apples-and-oranges[/socialcompare] or [sc]http://socialcompare.com/en/w/apples-and-oranges[/sc]
Version: 1.0
Author: SocialCompare (Alexis)
Author URI: http://socialcompare.com/en/member/alexis
License: GPL2
*/
/*  Copyright 2010  Alexis Fruhinsholz  (email: alexis@socialcompare.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Handler to transform [sc] or [socialcompare] syntax to appropriate code to include SocialCompare comparison table
 */
function socialcompare_shortcode($atts, $content=null, $code='') {
	//check if the url is a SocialCompare embeddable url
	if (preg_match('@^https?://((?:[^/]*\.)?socialcompare.com/\w\w/)(w|comparison)(/[a-z\-_0-9]+)(#.*)?$@i', $content, $matches)) {
		$widgetUrl='http://'.$matches[1].'w'.$matches[3];
		if (isset($matches[4])) {
			$widgetUrl.=$matches[4];
		}
		//check if there is a height or width specified in the [sc] or [socialcompare] tags
		extract(shortcode_atts(array('width' => 0, 'height' => 0), $atts));
		if (empty($width)) { $width=socialcompare_get_width(); }
		if (empty($height)) { $height=socialcompare_get_height(); }
		$width=(preg_match('#^\d+%$#', $width) ? $width : intval($width));
		$height=(preg_match('#^\d+%$#', $height) ? $height : intval($height));

		//add default design param if not already set in the URL
		$hashPos=strpos($widgetUrl, '#');
		if($hashPos===false){
			$socialcompare_design=socialcompare_get_design_stripped();
			if (!empty($socialcompare_design)) {
				$widgetUrl.='#'.$socialcompare_design;
			}
		}
		return '<iframe src="'.$widgetUrl.'" width="'.$width.'" height="'.$height.'" frameborder="0" scrolling="auto" marginheight="0" marginwidth="0"></iframe>';
	}
}

function socialcompare_admin_menu(){
	if (function_exists('add_submenu_page')) {
		//add link under plugin for users that can manage options to allow default embed options
		add_submenu_page('plugins.php', __('SocialCompare embed options','socialcompare-embed'), 'SocialCompare', 'manage_options', 'socialcompare-plugin-page', 'socialcompare_plugin_page');
	}
	add_filter('plugin_row_meta', 'socialcompare_register_plugin_links', 10, 2);
}

/** Get the default width (try socialcompare option fallback to embed size or default value) */
function socialcompare_get_width(){
	return socialcompare_get_size('socialcompare_width', 'embed_size_w', '100%');
}

/** Get the default height (try socialcompare option fallback to embed size or default value) */
function socialcompare_get_height(){
	return socialcompare_get_size('socialcompare_height', 'embed_size_h', '500');
}

/** Get the design parameter without linefeed */
function socialcompare_get_design_stripped(){
	$socialcompare_design=get_option('socialcompare_design','');
	if (!empty($socialcompare_design)) {
		$socialcompare_design=preg_replace("/(\n|\r)/", '', $socialcompare_design);//strip line feeds
		return $socialcompare_design;
	}
	return '';
}


/** Helper function to fetch option with fallback option and value */
function socialcompare_get_size($option_name, $option_fallback, $default){
	$size=get_option($option_name);
	if (empty($size)) {
		$size=get_option($option_fallback);
	}
	if (empty($size) || !preg_match('#^\d+(%|px)?$#', $size)) {
		$size=$default;
	}
	return $size;
}

/** Update given option size returning a message to display or false if no modifications has been applied */
function socialcompare_update_size($param_name){
	if (!empty($_POST[$param_name])){
		$size=$_POST[$param_name];
		if (preg_match('#^\d+(%|px)?$#', $size)) {
			update_option($param_name, $size);
			return __('Size saved', 'socialcompare-embed').'<br />';
		}
		return '<strong style="color:red">'.__('Invalid size value', 'socialcompare-embed').'</strong><br />';
	}
	return false;
}

function socialcompare_plugin_page(){
	$socialcompare_width=socialcompare_get_width();
	$socialcompare_height=socialcompare_get_height();
	$socialcompare_design=get_option('socialcompare_design','');

	if (count($_POST)>0) {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			die(__('Cheatin&#8217; uh?'));
		}

		$result='';
		$result.=socialcompare_update_size('socialcompare_width');
		$result.=socialcompare_update_size('socialcompare_height');
		$socialcompare_width=socialcompare_get_width();
		$socialcompare_height=socialcompare_get_height();
		if (!empty($_POST['socialcompare_design'])){
			$socialcompare_design=htmlspecialchars($_POST['socialcompare_design']);
			//check syntax is valid for widget design
			if (preg_match('/^#?((\s*;\s*)?([a-z\\-])+(:[0-9a-zA-Z#\\.\\-\\s,]+)?)+$/m', $socialcompare_design)) {
				if (strpos($socialcompare_design, '#')===0){//remove leading '#'
					$socialcompare_design=substr($socialcompare_design, 1);
				}
				update_option('socialcompare_design', $socialcompare_design);
				$result.=__('Design option saved', 'socialcompare-embed').'<br />';
			}
			else {
				$result.='<strong style="color:red">'.__('Design option invalid', 'socialcompare-embed').'</strong><br />';
			}
		}

		if ($resultW || $resultH || $resultDesign) ?>
			<div id="message" class="updated fade"><p><strong><?php echo $result; ?></strong></p></div>
		<?php
	}
	?><h2><?php _e('SocialCompare Configuration', 'socialcompare-embed'); ?></h2>
<p><?php _e('You can specify here the default size and design options to use when embedding a SocialCompare comparison table using [socialcompare] or [sc] shortcode.', 'socialcompare-embed')?></p>
<form action="" method="post">
<p><label for="socialcompare_width"><?php _e('Default width', 'socialcompare-embed'); ?></label> <input name="socialcompare_width" type="text" size="15" maxlength="12" value="<?php echo $socialcompare_width; ?>" /> ('100%', '500')</p>
<p><label for="socialcompare_height"><?php _e('Default height', 'socialcompare-embed'); ?></label> <input name="socialcompare_height" type="text" size="15" maxlength="12" value="<?php echo $socialcompare_height; ?>" />  ('450', '600')</p>
<p><label for="socialcompare_design"><?php _e('Default design options', 'socialcompare-embed'); ?></label> <small>(<?php _e('More details:', 'socialcompare-embed'); ?>
 <a href="http://blog.socialcompare.com/2010/12/08/customize-the-embed-comparison-table/" target="_blank">[EN]</a>
 <a href="http://blog.socialcompare.com/fr/2010/12/08/personnaliser-le-tableau-comparatif-a-inclure-sur-votre-blog-ou-site-web/" target="_blank">[FR]</a>)</small>
<br /><textarea name="socialcompare_design" cols="80" rows="3"><?php echo $socialcompare_design; ?></textarea></p>
<p><button type="submit" name="update"><?php _e('Update options &raquo;'); ?></button></p>
</form>
<h2><?php _e('How to embed SocialCompare\'s comparison?', 'socialcompare-embed'); ?></h2>
<p><?php _e('Once you have a comparison URL you can use following syntax in your blog post or page.', 'socialcompare-embed')?></p>
<code>[socialcompare]http://socialcompare.com/en/w/apples-and-oranges[/socialcompare]</code><br />
<br />
<code>[sc]http://socialcompare.com/en/comparison/apples-and-oranges[/sc]</code>
<p><?php _e('You can override the default dimensions specified above using this syntax.', 'socialcompare-embed')?></p>
<code>[socialcompare width="50%" height="300"]http://socialcompare.com/en/comparison/apples-and-oranges[/socialcompare]</code><br />
<br />
<code>[sc height="600" width="500"]http://socialcompare.com/en/comparison/apples-and-oranges[/sc]</code>
<p><?php _e('You can override the default design specified above using this syntax.', 'socialcompare-embed')?></p>
<code>[socialcompare width="50%" height="300"]http://socialcompare.com/en/comparison/apples-and-oranges#nofull;noflags[/socialcompare]</code><br />
<br />
<code>[sc]http://socialcompare.com/en/comparison/apples-and-oranges#noedit[/sc]</code>
	<?php
}


/** Helper to get the relative URL to our plugin directory */
function socialcompare_get_basename(){
	return plugin_basename(__FILE__);
}

function socialcompare_register_plugin_links($links, $file) {
	$base=socialcompare_get_basename();
	if ($file==$base){ // filter is executed on all entries so check it's our own plugin and add a link to the settings
		$links[]='<a href="plugins.php?page=socialcompare-plugin-page">'.__('Settings').'</a>';
	}
	return $links;
}


function socialcompare_init(){
	/* Define handler to be able to use [sc] and [socialcompare] syntax in your posts or pages */
	add_shortcode('socialcompare','socialcompare_shortcode');
	add_shortcode('sc','socialcompare_shortcode');

	/* Add SocialCompare oembed definition to include wordpress builtin oembed support for SocialCompare's url. Include the comparison url on a single line during post or page edition. See Wordpress oembed documentation for detail */
	wp_oembed_add_provider('#http://socialcompare.com/\w\w/(w|comparison)/*#i', 'http://socialcompare.com/oembed', true);

	add_action('admin_menu', 'socialcompare_admin_menu');
}

//Enable the plugin for the init hook, but only if WP is loaded. Calling this php file directly will do nothing.
if(defined('ABSPATH') && defined('WPINC')) {
	add_action('init','socialcompare_init');
}

//no closing php to avoid blank space insertion
