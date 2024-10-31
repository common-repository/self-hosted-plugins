<?php
    require_once(PACKAGE_DIRECTORY."Common/Package.php");

    class SelfHostedPluginsPackage extends Package{
        
        function SelfHostedPluginsPackage(){
            $this->Package();            

            include_once("SelfHostedPluginContainer.php");
        
            $this->package_name = 'SelfHostedPlugins';
            $this->package_title = 'Self Hosted Plugins';
            $this->package_description = 'Use this plugin to host your own plugins on your WordPress site';
            $this->auth_level = USER_AUTH_EVERYONE;
            $this->is_active = true;
            
            $this->admin_pages = array();
            $this->admin_pages['manage'] = array('url' => 'admin/manage_self_hosted_plugins.php', 'title' => 'Self Hosted Plugins');
            $this->admin_pages['edit'] = array('url' => 'admin/update_self_hosted_plugin.php', 'title' => 'Update Self Hosted Plugin');
            $this->admin_pages['delete'] = array('url' => 'admin/delete_self_hosted_plugin.php', 'title' => 'Delete Self Hosted Plugin');
            
            $this->main_menu_page = 'manage';
            
            $this->loadUserConf();
            
        }
        
    }
    
    
?>