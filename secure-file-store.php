<?php
/*
Plugin Name: Secure File Store
Description: A wordpress client for the Secure File Store at Resolute
Version: 1.0
*/

require_once dirname( __FILE__ ) . '/admin-menu.php';
global $secure_file_store_db_version;
$secure_file_store_db_version = "1.0";

function secure_file_store_install() {
	global $wpdb;
	global $secure_file_store_version;
	
	$table_name = $wpdb->prefix . "secure_file_store";

	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		filename text NOT NULL,
		guid text NOT NULL,
		uploaded_by text NOT NULL,
		description text DEFAULT '',
		UNIQUE KEY id (id)
	);";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( "secure_file_store_db_version", $secure_file_store_db_version );
}

register_activation_hook( __FILE__,'secure_file_store_install' );


add_shortcode( 'secure_file_store', 'sfs_handler');

function sfs_handler($attributes) {

	$str = '<form name="file-upload" method="POST" enctype="multipart/form-data" action="';
	$str .= get_bloginfo('url') . '/index.php?secure-file-store=request-handler" />';
	$str .= '<dl><dt>File: </dt>';
	$str .= '<dd><input type="file" name="file" /></dd>';
	$str .= '<dt>Description: </dt>';
	$str .= '<dd><textarea cols="8" rows="4" name="description"></textarea></dd>';
	$str .= '<input type="submit" value="Submit" /></dl></form>';
	return $str;
}

function secure_file_store_parse_request($wp) {
	//only process requests with "secure-file-store=request-handler"
	if (array_key_exists('secure-file-store', $wp->query_vars) 
		&& $wp->query_vars['secure-file-store'] == 'request-handler')
	{
		$file = $_FILES['file']['name'];
		$description = $_POST['description'];
		$server_options = get_option('ss_settings');
		$str = $server_options['server_uri'] . ' ' . $server_options['client_id'] . ' ' . $server_options['client_api'];	
		wp_die($str);
	}
}

add_action('parse_request', 'secure_file_store_parse_request');

function secure_file_store_query_vars($vars) {
	$vars[] = 'secure-file-store';
	return $vars;
}

add_filter('query_vars', 'secure_file_store_query_vars');
?>
