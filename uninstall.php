<?php 
//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) 
    exit();

$taxonomy_name = 'ctlt_rubric_evaluation';

$option_name = array(
	'rubric_evaluation_rubric_name',
	'rubric_evaluation_roles_settings'
);

// For Single site
if ( !is_multisite() ) 
{
	foreach ($option_name as $option) {
    	delete_option( $option );
	}
	
	unregister_taxonomy_for_object_type($taxonomy_name, 'post');
	
} 
// For Multisite
else 
{
    // For regular options.
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    $original_blog_id = get_current_blog_id();
    foreach ( $blog_ids as $blog_id ) 
    {
        switch_to_blog( $blog_id );
    	foreach ($option_name as $option) {
    		delete_option( $option );
		} 
		unregister_taxonomy_for_object_type($taxonomy_name, 'post');
    }
    switch_to_blog( $original_blog_id );

    // For site options.
    foreach ($option_name as $site_option) {
    	delete_site_option( $option );
    }
      
}