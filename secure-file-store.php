<?php
/*
Plugin Name: Secure File Store
Description: A wordpress client for the Secure File Store at Resolute
Version: 1.0
*/

require_once dirname( __FILE__ ) . '/admin-menu.php';
global $secure_file_store_db_version;

$secure_file_store_db_version = "1.0";

add_action('admin_enqueue_scripts', 'sfs_add_css');

function sfs_add_css() {
	wp_register_style( 'sfs-style', plugins_url('style.css', __FILE__) );
	wp_enqueue_style( 'sfs-style' );
}

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
		uploaded datetime NOT NULL,
		UNIQUE KEY id (id)
	);";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( "secure_file_store_db_version", $secure_file_store_db_version );
}

register_activation_hook( __FILE__,'secure_file_store_install' );


add_shortcode( 'secure_file_store', 'sfs_handler');

function sfs_handler($attributes) {
	$str = '';
	if (array_key_exists('feedback', $_POST)) {
		if (strstr($_POST['feedback'], 'ERROR')) {
		 $str = '<span style="background:#CC0000; color:#FFFFFF; padding: 4px;">'; 
		} else {
		 $str = '<span style="background:#0066FF; color:#FFFFFF; padding: 4px;">';
		}
		$str .= $_POST['feedback'] . '</span>';
	}
	$str .= '<form name="file-upload" method="POST" enctype="multipart/form-data" action="';
	$str .= $_SERVER['REQUEST_URI'] . '" />';
	$str .= '<dl><dt>File: </dt>';
	$str .= '<dd><input type="file" name="file" /></dd>';
	$str .= '<dt>Description: </dt>';
	$str .= '<dd><textarea cols="8" rows="4" name="description"></textarea></dd>';
	$str .= '<input type="submit" value="Submit" /></dl></form>';
	return $str;
}



function secure_file_store_parse_request($wp) {
	global $wpdb;
	//only process requests with "secure-file-store=request-handler"
//	if (array_key_exists('secure-file-store', $wp->query_vars) 
	//	&& $wp->query_vars['secure-file-store'] == 'request-handler')
	$server_options = get_option('ss_settings');
	$url = $server_options['page_uri'];
	if (array_key_exists('file', $_FILES) && $url ==  $_SERVER['QUERY_STRING'])
	{
		$file = $_FILES['file'];
		$description = $_POST['description'];
		$token = sfs_create_token($server_options['client_api']);

		$ch = curl_init();
		$post_data = array(
			'client_system_id' => $server_options['client_id'],
			'token' => $token,
			'filename' => $file['name'],
			'file' => '@'.$file['tmp_name'],
		);
		curl_setopt($ch, CURLOPT_URL, $server_options['server_uri'] . '/store');
		//curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
			
		$response = curl_exec($ch);
		if (curl_errno($ch) == 0 && !strstr($response, "ERROR")) {
			$table_name = $wpdb->prefix . "secure_file_store";
			$wpdb->insert($table_name, array(
					'filename' => $file['name'],
					'guid' => $response,
					'uploaded_by' => wp_get_current_user()->user_login,
					'description' => $description,
					'uploaded' => date('Y-m-d H:i:s'),
				), array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
				)
			);
			//set the feedback message
			$_POST['feedback'] = "File successfully stored";
		} else {
			//an error occured
			if (curl_errno($ch) == 0) {
				$_POST['feedback'] = $response;
			} else {
				$_POST['feedback'] = "ERROR: Could not store file";
			}
		}
	}
}

add_action('parse_request', 'secure_file_store_parse_request');

function secure_file_store_query_vars($vars) {
	$vars[] = 'secure-file-store';
	return $vars;
}

add_filter('query_vars', 'secure_file_store_query_vars');
?>
