<?php
/*
Plugin Name: Config Constants
Plugin URI: http://www.presscoders.com/
Description: Modify WP_DEBUG and other wp-config.php constants directly in the WordPress admin rather than manually editing them!
Version: 0.1
Author: David Gwyer
Author URI: http://www.presscoders.com
*/

/*  Copyright 2009 David Gwyer (email : d.v.gwyer@presscoders.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* @todo
- Move all CSS to separate file and enqueue only on Plugin options page.
- Backup wp-config.php the first time the Plugin is activated into a db option. Delete this option if Plugin deleted? Enable a way to retrieve/edit this backup.
*/

global $const_names, $const_codex_links;

/* List of constants. Re-order to change the checkbox display order. */
$const_names = array(	'WP_DEBUG' =>				'chk_wp_debug_constant',
						'WP_DEBUG_LOG' =>			'chk_wp_debug_log_constant',
						'WP_DEBUG_DISPLAY' =>		'chk_wp_debug_display_constant',
						'SCRIPT_DEBUG' =>			'chk_script_debug_constant',
						'CONCATENATE_SCRIPTS' =>	'chk_concatenate_scripts_constant',
						'SAVEQUERIES' =>			'chk_savequeries_constant',
						'DISALLOW_FILE_MODS' =>		'chk_disallow_file_mods_constant',
						'DISALLOW_FILE_EDIT' =>		'chk_disallow_file_edit_constant',
						'WP_ALLOW_REPAIR' =>		'chk_wp_allow_repair_constant' );

/* Constant Codex links. If a link isn't specified for a constant then no image/link is rendered. */
$const_codex_links = array(	'WP_DEBUG' =>				'http://codex.wordpress.org/WP_DEBUG',
							'WP_DEBUG_LOG' =>			'http://codex.wordpress.org/Editing_wp-config.php#Configure_Error_Log',
							'WP_DEBUG_DISPLAY' =>		'http://codex.wordpress.org/Editing_wp-config.php#Configure_Error_Log',
							'SCRIPT_DEBUG' =>			'http://codex.wordpress.org/Debugging_in_WordPress#SCRIPT_DEBUG',
							'SAVEQUERIES' =>			'http://codex.wordpress.org/Editing_wp-config.php#Save_queries_for_analysis',
							'CONCATENATE_SCRIPTS' =>	'http://codex.wordpress.org/Editing_wp-config.php#Disable_Javascript_Concatenation',
							'DISALLOW_FILE_MODS' =>		'http://codex.wordpress.org/Editing_wp-config.php#Disable_Plugin_and_Theme_Update_and_Installation',
							'DISALLOW_FILE_EDIT' =>		'http://codex.wordpress.org/Editing_wp-config.php#Disable_the_Plugin_and_Theme_Editor',
							'WP_ALLOW_REPAIR' =>		'http://codex.wordpress.org/Editing_wp-config.php#Automatic_Database_Optimizing' );

/* pcdm_ prefix is derived from [p]ress [c]oders [d]ebug [m]ode. */
register_activation_hook( __FILE__, 'pcdm_plugin_activated' );
register_uninstall_hook( __FILE__, 'pcdm_delete_plugin_options' );
add_action( 'admin_init', 'pcdm_init' );
add_action( 'admin_menu', 'pcdm_add_options_page' );
add_filter( 'plugin_action_links', 'pcdm_plugin_action_links', 10, 2 );

add_action('activated_plugin','save_error');
function save_error(){
    update_option('pc_plugin_error',  ob_get_contents());
}

/* Delete options table entries ONLY when Plugin deactivated AND deleted. */
function pcdm_delete_plugin_options() {
	delete_option('pcdm_options');
}

function pcdm_plugin_activated() {
	pcdm_add_defaults();
	pcdm_sync_config(); /* Sync wp-config.php with Plugin settings. */
}

/* Define default option settings. */
function pcdm_add_defaults() {
	global $const_names;

	$tmp = get_option('pcdm_options');
	if( ( (isset($tmp['chk_default_options_db']) && $tmp['chk_default_options_db']=='1')) || (!is_array($tmp)) ) {
		$arr = array();
		foreach( $const_names as $const_name => $chkbox_name )
				$arr[$chkbox_name] = "";

		/* Add other defaults here. */
		$arr['chk_default_options_db'] = "";
		update_option('pcdm_options', $arr);
	}
}

/* Init Plugin options to white list our options. */
function pcdm_init(){
	register_setting( 'pcdm_plugin_options', 'pcdm_options' );
}

/* Add menu page. */
function pcdm_add_options_page() {
	add_options_page('Config Constants Options Page', 'Config Constants', 'manage_options', __FILE__, 'pcdm_render_form');
}

/* Draw the menu page itself. */
function pcdm_render_form() {
	?>
	<div class="wrap">
		<div class="icon32" id="icon-options-general"><br></div>
		<h2>Config Constants Options</h2>
		<p>Any constants showing as disabled have not yet been added to wp-config.php. Once added they will be editable via the settings below.</p>

		<?php
			global $pagenow, $const_flag_arr, $const_names;
			/* Sync settings if Plugin options NOT saved, but options page has been visited. */
			if( $pagenow == 'options-general.php' && isset($_GET["page"]) && $_GET["page"] == "config-constants/config-constants.php" && !isset($_GET["settings-updated"]) )
				pcdm_sync_config(); /* Sync wp-config.php with Plugin settings in-case they have been manually updated. */
			
			/* Save the Plugin settings to wp-config.php. */
			pcdm_update_config();
		?>

		<form method="post" action="options.php">
			<?php settings_fields('pcdm_plugin_options'); ?>
			<?php $options = get_option('pcdm_options'); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Toggle WordPress Constants</th>
					<td>
						<?php /* Loop to output the options form check boxes. */ ?>
						<?php foreach( $const_names as $const_name => $chkbox_name ) : ?>
						
						<?php $disabled = empty($const_flag_arr[$chkbox_name]) ? ' disabled' : ''; ?>
						<label><input name="pcdm_options[<?php echo $chkbox_name; ?>]" type="checkbox"<?php echo $disabled; ?> value="1" <?php if (isset($options[$chkbox_name])) { checked('1', $options[$chkbox_name]); } ?> /> <span<?php if(!empty($disabled)) echo ' style="color:#aaa;"' ?>><?php echo $const_name; ?></span></label><?php echo pcdm_codex_link($const_name); ?><br />

						<?php endforeach; ?>

						<span class="description">Changes to the active WordPress constants above will be refelcted in wp-config.php automatically after saving.</span><br />
					</td>
				</tr>
				<tr><td colspan="2"><div style="margin-top:0;"></div></td></tr>
				<tr valign="top" style="border-top:#dddddd 1px solid;">
					<th scope="row">Database Options</th>
					<td>
						<label><input name="pcdm_options[chk_default_options_db]" type="checkbox" value="1" <?php if (isset($options['chk_default_options_db'])) { checked('1', $options['chk_default_options_db']); } ?> /> Restore defaults upon Plugin deactivation/reactivation</label>
						<br /><span class="description">Only check this if you want to reset Plugin settings upon reactivation</span>
					</td>
				</tr>
			</table>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>

		<div style="margin-top:15px;">
			<p style="margin-bottom:10px;">If you use this Plugin on your website <b><em>please</em></b> consider making a <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=PEDF99Y2ZQE9N" target="_blank">donation</a> to support continued development. Thanks.&nbsp;&nbsp;:-)</p>
		</div>

		<div style="clear:both;">
			<p>
				<a href="http://www.facebook.com/PressCoders" title="Our Facebook page" target="_blank"><img src="<?php echo plugins_url(); ?>/config-constants/images/facebook.png" /></a><a href="http://www.twitter.com/dgwyer" title="Follow on Twitter" target="_blank"><img src="<?php echo plugins_url(); ?>/config-constants/images/twitter.png" /></a>&nbsp;<input class="button" style="vertical-align:12px;" type="button" value="Visit Our Site" onClick="window.open('http://www.presscoders.com')">&nbsp;<input class="button" style="vertical-align:12px;" type="button" value="Free Responsive Theme!" onClick="window.open('http://www.presscoders.com/designfolio')">
			</p>
		</div>

	</div>
	<?php	
}

function pcdm_update_config(){

	$config_file = ABSPATH.'wp-config.php';

	if(  file_exists($config_file) ) {

		global $const_flag_arr, $const_names;
		$options = get_option('pcdm_options');
		$config_contents = file_get_contents($config_file);

		/* If Plugin options saved. */
		if( isset($_GET["settings-updated"]) && ($_GET["settings-updated"] == "true") )  {

			/* Initialize with null flags. */
			$const_flag_arr = array();
			foreach( $const_names as $const_name => $chkbox_name )
				$const_flag_arr[$chkbox_name] = null;

			/* Return all lines containing 'define' statements in wp-config.php. */
			preg_match_all( '/^.*\bdefine\b.*$/im', $config_contents, $matches );
			
			/* Turn $matches array into string for further preg_match() calls. */
			$matches_str = implode( '', $matches[0] );

			foreach( $const_names as $const_name => $chkbox_name ) {
				if( preg_match( '/\b'.$const_name.'\b/', $matches_str ) ) {
					$res = pcdm_array_find( $const_name, $matches[0] );
					if($res !== false) {
						$updated_constant_value = isset($options[$chkbox_name]) ? 'true' : 'false';
						$updated_constant = str_replace( array( 'true', 'false' ), $updated_constant_value, trim( $matches[0][$res] ) );
						$config_contents = str_replace( trim($matches[0][$res]), $updated_constant, $config_contents );
						$const_flag_arr[$chkbox_name] = true;
					}
				}
				else {
					/* If constant not found then reset checkbox so it's not showing as selected when disabled. */
					$options[$chkbox_name] = false;
				}
			}
			update_option( 'pcdm_options', $options );

			/* Update wp-config.php. */
			file_put_contents( $config_file, $config_contents );
		}
	}
}

function pcdm_sync_config(){

	$config_file = ABSPATH.'wp-config.php';

	if(  file_exists($config_file) ) {

		global $const_flag_arr, $const_names;
		$options = get_option('pcdm_options');
		$config_contents = file_get_contents($config_file);

		/* Initialize with null flags. */
		$const_flag_arr = array();
		foreach( $const_names as $const_name => $chkbox_name ) {
			$const_flag_arr[$chkbox_name] = null;
			$options[$chkbox_name] = false;
		}

		/* Return all lines containing 'define' statements in wp-config.php. */
		preg_match_all( '/^.*\bdefine\b.*$/im', $config_contents, $matches );

		/* Turn $matches array into string for further preg_match() calls. */
		$matches_str = implode( '', $matches[0] );

		foreach( $const_names as $const_name => $chkbox_name ) {
			if( preg_match( '/\b'.$const_name.'\b/', $matches_str ) ) {
				$res = pcdm_array_find( $const_name, $matches[0] );
				if($res !== false) {
					$options[$chkbox_name] = preg_match( '/\btrue\b/', ($matches[0][$res]) ) ? true : false;
					$const_flag_arr[$chkbox_name] = true;
				}
			}
		}
		update_option( 'pcdm_options', $options );
	}
}

/* Display a Settings link on the main Plugins page. */
function pcdm_plugin_action_links( $links, $file ) {

	if ( $file == plugin_basename( __FILE__ ) ) {
		$posk_links = '<a href="'.get_admin_url().'options-general.php?page=config-constants/config-constants.php">'.__('Settings').'</a>';
		/* Make the 'Settings' link appear first. */
		array_unshift( $links, $posk_links );
	}

	return $links;
}

/* Custom version of the PHP function array_search() that allows partial array key matches. */
function pcdm_array_find($needle, $haystack, $search_keys = false) {

		if(!is_array($haystack)) return false;
        foreach($haystack as $key=>$value) {
            $what = ($search_keys) ? $key : $value;
            if(strpos($what, $needle)!==false) return $key;
        }
        return false;
}

/* Show icon linking*/
function pcdm_codex_link($const_name) {
	
	global $const_codex_links;

	$url = array_key_exists( $const_name, $const_codex_links ) ? $const_codex_links[$const_name] : '';

	if( !empty($url) )
		return '&nbsp;&nbsp;<a href="'.$url.'" target="_blank"><img title="WordPress Codex information" style="width:12px;height:12px;display:inline;vertical-align:text-bottom;" src="'.plugins_url().'/config-constants/images/info.png" /></a>';
	else
		return '';
}

?>