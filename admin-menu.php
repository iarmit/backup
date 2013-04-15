<?php
add_action( 'admin_menu', 'secure_store_menu');
add_action( 'admin_init', 'secure_store_settings_init');


function sfs_julian_date(){
	$julianDate = gregoriantojd(date("n"), date("j"), date("Y"));

	//correct for fraction of a day
	$dayfrac = date('G') / 24 - .5;
	if ($dayfrac < 0) $dayfrac += 1;

	$frac = $dayfrac + (date('i') + date('s') / 60) / 60 / 24;
	return $julianDate = $julianDate + $frac;	
}

function sfs_create_token($client_api) {
  $ttl = 4;
	$julian_date = sfs_julian_date();
	$salt = $julian_date . "_" . $ttl;
	$hash = hash("sha256", $client_api . $salt, false);

	$token = $hash . "." . $salt;
	return $token;
}


function secure_store_settings_init() {
	register_setting( 'secure_store_settings', 'ss_settings', 
		'secure_store_options_validate');
	add_settings_section('ss_server_settings', 'Server Settings',
		'secure_store_text', 'secure_store');
	add_settings_field('server_uri', 'Server URI',
		'secure_store_option_uri', 'secure_store', 'ss_server_settings');
	add_settings_field('client_id', 'Client ID',
		'secure_store_option_id', 'secure_store', 'ss_server_settings');
	add_settings_field('client_api', 'Client API Key',
		'secure_store_option_key', 'secure_store', 'ss_server_settings');
	add_settings_field('page_uri', 'Page URI',
		'secure_store_option_page', 'secure_store', 'ss_server_settings');

}


function secure_store_menu() {
	add_menu_page( 'Secure File Store', 'Secure File Store', 'manage_options',
		'secure-store', 'secure_store_topmenu');
	add_submenu_page( 'secure-store', 'Stored Files', 'Stored Files',
		'manage_options', 'file-storage', 'secure_store_files');
}

function secure_store_topmenu() {
	if ( !current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission to access this page.' ) );
	}
?> 
<div class ="wrap">
  <h2>Secure File Store</h2>
	<form method="post" action="options.php">	
		<?php settings_fields('secure_store_settings'); ?>
  	<?php do_settings_sections('secure_store'); ?>
		<?php submit_button(); ?>
	</form>
<?php
	echo '</div>';
}

function secure_store_text() {
	echo '<p>The Server Details</p>';
}

function secure_store_option_uri() {
	$options = get_option('ss_settings');
	echo '<input id="server_uri" name="ss_settings[server_uri]" size="40" type="text" value="'. $options["server_uri"] . '" />';
}

function secure_store_option_id() {
	$options = get_option('ss_settings');
	echo '<input id="client_id" name="ss_settings[client_id]" size="40" type="text" value="'. $options["client_id"] . '" />';
}

function secure_store_option_key() {
	$options = get_option('ss_settings');
	echo '<input id="client_api" name="ss_settings[client_api]" size="40" type="text" value="'. $options["client_api"] . '" />';
}

function secure_store_option_page() {
	$options = get_option('ss_settings');
	echo '<input id="page_uri" name="ss_settings[page_uri]" size="40" type="text" value="'. $options["page_uri"] . '" />';
	echo '<i>The page number of the page wih the form</i>';}

/* TODO: validate input properly */
function secure_store_options_validate($input) {
	return $input;
}

function sfs_action_clicked() {
	if (array_key_exists('sfs_guid_get', $_POST)) {
		sfs_get_file($_POST['sfs_guid_get']);
	} elseif (array_key_exists('sfs_guid_delete', $_POST)) {
		sfs_delete_file($_POST['sfs_guid_delete']);
	}
}

function sfs_delete_file($guid) {
	global $wpdb;
	$server_options = get_option('ss_settings');
	$token = sfs_create_token($server_options['client_api']);
	$ch = curl_init();
	$post_data = array(
		'client_system_id' => $server_options['client_id'],
		'token' => $token,
		'guid' => $guid,
		'timestamp' => 'None',
	);
	curl_setopt($ch, CURLOPT_URL, $server_options['server_uri'] . 'delete');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	
	$response = curl_exec($ch);

	if (strstr($response, 'OK')) {
		$table_name = $wpdb->prefix . "secure_file_store";
		$wpdb->query(
			$wpdb->prepare(
				"
				DELETE FROM $table_name
				WHERE guid = %s
				",
				$guid
			)
		);
	} else {
		$_POST['feedback'] = $response;
	}

}

function sfs_get_file($guid){
	$server_options = get_option('ss_settings');
	$token = sfs_create_token($server_options['client_api']);

	$ch = curl_init();
	$post_data = array(
		'client_system_id' => $server_options['client_id'],
		'token' => $token,
		'guid' => $guid,
	);
	curl_setopt($ch, CURLOPT_URL, $server_options['server_uri'] . '/get');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_HEADER, 1);
		
	$response = curl_exec($ch);
	
	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$header = substr($response, 0, $header_size);
	$body = substr($response, $header_size);
	if (strstr($body, "ERROR:")) {
		$_POST['feedback'] = $body;
	} else {
		$arr = explode("\n", $header);
		foreach($arr as $line) {
			header($line);
		}
		echo ($body);
		exit();
	}
}

add_action('plugins_loaded', 'sfs_action_clicked');

function secure_store_files() {
	global $wpdb;
	if ( !current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission to access this page.' ) );
	}
	$table_name = $wpdb->prefix . "secure_file_store";
	$stored_files = $wpdb->get_results(
		"SELECT * FROM $table_name", ARRAY_A);
	
	echo '<div class ="wrap">';
	echo '<p> Here is a list of files stored</p>';
	if (array_key_exists( 'feedback' , $_POST)) {
		echo '<span style="background:#CC0000; color:#FFFFFF; padding: 4px;">';
		echo $_POST['feedback'] . '</span>';
	}	
	?>
	<table>
		<tr><th>Filename</th>
			<th>Uploaded By</th>
			<th>Description</th>
			<th>Date Uploaded</th>
			<th>Actions</th>
		</tr>
	<?php
	foreach ( $stored_files as $sf) {
		echo '<tr>';
		echo '<td>' . $sf['filename'] . '</a></td>';
		echo '<td>' . $sf['uploaded_by'] . '</td>';
		echo '<td>' . $sf['description'] . '</td>';
		echo '<td>' . $sf['uploaded'] . '</td>';		
		echo '<td>' . sfs_download_form($sf['guid']) . sfs_delete_form($sf['guid']) . '</td>';
		echo "</tr>";
	}
	echo '</table></div>';
}


function sfs_download_form($guid) {
	$str = "<form name='get-file' class ='sfs_action_form' method='POST' action='/wordpress/wp-admin/admin.php?page=file-storage'>";
	$str .= '<input type="hidden" name="sfs_guid_get" value="'. $guid .'" />';
	$str .= '<input type="submit" value="Download" /></form>';
	return $str;
}

function sfs_delete_form($guid) {
	$str = "<form name='delete-file' class= 'sfs_action_form' method='POST' action='/wordpress/wp-admin/admin.php?page=file-storage'>";
	$str .= "<input type='hidden' name='sfs_guid_delete' value='$guid' />";
	$str .= "<input type='submit' value='Delete' /></form>";
	return $str;
}
?>
