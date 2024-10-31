<?php

require_once(PACKAGE_DIRECTORY."Common/ObjectContainer.php");
require_once("SelfHostedPlugin.php");

if (!defined ('SHP_TABLE')){
    define('SHP_TABLE',DATABASE_PREFIX."SelfHostedPlugins");
}

if (!defined ('SHP_DIR')){
	if (!file_exists(DOC_BASE.CMS_ASSETS_DIRECTORY.'plugins/') and !mkdir(DOC_BASE.CMS_ASSETS_DIRECTORY.'plugins/')){
		echo 'Unable to create file manager directory at '.DOC_BASE.CMS_ASSETS_DIRECTORY.'plugins/';
	}
    define('SHP_URL',BASE_URL.CMS_ASSETS_DIRECTORY.'plugins/');
    define('SHP_DIR',DOC_BASE.CMS_ASSETS_DIRECTORY.'plugins/');
	if (!file_exists(SHP_DIR) and !mkdir(SHP_DIR)){
		echo 'Unable to create file manager directory at '.SHP_DIR;
	}
}

class SelfHostedPluginContainer extends ObjectContainer{

	var $tablename;
	
	function SelfHostedPluginContainer(){
		$this->ObjectContainer();
		$this->setDSN(DSN);
		$this->setTableName(SHP_TABLE);
		$this->setColumnName('SelfHostedPluginID','SelfHostedPluginID');
		$this->setColumnName('SelfHostedPluginName','SelfHostedPluginName');
		$this->setColumnName('SelfHostedPluginSlug','SelfHostedPluginSlug');
		$this->setColumnName('SelfHostedPluginVersions','SelfHostedPluginVersions');
		$this->setColumnName('SelfHostedPluginDownloads','SelfHostedPluginDownloads');
		$this->setColumnName('SelfHostedPluginRatings','SelfHostedPluginRatings');
		$this->setColumnName('SelfHostedPluginRating','SelfHostedPluginRating');
		$this->setColumnName('SelfHostedPluginModifiedTimestamp','SelfHostedPluginModifiedTimestamp');
		
		if (!$this->tableExists()){
			$this->initializeTable();
		}
		
	}
	
	function initializeTable(){
		$this->ensureTableExists();
		
	}
	
	function ensureTableExists(){
		$create_query="
			CREATE TABLE `".$this->getTableName()."` ( 
				`SelfHostedPluginID` int(6) unsigned AUTO_INCREMENT NOT NULL,
				`SelfHostedPluginName` varchar(255),
				`SelfHostedPluginSlug` varchar(255),
				`SelfHostedPluginVersions` text,
				`SelfHostedPluginDownloads` int(10) unsigned default 0,
				`SelfHostedPluginRatings` int(10) unsigned default 0,
				`SelfHostedPluginRating` int(3) unsigned default 0,
				`SelfHostedPluginModifiedTimestamp` timestamp default NOW(),
			  PRIMARY KEY  (`SelfHostedPluginID`),
			  KEY (`SelfHostedPluginName`),
			  KEY (`SelfHostedPluginSlug`)			  
			) TYPE=MyISAM 
		";

		if ($this->tableExists()){
			return true;
		}
		else{
			$result = $this->createTable($create_query);
			if (PEAR::isError($result)){
				return $result;
			}
			else{
				return true;
			}
		}
	}
	
	function addUploadedPluginFile($FileWidget,& $SelfHostedPlugin){
		if (!is_uploaded_file($_FILES[$FileWidget]['tmp_name'])){
			return PEAR::raiseError("There was no uploaded file entered in the file widget $FileWidget");
		}

		// Here goes the fancy stuff.  We need to extract the information about the plugin from the uploaded file
		$Dest = SHP_DIR.'___tmp___.zip';
		if (!move_uploaded_file($_FILES[$FileWidget]['tmp_name'],$Dest)){
			return PEAR::raiseError("Unable to move ".$_FILES[$FileWidget]['tmp_name']." to $Dest");
		}
		
		$result = $this->processPluginArchive($Dest,$SelfHostedPlugin);
		if (PEAR::isError($result)){
			return $result;
		}
		$plugin_info = $SelfHostedPlugin->getParameter('SelfHostedPluginInfo'); // gets set in processPluginArchive
		if (!isset($plugin_info['stable_tag'])){
			return PEAR::raiseError(sprintf("You must have a Stable tag in the readme.txt file.  For more information see the %ssample readme file%s and the %sreadme validator%s",'<a href="http://wordpress.org/extend/plugins/about/readme.txt" target="_blank">','</a>','<a href="http://wordpress.org/extend/plugins/about/validator/" target="_blank">','</a>'));
		}
		$filename = basename($_FILES[$FileWidget]['name']);
		$FinalDestFilename = $SelfHostedPlugin->getParameter('SelfHostedPluginSlug').$plugin_info['stable_tag'].'.zip';
		
		$versions = $SelfHostedPlugin->getVersions();
		$versions[$plugin_info['stable_tag']] = array('filename' => $FinalDestFilename,'date' => date("Y-m-d H:i:s"));
		krsort($versions);
		$wc = new whereClause();
		$wc->addCondition($this->getColumnName('SelfHostedPluginSlug').' = ?',$SelfHostedPlugin->getParameter('SelfHostedPluginSlug'));
		$wc->addCondition($this->getColumnName('SelfHostedPluginID').' <> ?',$SelfHostedPlugin->getParameter('SelfHostedPluginID'));
		if ($this->getAllSelfHostedPlugins($wc)){
			return PEAR::raiseError('A plugin with the slug `'.$SelfHostedPlugin->getParameter('SelfHostedPluginSlug').'` already exists.  There cannot be duplicates.');
		}
		$SelfHostedPlugin->setVersions($versions);
		if (file_exists(SHP_DIR.$FinalDestFilename)){
			unlink(SHP_DIR.$FinalDestFilename);
		}
		rename($Dest,SHP_DIR.$FinalDestFilename);
		if ($SelfHostedPlugin->getParameter('SelfHostedPluginID') == ""){
			// Adding it
			$result = $this->addSelfHostedPlugin($SelfHostedPlugin);
		}
		else{
			$result = $this->updateSelfHostedPlugin($SelfHostedPlugin);
		}
		return $result;
	}
	
	function processPluginArchive($PluginZip,&$SelfHostedPlugin){
		// Okay, here's where the meat of the processing happens.
		
		// Three things:
		//  1. A directory called __plugin-updates is added, containing only the file plugin-update-checker.class.php
		//  2. Some code is inserted at the end of your main plugin file to instantiate the above class
		//  3. All instances of the file .DS_STORE are removed (what can I say, I develop on a Mac and that file just bugs me)
		
		// Also, we parse the ReadMe file and add the info back into the SelfHostedPlugin object
		
		// Here we go.  First, we'll create a ZipArchive object
		$zip = new ZipArchive;
		if ($zip->open($PluginZip,ZIPARCHIVE::CREATE || ZIPARCHIVE::OVERWRITE) === true){
			$root = $zip->getNameIndex(0);
			$SelfHostedPlugin->setParameter('SelfHostedPluginSlug',str_replace('/','',$root)); // slug is taken from the root local directory - the first item in the zip
			$plugin_info = $this->parseReadme($PluginZip);
			if (PEAR::isError($plugin_info)){
				return $plugin_info;
			}
			$SelfHostedPlugin->setParameter('SelfHostedPluginInfo',$plugin_info);
			$SelfHostedPlugin->setParameter('SelfHostedPluginName',$plugin_info['name']);
		}
		else{
			return PEAR::raiseError('Unable to process plugin archive file');
		}
		
		// 1. insert the directory
		$status = $zip->statName($root.'__plugin-updates/');
		if (is_array($status)){
			// It already exists, we'll remove it to re-add
			$zip->deleteName($root.'__plugin-updates/');
		}
		if (!$zip->addEmptyDir($root.'__plugin-updates/')){
			return PEAR::raiseError('Unable to add the directory __plugin-updates to the plugin archive');
		}
		if (!$zip->addFile(dirname(__FILE__).'/lib/__plugin-updates/plugin-update-checker.class.php',$root.'__plugin-updates/plugin-update-checker.class.php')){
			return PEAR::raiseError('Unable to add the class file plugin-update-checker.class.php to the plugin archive');
		}
		
		// 2. Insert some code into the main plugin file
		// First, we have to __find__ the main plugin file
		$plugin_data_file_found = false;
		$begin_string = '<?php //BEGIN::SELF_HOSTED_PLUGIN_MOD';
		$end_string = '//END::SELF_HOSTED_PLUGIN_MOD ?>';
		for($i = 0; $i < $zip->numFiles; $i++){
			$test = $zip->getNameIndex($i);
			if (dirname($test).'/' == $root and strpos(basename($test),'.php')){
				// good candidate, need to extract it to test it
				$__tmp_filename = SHP_DIR.'___tmp___.php';
				file_put_contents($__tmp_filename,$zip->getFromIndex($i));
				$plugin_data = get_plugin_data($__tmp_filename,$markup);
				if ($plugin_data['Name'] != '' and $plugin_data['Version'] != ''){
					// Great, found it.  Now, we'll add our code in at the bottom, if it's not already there
					$plugin_data_file_found = true;
					$content = file_get_contents($__tmp_filename);
					if ($begin = strpos($content,$begin_string)){
						// It's already in there, remove it (to ensure that we always insert the latest version)
						$end = strpos($content,$end_string)+strlen($end_string);
						$content = substr($content,0,$begin).substr($content,$end);
					}
					// Great, ready to add it
					$updateCode = '
					
	/**********************************************
	* The following was added by Self Hosted Plugin
	* to enable automatic updates
	* See http://wordpress.org/extend/plugins/self-hosted-plugins
	**********************************************/
	require "__plugin-updates/plugin-update-checker.class.php";
	$__UpdateChecker = new PluginUpdateChecker(\''.get_bloginfo('wpurl').'/'.SHP_REDIRECT_DIR.$SelfHostedPlugin->getParameter('SelfHostedPluginSlug').'/update\', __FILE__,\''.$SelfHostedPlugin->getParameter('SelfHostedPluginSlug').'\');			
	
';
					file_put_contents($__tmp_filename,$content.$begin_string.$updateCode.$end_string);
					
					// Now, delete the entry in the zip
					$zip->deleteIndex($i);
					
					// And add in our updated file
					$zip->addFile($__tmp_filename,$test);
					break;
				}
			}
		}
		if (!$plugin_data_file_found){
			return PEAR::raiseError('Unable to find any .php file with plugin header information.  See the <a href="http://codex.wordpress.org/File_Header" target="_blank">File Header Reference</a>');
		}
		
		// 3. Finally, delete those pesky .DS_Store files
		$remove_globally = apply_filters('shp_remove_files_from_zip',array('.DS_Store')); // Allow plugins to augment
		for($i = 0; $i < $zip->numFiles; $i++){
			if (in_array(basename($zip->getNameIndex($i)),$remove_globally)){
				$zip->deleteIndex($i);
			}
		}

		// All done.
		if (!$zip->close()){
			return PEAR::raiseError('Unable to make required updates to the plugin');
		}
		if (file_exists($__tmp_filename)){
			unlink($__tmp_filename);
		}
		return true;
	}
	
	function parseReadme($PluginZip){
		$zip = new ZipArchive;
		if ($zip->open($PluginZip) === true){
			$root = $zip->getNameIndex(0);
			$readme = $zip->getFromName($root.'readme.txt');
			$zip->close();
			if (!$readme){
				return PEAR::raiseError("You must have a valid WordPress readme.txt file within the plugin zip");
			}
			include_once('lib/parse-readme/parse-readme.php');
			$Automattic_Readme = new Automattic_Readme();
			$plugin_info = $Automattic_Readme->parse_readme_contents($readme);
			if (!$plugin_info){
				return PEAR::raiseError("Could not validate the readme.txt file.  Have you run it against the <a href='http://wordpress.org/extend/plugins/about/validator/' target='_blank'>validator</a>?");
			}
			else{
				// let's inject the screenshots if there are any
				if (isset($plugin_info['sections']['screenshots']) and count($screenshots = SelfHostedPluginContainer::getScreenshots($PluginZip))){
					$m = $plugin_info['sections']['screenshots'];
					$m = str_replace('<ol>','<ol class=\'screenshots\'>',$m);
					$slug = str_replace('/','',$root);
					// Why is it so difficult to get the image markup in there?
					$m = str_replace('<li>','<li><img class=\'screenshot\' src=\''.get_bloginfo('url').'/'.SHP_REDIRECT_DIR.$slug.'/'.$plugin_info['stable_tag'].'/screenshot/__s__\' /><p>',$m);
					$m = str_replace('</li>','</p></li>',$m);
					for($s = 1; $s <= count($screenshots);$s++){
						$m = preg_replace('/__s__/',$s,$m,1);
					}
					$plugin_info['sections']['screenshots'] = $m;
				}
				if (isset($plugin_info['remaining_content'])){
					//parse remaining content into sections
					$other_sections = split('<h3>',$plugin_info['remaining_content']);
					array_shift($other_sections);
					foreach ($other_sections as $other_section){
						$split = split('</h3>',$other_section);
						$plugin_info['sections'][$split[0]] = $split[1];
					}
				}
				return $plugin_info;
			}
		}
	}
	
	function parsePluginData($PluginZip,$markup = false){
		$zip = new ZipArchive;
		if ($zip->open($PluginZip) === true){
			$root = $zip->getNameIndex(0);
			if (!function_exists('get_plugin_data')){
				require_once(ABSPATH.'wp-admin/includes/plugin.php');
			}
			for($i = 0; $i < $zip->numFiles; $i++){
				$test = $zip->getNameIndex($i);
				if (dirname($test).'/' == $root and strpos(basename($test),'.php')){
					// good candidate, need to extract it to test it
					$filename = SHP_DIR.'___tmp___.php';
					file_put_contents($filename,$zip->getFromIndex($i));
					$plugin_data = get_plugin_data($filename,$markup);
					unlink($filename); // Clean up
					if ($plugin_data['Name'] != '' and $plugin_data['Version'] != ''){
						// Valid match
						$zip->close();
						return $plugin_data;
					}
				}
			}
			$zip->close();
		}
		return false;
	}
		
	function addSelfHostedPlugin(& $SelfHostedPlugin){
		$this->setTimestamp($SelfHostedPlugin);
		return $this->addObject($SelfHostedPlugin);
	}
	
	function updateSelfHostedPlugin($SelfHostedPlugin,$UpdateTimestamp = true){
		if ($UpdateTimestamp){
			$this->setTimestamp($SelfHostedPlugin);
		}
		return $this->updateObject($SelfHostedPlugin);
	}
	
	function setTimestamp(&$SelfHostedPlugin){
		$SelfHostedPlugin->setParameter('SelfHostedPluginModifiedTimestamp', date("Y-m-d H:i:s"));
	}
	
	function getAllSelfHostedPlugins($whereClause = "",$_sort_field = array('SelfHostedPluginName'), $_sort_dir = array('asc')){
		if (PEAR::isError($Objects = $this->getAllObjects($whereClause,$_sort_field, $_sort_dir))) return $Objects;
		
		if ($Objects){
            $SelfHostedPlugins = $this->manufactureSelfHostedPlugin($Objects);
            return $SelfHostedPlugins;
		}
		else{
			return null;
		}
	}
	
    function manufactureSelfHostedPlugin($Object){
            if (!is_array($Object)){
                    $_Objects = array($Object);
            }
            else{
                    $_Objects = $Object;
            }
            
            $SelfHostedPlugins = array();
            foreach ($_Objects as $_Object){
                    $_tmp_SelfHostedPlugin = new SelfHostedPlugin();
                    $_parms = $_Object->getParameters();
                    foreach ($_parms as $key=>$value){
                            $_tmp_SelfHostedPlugin->setParameter($key,$value);
                    }
                    $_tmp_SelfHostedPlugin->saveParameters();
                    $SelfHostedPlugins[$_tmp_SelfHostedPlugin->getParameter($_tmp_SelfHostedPlugin->getIDParameter())] = $_tmp_SelfHostedPlugin;
            }
            
            if (!is_array($Object)){
                    return array_shift($SelfHostedPlugins);
            }
            else{
                    return $SelfHostedPlugins;
            }
    }	
    
	function getSelfHostedPlugin($SelfHostedPlugin){
		
		$wc = new whereClause();
		if (is_numeric($SelfHostedPlugin)){
			$wc->addCondition($this->getColumnName('SelfHostedPluginID')." = ?",$SelfHostedPlugin);
		}
		else{
			$wc->addCondition($this->getColumnName('SelfHostedPluginSlug')." = ?",$SelfHostedPlugin);
		}
		
		if (PEAR::isError($Object = $this->getObject($wc))) return $Object;
		
		if ($Object){
            return $this->manufactureSelfHostedPlugin($Object);
		}
		else{
			return null;
		}
	}
        
	function deleteSelfHostedPlugin($SelfHostedPlugin_id){
		$Plugin = $this->getSelfHostedPlugin($SelfHostedPlugin_id);
		if (!$Plugin){
			// It doesn't exist
			return true;
		}
		$Plugin->deleteAllVersions();
	    
		$wc = new whereClause($this->getColumnName('SelfHostedPluginID')." = ?",$SelfHostedPlugin_id);
		
		return $this->deleteObject($wc);
	
	}
	
	function sendScreenshot($PluginZip,$Number){
		$zip = new ZipArchive;
		if ($zip->open($PluginZip) === true){
			$root = $zip->getNameIndex(0);
			foreach(SelfHostedPluginContainer::getValidScreenshotExtensions() as $extension => $mime_type){
				if ($screenshot = $zip->getFromName($root.'screenshot-'.$Number.'.'.$extension)){
					header('Content-type: '.$mime_type);
					echo $screenshot;
					break;
				}
			}
		}
		exit();
	}
	
	function getScreenshots($PluginZip){
		$zip = new ZipArchive;
		$screenshots = array();
		if ($zip->open($PluginZip) === true){
			$root = $zip->getNameIndex(0);
			$no_more_screenshots = false;
			for($s=1;!$no_more_screenshots;$s++){
				$stat = false;
				foreach(SelfHostedPluginContainer::getValidScreenshotExtensions() as $extension => $mime_type){
					if ($stat = $zip->statName($root.'screenshot-'.$s.'.'.$extension)){
						break;
					}
				}
				if (!$stat){
					$no_more_screenshots = true;
				}
				else{
					$screenshots[$s] = $stat['name'];
				}
			}
		}
		return $screenshots;
	}
	
	function getValidScreenshotExtensions(){
		return array('png' => 'image/png', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'gif' => 'image/gif');
	}
}

?>