=== Self Hosted Plugins ===
Contributors: topquarky
Tags: plugins,self-hosted,host your own plugin,updates,repository
Requires at least: 3.0
Tested up to: 3.6
Stable tag: 1.0.10

This plugin helps you to self-host your WordPress plugins on your own site.  It even uses an extend/plugins/ structure

== Description ==

When I first went to host my plugins on my own site, I was surprised that there wasn't anything out there to help me with the tricky job of deploying updates.  WordPress has updating built into it, but it's not easy to apply that to plugins hosted on your own site.  

Until now...

Using this plugin, you can maintain a repository of your self-hosted plugins on your own site.  It makes use of the confusing web of WordPress hooks that are necessary to allow remote sites to look for and install updates from your own server.  This plugin will keep track of the number of downloads for you.  Ratings are not currently supported, but I hope to add that into a later version.  

= Call To Developers =

I think this could be a really interesting project that I could see quickly growing beyond what I'd be able to handle personally.  Is anyone interested in getting involved?  Let's start a conversation in the forum.  

= Back to your regularly scheduled plugin... =

It includes shortcodes to allow you to display your plugin information, in the familiar plugin-details layout, right in your own site.  

The download link includes hooks to allow you to program authentication (if you want to charge for your plugin)

**Important** Plugins stored in your repository will be altered to allow the update process to function:

1. A directory called __plugin-updates is added, containing only the file plugin-update-checker.class.php
1. Some code is inserted at the end of your main plugin file to instantiate the above class
1. All instances of the file .DS_STORE are removed (what can I say, I develop on a Mac and that file just bugs me)

For more information, see the FAQ section. 

= Beta = 
This plugin adds rewrite rules making it so that it appears your plugin is stored in an `extend/plugins` directory from your site, mimicking wordpress.org.  So, The details page for the latest version of your plugin is always at http://mysite.com/extend/plugins/my-plugin.  No pages or posts are added to make this possible - the page is generated (i.e. spoofed) dynamically when an extend/plugins call comes in.  

If your default template isn't a full-width template, you might consider making use of the filter `template_include` in the following manner

`add_filter('template_include','my_template_redirect');
function my_template_redirect($template){
	if (defined('SHP_SPOOFED_PLUGIN_PAGE')){ // Only set if we've spoofed a page.
		$template = get_bloginfo('template_url').'/template-my-template.php'; 
	}
	return $template;
}`

If you want to disable this feature, add the following filter:

`add_filter('shp_enable_redirect',create_function('$a','return false;'));`

== Installation ==

1. Install Self Hosted Plugins directly from the repository like you would any other plugin

**Important** Plugins stored in your repository will be altered to allow the update process to function:

1. A directory called __plugin-updates is added, containing only the file plugin-update-checker.class.php
1. Some code is inserted at the end of your main plugin file to instantiate the above class
1. All instances of the file .DS_STORE are removed (what can I say, I develop on a Mac and that file just bugs me)

== Frequently Asked Questions ==

= What are the available shortcodes? =

To make use of your new plugin repository, use the following shortcodes in your page/post

* `[self-hosted-plugins]` - lists all of your self-hosted plugins, with links to the detail pages
* `[self-hosted-plugins slug=my-slug]` - the download link for the latest version of your plugin `my-slug`
* `[self-hosted-plugins slug=my-slug version=1.0.2]` - the download link for the version 1.0.2 of your plugin `my-slug`
* `[self-hosted-plugins slug=my-slug history=true]` - an unordered list of the versions of your `my-slug` plugin, with download links
* `[self-hosted-plugins slug=my-slug history=true format='Plugin Version %s']` - an unordered list of the versions of your `my-slug` plugin, with download links, where the link text is sprintf('Plugin Version %s',$version)
* `[self-hosted-plugins slug=my-slug details=true]` - the Plugin details page for the latest version of your `my-slug` plugin

Note, if you're using the `slug` attribute, you can add a `version` attribute to the shortcode to display information for that version.  If this attribute is not present, it defaults to the latest version

= Will this make any changes to my plugin? =

Plugins stored in your repository will be altered to allow the update process to function:

1. A directory called __plugin-updates is added, containing only the file plugin-update-checker.class.php
1. Some code is inserted at the end of your main plugin file to instantiate the above class
1. All instances of the file .DS_STORE are removed (what can I say, I develop on a Mac and that file just bugs me)

= What filters are available? =

The following filters are called by this plugin:

* `apply_filters('allow_self_hosted_plugin_download',true,$Plugin,$version)` - called on a download request.  If you return false, then the file will not be served and the plugin will call `wp_die('You do not have permission to download this plugin')`.  Called in `self-hosted-plugins.php`.
* `apply_filters('redirect_self_hosted_plugin_download','',$Plugin,$version)` - called on a download request.  If a URL is returned, the plugin does a `wp_redirect` to that page.  This is useful if you want to redirect non-authorized downloaders to a sales page (for example).  Called in `self-hosted-plugins.php`.
* `apply_filters('shp_details_action_button',$action_button,array(&$Plugin,$version))` - allows you to override the download button/link on the details page.  The default is `<p class="action-button"><a href="'.$Plugin->get('download_url',$version).'">'.__('Download').'</a></p>`.  Called in `SelfHostedPlugin.php`.
* `apply_filters('shp_remove_files_from_zip',array('.DS_Store'))` - if you want to globally remove particularly named files from your plugin.zip file, then use this filter.  I develop on a Mac and I hate that pesky .DS_Store file.  Called from `SelfHostedPluginContainer.php`

= How do I name my screenshots? =

The plugin will properly display screenshot images named `screenshot-N.(jpg|jpeg|png|gif)` where N is the screenshot number (1, 2, 3, etc)

= I'm testing and I want to force an update request on the remote server =

WordPress only checks for updates on a plugin every 12 hours or so.  You can force an update check on all self-hosted plugins by manually adding a query variable called `forceCheck` (the value doesn't matter) to the url on the plugins page.  For example, http://mysite.com/wp-admin/plugins.php?forceCheck=foo.  Useful for testing.  

= Any plans to extend this to themes as well? = 

Not at this point.  I'm a plugin writer, not a theme writer.  If you're interested in collaborating on this project to include themes, find me at [topquark.com](http://topquark.com/)

= Is it internationalization ready? = 

No.  I don't have any experience developing internationalizable plugins.  Wanna help?  Find me at [topquark.com](http://topquark.com/)

= I get the message "You must have a valid WordPress readme.txt file within the plugin zip" when uploading my ZIP= 

First, make sure there is a `readme.txt` file in your zip.  Second, when you zip the plugin up, you should zip -r a directory as opposed to zip * a bunch of files.  SHP uses the root directory to form the path that it uses to look for the readme.txt file.  When you zip a bunch of files, it things the root file is simply the first file in the archive.

So, put the files you want to zip into a directory 'my_plugin' and then in terminal,  navigate to whatever directory that 'my_plugin' directory is in and type:

zip -r my_plugin.zip my_plugin/

That will archive the directory in a way that SHP wants it.

== Screenshots ==

1. The plugin detail page (using `[self-hosted-plugins slug=hello-world details=true]`) of a "Hello World" plugin
2. Admin interface for updating the plugin
3. Proof! A remote site seeing the update notice for the plugin

== Changelog ==

= 1.0.9 =
* Change to PluginUpdateChecker class to properly display readme.txt upgrade notices

= 1.0.8 =
* changed the rewrite rules to have the SHP rules take precedence (i.e. come first)
* added some useful filters
* now displays the upgrade_notice sent back from the server.  Useful for sending notifications re: authorization

= 1.0.7 =
* Changed it so that links in [self-hosted-plugins] use extend/plugins url
* Added a filter when creating the fake post to display the plugin details

= 1.0.6 =
* Fixed newbie SVN upload mistake from version 1.0.5

= 1.0.5 =
* WARNING don't use this version.  I made a newbie SVN mistake

= 1.0.4 =
* Fixed bug that ended the script prematurely

= 1.0.3 =
* Fixed the extend/plugins rewriting to play nicer with other themes

= 1.0.2 =
* Added filter within the rewrite_rules to move offending rules to the end

= 1.0 =
* Initial check-in

== Requirements ==

There are a few things that you need to do to make sure that you can use this plugin.  

= PHP 5 >= 5.2.0, PECL zip >= 1.5.0 =

This plugin makes use of the ZipArchive class, introduced in PHP 5.2.0.  You must be running at least this version to use the plugin

= Requires the Top Quark Architecture plugin = 

I develop plugins using a framework that I've developed for allowing rapid database-driven plugin development.  The Self Hosted Plugins uses that framework, so you must install the [Top Quark Architecture](http://wordpress.org/extend/plugins/topquark/) plugin.  

= Valid readme.txt and plugin headers = 

Like plugins hosted on wordpress.org, you must include a valid readme.txt file and the proper plugin headers in your main plugin file.  Check out a [sample readme.txt file](http://wordpress.org/extend/plugins/about/readme.txt) and use the [readme validator](http://wordpress.org/extend/plugins/about/validator/).  The Self Hosted Plugins plugin uses the readme.txt file and the plugin headers to render information about the plugin.

== Acknowledgements ==

Thanks to [Janis Elsts](http://w-shadow.com) for the `plugin-update-checker.class.php` file.  See [http://w-shadow.com/blog/2010/09/02/automatic-updates-for-any-plugin/](http://w-shadow.com/blog/2010/09/02/automatic-updates-for-any-plugin/)

Thanks also to all members of the [wordpress-plugin-readme-parser](http://code.google.com/p/wordpress-plugin-readme-parser/) project.  This code lets mere mortals parse a readme.txt file

Thanks to Scott [Sherrill-Mix](http://scott.sherrillmix.com) for his [FakePage class](http://scott.sherrillmix.com/blog/blogger/creating-a-better-fake-post-with-a-wordpress-plugin/) 

== Upgrade Notice ==

= 1.0.10 =
Updated maximum file size for uploading to reflect ini_get('upload_max_filesize') - previously it was capped at 10M

= 1.0.9 =
A change was made to the PluginUpdateChecker class.  Please re-upload any Self Hosted Plugins to pick up the change (no version change required).

= 1.0 =
No upgrade notices