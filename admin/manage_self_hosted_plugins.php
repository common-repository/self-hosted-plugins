<?php
    if (!$Bootstrap){
        die ("You cannot access this file directly");
    }
    
	$Bootstrap->addPackagePageToAdminBreadcrumb($Package,'manage');
	global $manage, $edit, $delete;
	$manage = $Bootstrap->makeAdminURL($Package,'manage');
	$edit = $Bootstrap->makeAdminURL($Package,'edit');
	$delete = $Bootstrap->makeAdminURL($Package,'delete');

	include_once(PACKAGE_DIRECTORY."Common/ObjectLister.php");
	
	$ObjectLister = new ObjectLister();

	$SelfHostedPluginContainer = new SelfHostedPluginContainer();
	
	$SelfHostedPlugins = $SelfHostedPluginContainer->getAllSelfHostedPlugins();
	
	$ObjectLister->addColumn('Plugin Name (<a href="'.$edit.'">add one</a>)','displayPluginName','80%');
	$ObjectLister->addColumn('&nbsp;','displayNavigation','20%');
	$smarty->assign('ObjectListHeader', $ObjectLister->getObjectListHeader());
	$smarty->assign('ObjectList', $ObjectLister->getObjectList($SelfHostedPlugins));
	$smarty->assign('ObjectEmptyString',"There are currently no self-hosted plugins loaded.  <a href='".$edit."'>Click to Add a Self Hosted Plugin</a>");
		 	
	function displayPluginName($Object){
        global $edit;
	 	if (is_a($Object,'Parameterized_Object')){
	 		$ret = "<a href='".$edit."&shpid=".$Object->getParameter('SelfHostedPluginID')."'>".$Object->getParameter('SelfHostedPluginName')."</a>";
			return $ret;
	 	}
	 	else{
	 		return "Invalid Object Passed: ".get_class($Object);
	 	}
	}
	
	function displayNavigation($Object){
        global $delete;
	 	if (is_a($Object,'Parameterized_Object')){
	 	    $ret = "<a href='".$delete."&shpid=".$Object->getParameter('SelfHostedPluginID')."'>delete</a>";
			return $ret;
	 	}
	 	else{
	 		return "Invalid Object Passed: ".get_class($Object);
	 	}
	    
	}
?>
<div style="width:50%"><?php	$smarty->display('admin_listing.tpl');	?></div>