<?php
/************************************************************
*
*
*************************************************************/
    if (!$Bootstrap){
        die ("You cannot access this file directly");
    }

	$Bootstrap->addPackagePageToAdminBreadcrumb($Package,'manage');
	$Bootstrap->addPackagePageToAdminBreadcrumb($Package,'edit');
	$manage = $Bootstrap->makeAdminURL($Package,'manage');
	$edit = $Bootstrap->makeAdminURL($Package,'edit');
	$delete = $Bootstrap->makeAdminURL($Package,'delete');

	define (HTML_FORM_TH_ATTR,"valign=top align=left width='15%'");
	define (HTML_FORM_TD_ATTR,"valign=top align=left");
    define (HTML_FORM_MAX_FILE_SIZE, intval(ini_get('upload_max_filesize')) * 1024 * 1024); // Number of bytes, assuming upload_max_filesize is in the form 32M
	include_once(PACKAGE_DIRECTORY.'../TabbedForm.php');
        
	$SelfHostedPluginContainer = new SelfHostedPluginContainer();
        
	if ($_REQUEST['shpid']){
	        $SelfHostedPlugin = $SelfHostedPluginContainer->getSelfHostedPlugin($_REQUEST['shpid']);
	}
	if (!$SelfHostedPlugin){
	        $SelfHostedPlugin = new SelfHostedPlugin();
	}

        
	
	/******************************************************************
	*  Field Level Validation
	*  Only performed if they've submitted the form
	******************************************************************/
	if ($_POST['form_submitted'] == 'true'){
	
		// They hit the cancel button, return to the Manage Pages page
		if ($_POST['cancel']){
			header("Location:".$manage);
			exit();
		}
		
		/******************************************************************
		*  BEGIN EDITS
		*  If an edit fails, it adds an error to the message list.  
		******************************************************************/
		/******************************************************************
		*  END EDITS
		******************************************************************/
				




						
		/******************************************************************
		*  BEGIN Set Parameters
		******************************************************************/
		/******************************************************************
		*  END Set Parameters
		******************************************************************/
		
		// If there are no messages/errors, then go ahead and do the update (or add)
		// Note: if they were deleting a version, then there will be a message, so
		// this section won't get performed
		if (!$MessageList->hasMessages()){
		    $PluginSource=$_FILES['PluginZIP']['tmp_name'];
		    if ($PluginSource != "" and $PluginSource !="none"){
				$result = $SelfHostedPluginContainer->addUploadedPluginFile('PluginZIP',$SelfHostedPlugin);
				if (PEAR::isError($result)){
					$MessageList->addMessage($result->getMessage(),MESSAGE_TYPE_ERROR);
				}                        
			}
		}
		
		// Trigger an object update, useful for plugins that want to modify behaviour on object update
		if (!$MessageList->hasMessages()){
			$SelfHostedPluginContainer->updateSelfHostedPlugin($SelfHostedPlugin);
		}
	}
	
	
	/****************************************************************************
	*
	* BEGIN Display Code
	*    The following code sets how the page will actually display.  
	*
	****************************************************************************/
	// Declaration of the Form	
	$form = new HTML_TabbedForm($Bootstrap->getAdminURL(),'post','Update_Form','','multipart/form-data');	
	
	/***********************************************************************
	*
	*	SelfHostedPlugin Tab
	*
	***********************************************************************/
	$SelfHostedPluginTab = new HTML_Tab('SelfHostedPlugin','Self Hosted Plugin');
	$SelfHostedPluginTab->addPlainText('&nbsp;','
		<strong>Important</strong>: your plugin will be modified when you upload it.  Three things happen.
		<ol>
			<li>A directory called __plugin-updates is added, containing only the file plugin-update-checker.class.php</li>
			<li>Some code is inserted at the end of your main plugin file to instantiate the above class</li>
			<li>All instances of the file .DS_STORE are removed (what can I say, I develop on a Mac and that file just bugs me)</li>
		</ol>
		This is all done to enable the plugin to update itself from your server.
	');
	$SelfHostedPluginTab->addPlainText('&nbsp;','<hr>');
	if ($SelfHostedPlugin->getParameter('SelfHostedPluginID') == ""){
		$SelfHostedPluginTab->addPlainText('&nbsp;','To add a Self Hosted Plugin, simply upload a valid ZIP file containing the plugin');
	    $SelfHostedPluginTab->addFile('PluginZIP','Upload Plugin ZIP:');
	}
	else{
		$SelfHostedPluginTab->addPlainText('Plugin:','<h2>'.$SelfHostedPlugin->getParameter('SelfHostedPluginName').'</h2>');
		$SelfHostedPluginTab->addPlainText('&nbsp;','To add a new version, simply upload a valid ZIP file containing the plugin');
	    $SelfHostedPluginTab->addFile('PluginZIP','Upload Plugin ZIP:');
		$versions = $SelfHostedPlugin->getVersions();
		if (count($versions)){
			$SelfHostedPluginTab->addPlainText('&nbsp;','<hr>');
			$VersionHistory = '<ul>';
			foreach(array_keys($versions) as $version){
				$VersionHistory.= '<li>Version: <a href="'.$SelfHostedPlugin->get('download_url',$version).'">'.$version.'</a>  <em>Date: '.$versions[$version]['date'].'</em> <font size="-1">(<a href="'.$delete.'&amp;shpid='.$SelfHostedPlugin->getParameter('SelfHostedPluginID').'&amp;version='.$version.'">delete</a>)</font>';
			}
			$VersionHistory.= '</ul>';

			$SelfHostedPluginTab->addPlainText('Version History:',$VersionHistory);
		}
		$shortcodes = array();
		$shortcodes[] = '<pre style="display:inline">[self-hosted-plugins slug='.$SelfHostedPlugin->getParameter('SelfHostedPluginSlug').']</pre> - the download link';
		$shortcodes[] = '<pre style="display:inline">[self-hosted-plugins slug='.$SelfHostedPlugin->getParameter('SelfHostedPluginSlug').' history=true]</pre> - all versions with download links';
		$shortcodes[] = '<pre style="display:inline">[self-hosted-plugins slug='.$SelfHostedPlugin->getParameter('SelfHostedPluginSlug').' details=true]</pre> - the plugin details page';
		$SelfHostedPluginTab->addPlainText('Shortcodes:',join('<br/>',$shortcodes));
		
		$SelfHostedPluginTab->addPlainText('Downloads:',$SelfHostedPlugin->getParameter('SelfHostedPluginDownloads'));
	}
	
	// We've defined the tabs.  Now let's add them
	$form->addTab($SelfHostedPluginTab);
	$DefaultTab = 'SelfHostedPluginTab';

	/***********************************************************************
	*
	*	Message Tab
	*
	***********************************************************************/
	// We display messages on a new tab.  this will be the default tab that displays when the page gets redisplayed	
	if ($MessageList->hasMessages()){
		$MessageTab = new HTML_Tab('Messages',$MessageList->getSeverestType());
		$MessageTab->addPlainText('Messages',"<p>&nbsp;<p>".$MessageList->toBullettedString());
		$DefaultTab = 'MessageTab';
		$form->addTab($MessageTab);
	}
	
	$$DefaultTab->setDefault();
	
	// Here are the buttons
	$form->addSubmit('save','Save Changes');
	$form->addSubmit('cancel','Cancel');
	
	// Some hidden fields to help us out 
	$form->addHidden('form_submitted','true');
	$form->addHidden('shpid',$SelfHostedPlugin->getParameter('SelfHostedPluginID'));
	
	// Finally, we set the Smarty variables as needed.
	$smarty->assign('includes_tabbed_form',true);
	$smarty->assign('form',$form);
	$smarty->assign('form_attr','width=90% align=center');
	$smarty->assign('Tabs',$form->getTabs());
	//$smarty->assign('admin_head_extras',$admin_head_extras);
	
?>
<?php   $smarty->display('admin_form.tpl'); ?>