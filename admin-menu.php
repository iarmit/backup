<?php
add_action( 'admin_menu', 'secure_store_menu');
add_action( 'admin_init', 'secure_store_settings_init');

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


/* TODO: validate input properly */
function secure_store_options_validate($input) {
	return $input;
}

function secure_store_files() {
	if ( !current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission to access this page.' ) );
	}
	$options = get_option('ss_settings');
	echo '<div class ="wrap">';
	echo '<p> Here is a list of files stored</p>'. $options['test_option'];
	echo '</div>';
}
?>
