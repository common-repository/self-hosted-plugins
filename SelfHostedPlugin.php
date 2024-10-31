<?php

require_once(PACKAGE_DIRECTORY."Common/Parameterized_Object.php");

class SelfHostedPlugin extends Parameterized_Object
{
	function SelfHostedPlugin($SelfHostedPlugin_id = '',$params=array()){
		$this->Parameterized_Object($params);
		$this->setParameter('SelfHostedPluginID',$SelfHostedPlugin_id);	

		$this->setIDParameter('SelfHostedPluginID');
		$this->setNameParameter('SelfHostedPluginName');
	}
	
	function deleteVersion($version){
		$versions = $this->getVersions();
		if (!array_key_exists($version,$versions)){
			// nothing to do
			return true;
		}
		else{
			if (file_exists(SHP_DIR.$versions[$version]['filename'])){
				unlink(SHP_DIR.$versions[$version]['filename']);
			}
			unset($versions[$version]);
			$this->setVersions($versions);
			$SelfHostedPluginContainer = new SelfHostedPluginContainer();
			$SelfHostedPluginContainer->updateSelfHostedPlugin($this);
		}
	}
	
	function deleteAllVersions(){
		$versions = $this->getVersions();
		foreach (array_keys($versions) as $version){
			if (file_exists(SHP_DIR.$versions[$version]['filename'])){
				unlink(SHP_DIR.$versions[$version]['filename']);
			}
			unset($versions[$version]);
		}
		$this->setVersions($versions);
		$SelfHostedPluginContainer = new SelfHostedPluginContainer();
		$SelfHostedPluginContainer->updateSelfHostedPlugin($this);
	}
	
	function getVersions(){
		$versions = maybe_unserialize($this->getParameter('SelfHostedPluginVersions'));
		if (!is_array($versions)){
			$versions = array();
		}
		return $versions;
	}

	function setVersions(& $version_array){
		$keys = array_keys($version_array);
		natcasesort($keys);
		$sorted_keys = array_reverse($keys); // descending
		$_version_array = array();
		foreach ($sorted_keys as $key){
			$_version_array[$key] = $version_array[$key];
		}
		$version_array = $_version_array;
		$this->setParameter('SelfHostedPluginVersions',serialize($version_array));
	}
	
	function get($what,$meta=""){
		switch($what){
		case 'zip_file':
		case 'zip_url':
			$versions = $this->getVersions();
			if (((($version = $meta) != "") and array_key_exists($version,$versions)) or ($version = array_shift(array_keys($versions)))){
				$return = ($what == 'zip_file' ? SHP_DIR : SHP_URL).$this->getParameter('SelfHostedPluginSlug').$version.'.zip';
			}
			break;
		case 'download_url':
			$versions = $this->getVersions();
			$version = $meta;
			if (((($version = $meta) != "") and array_key_exists($version,$versions)) or ($version = array_shift(array_keys($versions)))){
				$return = BASE_URL.SHP_REDIRECT_DIR.urlencode($this->getParameter('SelfHostedPluginSlug')).'/'.$version;
			}
			break;
		case 'latest_version':
			$return = array_shift(array_keys($this->getVersions()));
			break;
		case 'readme_data':
			$return = SelfHostedPluginContainer::parseReadMe($this->get('zip_file',$meta));
			break;
		case 'plugin_data':
			$return = SelfHostedPluginContainer::parsePluginData($this->get('zip_file',$meta));
			break;
		case 'plugin_details':
			$return = $this->getPluginDetails($meta);
			break;
		}
		return $return;
	}
	
	function getPluginDetails($version = ''){
		/*
		The WordPress update process needs an array formatted like the following.  That's what we'll deliver....
		{
		    "name" : "External Update Example",
		    "slug" : "external-update-example",
		    "homepage" : "http://example.com/",
		    "download_url" : "http://w-shadow.com/files/external-update-example/external-update-example.zip",

		    "version" : "2.0",
		    "requires" : "3.0",
		    "tested" : "3.1-alpha",
		    "last_updated" : "2010-08-29 20:50:00",
		    "upgrade_notice" : "Here's why you should upgrade...",

		    "author" : "Janis Elsts",
		    "author_homepage" : "http://w-shadow.com/",

		    "sections" : {
		        "description" : "(Required) Plugin description.<p>This section will be opened by default when the user clicks on 'View version XYZ information'. Basic HTML can be used in all sections.</p>",
		        "installation" : "(Recommended) Installation instructions.",
		        "changelog" : "(Recommended) Changelog.",
		        "custom_section" : "This is a custom section labeled 'Custom Section'." 
		    },

		    "rating" : 90,
		    "num_ratings" : 123,
		    "downloaded" : 1234
		}		
		*/
		$readme_data = $this->get('readme_data',$version);
		$plugin_data = $this->get('plugin_data',$version);
		$payload = new stdClass;
		$payload->name = $this->getParameter('SelfHostedPluginName');
		$payload->slug = $this->getParameter('SelfHostedPluginSlug');
		$payload->homepage = $plugin_data['PluginURI'];
		$payload->download_url = $this->get('download_url',$plugin_data['Version']);
		$payload->version = $plugin_data['Version'];
		$payload->requires = $readme_data['requires_at_least'];
		$payload->tested = $plugin_data['tested_up_to'];
		$payload->last_updated = date("Y-m-d H:i:s",filemtime($this->get('zip_file',$version)));
		$payload->upgrade_notice = $readme_data['upgrade_notice'];
		$payload->author = $plugin_data['Author'];
		$payload->author_homepage = $plugin_data['AuthorURI'];
		$payload->sections = $readme_data['sections'];
		$payload->rating = $this->getParameter('SelfHostedPluginRating');
		$payload->num_ratings = $this->getParameter('SelfHostedPluginRatings');
		$payload->downloaded = $this->getParameter('SelfHostedPluginDownloads');
		
		$payload = apply_filters('shp_get_plugin_details',$payload,array(& $this));
		return $payload;
	}
	
	function getDetailsPage($version = ''){
		$url = get_bloginfo('url').'/'.SHP_REDIRECT_DIR.$this->getParameter('SelfHostedPluginSlug').($version != '' ? '/'.$version : '').'/details';
		if ($_GET['section'] != ''){
			$url.= '?section='.$_GET['section'];
		}
		$request = wp_remote_get($url);
		if ( is_wp_error($request) ) {
			$res = __('An Unexpected HTTP Error occurred during the API request. '). $request->get_error_message();
		} else {
			$res = $request['body'];
			
			// I need to do a couple of things to the result to make it work within this framework
			// First off, the section links reference the remote_url.  I want them to reference the current URL
			// TODO - comeback to this
			// - the version below was going to allow things of the form http://mysite.com/extend/plugins/hello-world/section/installation, but I couldn't get it working.  See @install_plugin_information
			//$res = preg_replace('/(href=\')[^\']+'.preg_quote(SHP_REDIRECT_DIR,'/').'[^\?]+\?.*section=([^\']*)/','$1section/$2'.(isset($_GET[SHP_SHORTCODE_QUERY_VAR]) ? '&'.SHP_SHORTCODE_QUERY_VAR.'='.$_GET[SHP_SHORTCODE_QUERY_VAR] : ''),$res);
			$res = preg_replace('/(href=\')[^\']+'.preg_quote(SHP_REDIRECT_DIR,'/').'[^\?]+\?.*(section=[^\']*)/','$1?$2'.(isset($_GET[SHP_SHORTCODE_QUERY_VAR]) ? '&'.SHP_SHORTCODE_QUERY_VAR.'='.$_GET[SHP_SHORTCODE_QUERY_VAR] : ''),$res);
			
			// Then, we need to inject a Download button
			$action_button = '<p class="action-button"><a href="'.$this->get('download_url',$version).'">'.__('Download').'</a></p>';
			$action_button = apply_filters('shp_details_action_button',$action_button,array(&$this,$version));
			$res = preg_replace('/(class\="alignright fyi">)/','$1'.$action_button,$res);			

			add_action('wp_footer','shp_include_scripts'); // Wish I could to this with wp_enqueue_script.  This enables javascript to switch the tabs
		}
		return $res;
	}
	
	function sendScreenshot($number=1,$version=""){
		SelfHostedPluginContainer::sendScreenshot($this->get('zip_file',$version),$number);
	}
	
	function getScreenshots($version=""){
		return SelfHostedPluginContainer::getScreenshots($this->get('zip_file',$version));
	}
}
?>