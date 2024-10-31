<?php
/*
Plugin Name: Self Hosted Plugins
Plugin URI: http://wordpress.org/extend/plugins/self-hosted-plugins
Description: A Top Quark Wordpress Plugin to manage self-hosted plugins.
Version: 1.0.10
Author: Top Quark
Author URI: http://www.topquark.com
*/
add_action('init','shp_init');
function shp_init(){
	if (!defined('SHP_QUERY_VAR')){
		define('SHP_QUERY_VAR','shp_plugin');
	}
	if (!defined('SHP_SHORTCODE_QUERY_VAR')){
		define('SHP_SHORTCODE_QUERY_VAR','show_plugin');
	}
	if (!defined('SHP_ACTION_QUERY_VAR')){
		define('SHP_ACTION_QUERY_VAR','shp_action');
	}
	if (!defined('SHP_VERSION_QUERY_VAR')){
		define('SHP_VERSION_QUERY_VAR','version');
	}
	if (!defined('SHP_SCREENSHOT_QUERY_VAR')){
		define('SHP_SCREENSHOT_QUERY_VAR','screenshot');
	}
	if (!defined('SHP_REDIRECT_DIR')){
		define('SHP_REDIRECT_DIR','extend/plugins/');
	}
	if (!defined('SHP_SECTION_QUERY_VAR')){
		define('SHP_SECTION_QUERY_VAR','section');
	}

	if (!class_exists('Bootstrap') or !class_exists('ZipArchive')){
		add_action('admin_notices','shp_admin_notices');
	}
	else{
		$Bootstrap = Bootstrap::getBootstrap();
		$Bootstrap->registerPackage('SelfHostedPlugins','../../../self-hosted-plugins/');	
		$Bootstrap->usePackage('SelfHostedPlugins');
		add_action( 'pre_get_posts', 'shp_template_redirect' );
		wp_enqueue_style('shp_stylesheet',plugins_url('self-hosted-plugins').'/css/shp_styles.css');
	}
}

function shp_admin_notices(){
	$notes = array();
	$errors = array();
	if (!class_exists('Bootstrap')){
		$errors[] = sprintf(__('The plugin "Self Hosted Plugins" requires the "Top Quark Architecture" plugin to be installed and activated.  This plugin can be downloaded from %sWordPress.org%s'),'<a href="http://wordpress.org/extend/plugins/topquark/" target="_blank">.','</a>');
	}
	if (!class_exists('ZipArchive')){
		$errors[] = sprintf(__('The plugin "Self Hosted Plugins" requires at least PHP 5.2.0 compiled with --enable-zip as it uses the ZipArchive class'));
	}
	
    foreach ($errors as $error) {
        echo sprintf('<div class="error"><p>%s</p></div>', $error);
    }
    
    foreach ($notes as $note) {
        echo sprintf('<div class="updated fade"><p>%s</p></div>', $note);
    }
}

function shp_template_redirect(){
	// If a plugin download has been requested, then we'll try to download it
	// If anything fails, this function will do nothing
	if ($shp_query_var = get_query_var(SHP_QUERY_VAR)) {
		global $wp_query;
		// great, we've got something to look up
		$SelfHostedPluginContainer = new SelfHostedPluginContainer();
		$Plugin = $SelfHostedPluginContainer->getSelfHostedPlugin($shp_query_var);
		if (!is_a($Plugin,'SelfHostedPlugin')){
			$wp_query->is_404 = true;
			return;
		}
		
		// Great, the plugin exists
		$Versions = $Plugin->getVersions();
		if ($version = get_query_var(SHP_VERSION_QUERY_VAR) ){
			if (!array_key_exists($version,$Versions)){
				$wp_query->is_404 = true;
				return;
			}
		}
		else{
			// in the case of http://mysite.com/extend/plugis/hello-world, show a template with the plugin details
			if (get_query_var(SHP_ACTION_QUERY_VAR) == ''){
				if (apply_filters('shp_enable_redirect',true)){
					shp_spoof_page($Plugin); // spoofs a fake page with the plugin details
				}
				else{
					$wp_query->is_404 = true;
				}
				return;
			}
			//$version = current(array_keys($Versions));
		}
		
		switch(get_query_var(SHP_ACTION_QUERY_VAR)){
		case 'update':
			// An update request has come in from a remote server
			$payload = $Plugin->get('plugin_details');
			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
			header('Content-type: application/json');
			echo json_encode($payload);
			exit();
		case 'details':
			// Asking for the details page
			require_once(ABSPATH.'/wp-admin/includes/plugin-install.php');
			add_filter('plugins_api','shp_spoof_plugins_api',10,3);
		
			// Need to spoof iframe_header and iframe_footer
			function iframe_header(){
				echo '<div id="shp-plugin-details">'."\n";
			}
			function iframe_footer(){
				echo '</div>'."\n";
			}
			global $tab;
			$tab = 'shp-plugin';
			//$_REQUEST['section'] = get_query_var(SHP_SECTION_QUERY_VAR); // This is a remnant from trying to make http://mysite.com/extend/plugins/hello-world/section/installation work
			install_plugin_information();
		case 'screenshot':
			$Plugin->sendScreenshot(get_query_var(SHP_SCREENSHOT_QUERY_VAR),get_query_var(SHP_VERSION_QUERY_VAR));
			break;
		default:
			// Just trying to download it
			// Let's let plugins set the permissions for download
			if (!apply_filters('allow_self_hosted_plugin_download',true,$Plugin,$version)){
				wp_die('You do not have permission to download this plugin');
				return;
			}

			// We'll also allow plugins to redirect on a download request
			$redirect = apply_filters('redirect_self_hosted_plugin_download','',$Plugin,$version);
			if ($redirect != ''){
				wp_redirect($redirect);
				exit();
			}

			// Great, we're allowed to download it
			$details = $Versions[$version];
			if (!file_exists(SHP_DIR.$details['filename'])){
				return;
			}

			// Increment the download counter
			$Plugin->setParameter('SelfHostedPluginDownloads',intval($Plugin->getParameter('SelfHostedPluginDownloads'))+1);
			$SelfHostedPluginContainer->updateSelfHostedPlugin($Plugin,false);

			// Great, the file exists
			header("Vary: User-Agent");
			header("Content-disposition: attachment; filename=\"".$details['filename']."\"");
			header("Content-Type: application/zip");
			readfile(SHP_DIR.$details['filename']);
			exit();
		}
		
	}
}

function is_shp_plugin_page(){
	// if we're displaying a 
	return (get_query_var(SHP_QUERY_VAR) != '' && get_query_var(SHP_ACTION_QUERY_VAR) == '' && apply_filters('shp_enable_redirect',true));
}

function shp_template_include($shp_template_include){
	//if (apply_filters('shp_enqueue_jquery',true)) wp_enqueue_script('jquery');
	//wp_enqueue_script('shp_js',plugins_url('self-hosted-plugins/js/shp_js.details.js'),'jquery');
	$template = ABSPATH.'wp-content/plugins/'.plugin_basename('self-hosted-plugins/templates/plugin-details.php');
	return $template;
}
function shp_include_scripts(){
	$src = plugins_url('self-hosted-plugins/js/shp_js.details.js');
	echo "<script type='text/javascript' src='$src'></script>\n";
}
function shp_spoof_plugins_api($api,$action,$args){
	$SelfHostedPluginContainer = new SelfHostedPluginContainer();
	$Plugin = $SelfHostedPluginContainer->getSelfHostedPlugin(get_query_var(SHP_QUERY_VAR));
	if (!is_a($Plugin,'SelfHostedPlugin')){
		return new WP_Error('shp_plugin_details_failed', __('Could not find the requested plugin'));
	}
	$return = $Plugin->get('plugin_details',get_query_var(SHP_VERSION_QUERY_VAR));
	return $return;
}
function shp_spoof_page($Plugin){
	require_once('lib/fakepage.php');
	$Spoof = new FakePage;
	$Spoof->page_slug = $Plugin->getParameter('SelfHostedPluginSlug');
	$Spoof->page_title = $Plugin->getParameter('SelfHostedPluginName');
	$Spoof->content = $Plugin->getDetailsPage();
	$Spoof->force_injection = true;
	define(SHP_SPOOFED_PLUGIN_PAGE,true);
}

//add_action('parse_request','shp_parse_request'); // uncomment to check what was matched
function shp_parse_request($wp_rewrite){
	var_dump($wp_rewrite);
}

add_filter('option_rewrite_rules','shp_rewrite_rules');
function shp_rewrite_rules($rules){
	$shp_rules[SHP_REDIRECT_DIR.'([^/]+)/?$'] = 'index.php?'.SHP_QUERY_VAR.'=$matches[1]'; // the plugin page
	$shp_rules[SHP_REDIRECT_DIR.'([^/]+)/update/?$'] = 'index.php?'.SHP_QUERY_VAR.'=$matches[1]&'.SHP_ACTION_QUERY_VAR.'=update'; // a request for update
	$shp_rules[SHP_REDIRECT_DIR.'([^/]+)/details/?$'] = 'index.php?'.SHP_QUERY_VAR.'=$matches[1]&'.SHP_ACTION_QUERY_VAR.'=details'; // a request for the details of the plugin
	$shp_rules[SHP_REDIRECT_DIR.'([^/]+)/section/([^/]+)/?$'] = 'index.php?'.SHP_QUERY_VAR.'=$matches[1]&'.SHP_ACTION_QUERY_VAR.'=details&'.SHP_SECTION_QUERY_VAR.'=$matches[2]'; // a particular section of the details page
	$shp_rules[SHP_REDIRECT_DIR.'([^/]+)/([^/]+)/?$'] = 'index.php?'.SHP_QUERY_VAR.'=$matches[1]&'.SHP_VERSION_QUERY_VAR.'=$matches[2]'; // a plugin + version = direct download
	$shp_rules[SHP_REDIRECT_DIR.'([^/]+)/([^/]+)/details/?$'] = 'index.php?'.SHP_QUERY_VAR.'=$matches[1]&'.SHP_VERSION_QUERY_VAR.'=$matches[2]&'.SHP_ACTION_QUERY_VAR.'=details'; // details on a specific version
	$shp_rules[SHP_REDIRECT_DIR.'([^/]+)/([^/]+)/section/?$'] = 'index.php?'.SHP_QUERY_VAR.'=$matches[1]&'.SHP_VERSION_QUERY_VAR.'=$matches[2]&'.SHP_ACTION_QUERY_VAR.'=details&'.SHP_SECTION_QUERY_VAR.'=$matches[3]'; // section of details page for a particular version
	$shp_rules[SHP_REDIRECT_DIR.'([^/]+)/([^/]+)/screenshot/([^/]+)/?$'] = 'index.php?'.SHP_QUERY_VAR.'=$matches[1]&'.SHP_VERSION_QUERY_VAR.'=$matches[2]&'.SHP_ACTION_QUERY_VAR.'=screenshot&'.SHP_SCREENSHOT_QUERY_VAR.'=$matches[3]'; // a particular screenshot from a particular version
	
	$shp_rules = apply_filters('shp_rewrite_rules',$shp_rules);
	
	// I want the SHP rules to appear at the beginning - thereby taking precedence over other rules
	$rules = $shp_rules + $rules;
	
	return $rules;
}

add_filter('query_vars','shp_query_vars');
function shp_query_vars($query_vars){
	$query_vars[] = SHP_QUERY_VAR;
	$query_vars[] = SHP_VERSION_QUERY_VAR;
	$query_vars[] = SHP_ACTION_QUERY_VAR;
	$query_vars[] = SHP_SCREENSHOT_QUERY_VAR;
	$query_vars[] = SHP_SECTION_QUERY_VAR;
	return $query_vars;
}


add_shortcode('self-hosted-plugins','shp_shortcodes');
function shp_shortcodes($atts){
    extract(shortcode_atts(array(
	      'id' => '',
		'slug' => '',
		'version' => '',
		'history' => false,
		'details' => false,
		'format' => '',
		'heading' => 'My Plugins'
    ), $atts));

	$SelfHostedPluginContainer = new SelfHostedPluginContainer();
	if ($id != '' or $slug != ''){
		$Plugin = $SelfHostedPluginContainer->getSelfHostedPlugin($id != '' ? $id : $slug);
		if (!is_a($Plugin,'SelfHostedPlugin')){
			return "Plugin not found";
		}
		if ($history){
			// reqguested the archive list
			$return = "<ul class=\"shp_plugin_list\">\n";
			$versions = $Plugin->getVersions();
			if ($format == ''){
				$format = 'Version %s';
			}
			foreach(array_keys($versions) as $version){
				$return.= '<li><a href="'.$Plugin->get('download_url',$version).'">'.sprintf($format,$version).'</a>'."\n";
			}
			$return.= "</ul>\n";
		}
		elseif($details){
			// Show the detail page for the plugin
			$return = $Plugin->getDetailsPage($version);
		}
		else{
			// Just return the download link for the latest version
			$return = '<a href="'.$Plugin->get('download_url',$version).'">'.$Plugin->getParameter('SelfHostedPluginName').'</a>';
		}
	}
	else{
		// No attributes, just list all self-hosted plugins
		if(isset($_GET[SHP_SHORTCODE_QUERY_VAR])){
			// They're looking at a specific plugin
			$Plugin = $SelfHostedPluginContainer->getSelfHostedPlugin($_GET[SHP_SHORTCODE_QUERY_VAR]);
			$return = $Plugin->getDetailsPage();
		}
		else{
			$return = '';
		}
		
		$Plugins = $SelfHostedPluginContainer->getAllSelfHostedPlugins();
		$return.= '<h3 class="shp_all_plugins_header" style="clear:both">'.$heading.'</h3>';
		$return.= "<ul class=\"shp_plugin_list\">\n";
		foreach ($Plugins as $Plugin){
			$return.= '<li><a href="'.BASE_URL.SHP_REDIRECT_DIR.$Plugin->getParameter('SelfHostedPluginSlug').'">'.$Plugin->getParameter('SelfHostedPluginName').'</a>'."\n";
		}
		$return.= "</ul>\n";
	}
	return $return;
}

function & the_plugin(){
	global $shp_plugin, $shp_plugin_instance;
	if ($shp_plugin and !isset($shp_plugin_instance)){
		$SelfHostedPluginContainer = new SelfHostedPluginContainer();
		$shp_plugin_instance = $SelfHostedPluginContainer->getSelfHostedPlugin($shp_plugin);
	}
	return $shp_plugin_instance;
}

function the_plugin_detail_page(){
	$Plugin = & the_plugin();
	return $Plugin->getDetailsPage(the_plugin_version());
}

function the_plugin_version(){
	return get_query_var(SHP_VERSION_QUERY_VAR);
}

?>