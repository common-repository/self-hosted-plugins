<?php
    if (!$Bootstrap){
        die ("You cannot access this file directly");
    }
    
	$Bootstrap->addPackagePageToAdminBreadcrumb($Package,'manage');
	$Bootstrap->addPackagePageToAdminBreadcrumb($Package,'delete');
	$manage = $Bootstrap->makeAdminURL($Package,'manage');
	$edit = $Bootstrap->makeAdminURL($Package,'edit');

	define (HTML_FORM_TH_ATTR,"valign=top align=left width='3%'");
	define (HTML_FORM_TD_ATTR,"valign=top align=left");
	include_once(PACKAGE_DIRECTORY.'../TabbedForm.php');

	$id = $_REQUEST['shpid'];
	$SelfHostedPluginContainer = new SelfHostedPluginContainer();
	if ($id){
		$Plugin = $SelfHostedPluginContainer->getSelfHostedPlugin($id);
	}
	if (!$Plugin){
		header("Location:".$manage);
		exit();
	}

	if ($_POST['form_submitted'] == 'true'){
		if ($_POST['confirm']){
			if (isset($_POST['version'])){
				$result = $Plugin->deleteVersion($_POST['version']);
			}
			else{
				$result = $SelfHostedPluginContainer->deleteSelfHostedPlugin($id);
			}
		}
		if (PEAR::isError($result)){
			$MessageList->addPearError($result);
		}
		else{
			if (isset($_POST['version'])){
				header("Location:".$edit.'&shpid='.$id);
			}
			else{
				header("Location:".$manage);
			}
			exit();
		}
	}
	if (isset($_GET['version'])){
		$MessageList->addMessage("Do you really want to delete version ".$_GET['version']." of this plugin? (This can't be undone)?");
	}
	else{
		$MessageList->addMessage("Do you really want to delete the plugin \"".$Plugin->getParameter('SelfHostedPluginName')."\" (This can't be undone)?");
	}
	
	// Declaration of the Form	
	$form = new HTML_Form($Bootstrap->getAdminURL(),'post','Delete_Form');
	if ($MessageList->hasMessages()){
		$form->addPlainText('',$MessageList->toSimpleString());
	}
	$form->addSubmit('confirm','Yes, delete it');
	$form->addSubmit('cancel','No, don\'t!');
	$form->addHidden('form_submitted','true');	
	$form->addHidden('shpid',$Plugin->getParameter('SelfHostedPluginID'));
	if (isset($_GET['version'])){
		$form->addHidden('version',$_GET['version']);
	}
	
	$smarty->assign('form',$form);
	$smarty->assign('form_attr','width=90% align=center');
	
?>
<?php   $smarty->display('admin_form.tpl'); ?>
