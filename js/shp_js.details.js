jQuery(function(){
	jQuery('#shp-plugin-header').find('a').click(function(){
		jQuery('#shp-plugin-header').find('a.current').removeClass('current');
		jQuery(this).addClass('current');
		jQuery('#section-holder .section').hide();
		jQuery('#section-'+jQuery(this).attr('name')).show();
		return false;
	});
});