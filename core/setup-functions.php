<?php

/**
 * Reads debug configurations and accordingly displays or hides errors
 * recommended to turn FEEDSYNC_DEBUG = true on developement server
 * and false on production server
 * @return void
 */
function setup_environment() {

    ini_set("log_errors" , "1");
    ini_set("error_log" , LOG_PATH.LOG_FILE);

    if ( defined('FEEDSYNC_DEBUG') && (FEEDSYNC_DEBUG == true || FEEDSYNC_DEBUG == TRUE || FEEDSYNC_DEBUG == 1 ) ) {
        ini_set("display_errors" , "1");
    } else {
        ini_set("display_errors" , "0");
    }

    date_default_timezone_set(get_option('feedsync_timezone'));

}
add_action('init','setup_environment',5);

function make_current_url() {
    $url =  sprintf(
        "%s://%s%s",
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $_SERVER['SERVER_NAME'],
        $_SERVER['REQUEST_URI']
    );
    $url = strtok( $url, '?' );
    return trailingslashit( $url );
}

/**
 * Set default settings to DB on new installation.
 * 
 * @since 3.4.4 is_request_available set to yes for fresh installs.
 * @since 3.4.5 feedsync_access_key is set during installation.
 */
function set_default_settings() {

    global $current_version;

    if( get_option('site_url',false) == '' && defined( 'SITE_URL' ) ){
        update_option('site_url', constant('SITE_URL') );
    }

    $options_to_db = array(
        'FEEDSYNC_MAX_FILESIZE' =>  '',
        'FEEDSYNC_CHUNK_SIZE'   =>  '',
        'FEEDTYPE'              =>  'reaxml',
        'GEO_ENABLED'           =>  '',
        'FORCE_GEOCODE'         =>  '',
        'FEEDSYNC_PAGINATION'   =>  '',
        'FEEDSYNC_GALLERY_PAGINATION' =>  '',
        'FEEDSYNC_TIMEZONE'     =>  '',
        'REC_LICENSED_URL'      =>  '',
        'REC_LICENSE'           =>  ''
    );

    foreach($options_to_db as $option_to_db => &$opt_value) {

        $opt_value      = defined( $option_to_db ) ? constant($option_to_db) : '';
        $opt_key        = strtolower($option_to_db);

        if($option_to_db == 'REC_LICENSED_URL') {
            $opt_key = strtolower('FEEDSYNC_LICENSE_URL');
        }

        if($option_to_db == 'REC_LICENSE') {
            $opt_key = strtolower('FEEDSYNC_LICENSE_KEY');
        }

        if( get_option($opt_key,false) == '' && defined( $option_to_db ) ){

            if($opt_key == 'feedsync_timezone') {
                $timezones = get_single_timezone_array();
                $opt_value = in_array($opt_value,$timezones) ? $opt_value : 'Australia/Sydney';
                update_option($opt_key, $opt_value );
            } else {
                update_option($opt_key, strtolower($opt_value) );
            }

        }
    }

    /** set default settings */
    $default_opts = array(
        'feedsync_enable_access_key'    =>  'on',
        'feedsync_current_version'      =>  $current_version,
        'feedsync_license_url'          =>  get_option('site_url'),
        'feedsync_max_logs'             =>  '1000',
        'feedsync_enable_logging'       =>  'on',
        'is_request_available'          =>  'yes',
        'reaxml_map_status_current'     =>  'publish',
        'reaxml_map_status_leased'      =>  'publish',
        'reaxml_map_status_sold'        =>  'publish',
        'reaxml_map_status_withdrawn'   =>  'private',
        'reaxml_map_status_offmarket'   =>  'draft',
        'reaxml_map_status_deleted'     =>  'trash',
        'feedsync_access_key'           =>  uniqid()
    );

    foreach($default_opts as $default_opt_key => $default_opt) {
        if( get_option($default_opt_key) == '' ){
            update_option($default_opt_key, $default_opt );
        }
    }
}

/**
 * Initiate Database connection
 * also declares $feedsync_db global object to the database connection
 * creates tables required for feedsync if not already there.
 * upgrades table incase of feedsync upgradation from a lower version
 * @return void
 *
 * @since 3.4.0 Added check for missing fields in agents table
 * @since 3.4.5 Refactored code : Using FEEDSYNC_TABLES class for all tables functions.
 */
function init_db_connection() {

    $fs_tables = new FEEDSYNC_TABLES();

    $fs_tables->init_tables();
    $fs_tables->upgrade_listing_table();
    $fs_tables->upgrade_agent_table();

    upgrade_options();

    fsdb()->show_errors = false;

    if( is_home() && get_option('site_url') == '' ) {
        update_option('site_url', make_current_url() );
    }
}

/*
    ** Initialize database connection
    */
add_action('init_db','init_db_connection');
do_action('init_db');
do_action('init_options');
do_action('init_url_constants');
do_action('init_session');

/** set default settings */
set_default_settings();


/**
 * Save feedsync settings
 */
add_action('feedsync_form_feedsync_settings','save_feedsync_settings');

function feedsync_save_changed_status( $data ) {
    
    $mappings = [
        'reaxml_map_status_current'     =>  'publish',
        'reaxml_map_status_leased'      =>  'publish',
        'reaxml_map_status_sold'        =>  'publish',
        'reaxml_map_status_withdrawn'   =>  'private',
        'reaxml_map_status_offmarket'   =>  'draft',
        'reaxml_map_status_deleted'     =>  'trash'
    ];

    $diff = [];
    
    foreach( $mappings as $status =>    $value ) {

        $map = get_option( $status );
        
        if( empty( $map ) ) {
            $map = $value;
        }
        
        if( $data[ $status ] != $map ) {
            $diff[ $status ] = $data[ $status ];
        }
        
    }
    
    if( !empty( $diff ) ) {
        update_option('reaxml_publish_processed', 'no');
    }
}

function save_feedsync_settings() {


    $data  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
    feedsync_save_changed_status( $data );
    if( !empty($data) ) {
        foreach($data as $key => $value) {
            update_option($key,$value);
        }
    }

    // reinstantiate options to avoid refreshing 2nd time to see the save value
    instantiate_options();

    // delete geocode API cookie, in case API is resaved
    setcookie("fs_geoapi_status", "", time() - 3600);
    setcookie("fs_geoapi_error", "", time() - 3600);
}


function update_option($key,$value) {

    $exist = fsdb()->query("SELECT * FROM ".fsdb()->options." WHERE option_name = '{$key}' ");
    $value = (is_array($value) || is_object($value) ) ? serialize($value) : $value;
    if($exist) {
        // update the option
       $status = fsdb()->query("UPDATE ".fsdb()->options." SET option_value =  '".$value."' WHERE option_name = '{$key}' ");
    } else {
        // insert the option
       $status = fsdb()->query("INSERT INTO ".fsdb()->options."(option_name,option_value) VALUES ('{$key}','".$value."') ");
    }
    return $status;
}

function delete_option($key) {


    $status = false;
    if($key == '') {
        return $status;
    }

    $exist = fsdb()->query("SELECT * FROM ".fsdb()->options." WHERE option_name = '{$key}' ");
    if($exist) {
        // delete the option
       $status = fsdb()->query("DELETE FROM ".fsdb()->options." WHERE option_name = '{$key}' ");
    }
    return $status;
}

function get_post_meta($post_id,$meta_key='',$single=false) {



    if( $post_id <= 0 )
        return;

    if($meta_key == '')
        $meta = fsdb()->get_Results("SELECT meta_value FROM ".fsdb()->listing_meta." WHERE listing_id = '{$post_id}' ");
    else
        $meta = fsdb()->get_row("SELECT meta_value FROM ".fsdb()->listing_meta." WHERE meta_key = '{$meta_key}' AND listing_id = '{$post_id}' ");

    if( empty($meta) ) {
        return false;
    }
    if($single) {
        $meta = current($meta);
    }

    return is_serialized( $meta ) ? unserialize( $meta ) : $meta;
}

function update_post_meta($post_id,$meta_key='',$value='') {



     if( $post_id <= 0 )
        return;

    if( $meta_key == '' )
        return;

    $exist = get_post_meta($post_id,$meta_key,true);
    $value = (is_array($value) || is_object($value) ) ? serialize($value) : $value;
    if($exist) {
        // update the meta
       $status = fsdb()->query("UPDATE ".fsdb()->listing_meta." SET meta_value =  '".$value."'
                                        WHERE listing_id = '{$post_id}' AND meta_key = '{$meta_key}' ");

    } else {
        // insert the meta
       $status = fsdb()->query("INSERT INTO ".fsdb()->listing_meta."(meta_key,listing_id,meta_value) VALUES ('{$meta_key}','{$post_id}','".$value."') ");

    }
    return $status;
}

function delete_post_meta($post_id,$meta_key='') {



     if( $post_id <= 0 )
        return;

    if( $meta_key == '' )
        return;

    $status = false;
    $exist = get_post_meta($post_id,$meta_key,true);
    if($exist) {
        // update the meta
       $status = fsdb()->query("DELETE FROM ".fsdb()->listing_meta." WHERE listing_id = '{$post_id}' AND meta_key = '{$meta_key}' ");
    }

    return $status;
}


/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @since 3.0
 *
 * @param string $data   Value to check to see if was serialized.
 * @param bool   $strict Optional. Whether to be strict about the end of the string. Default true.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized( $data, $strict = true ) {
    // if it isn't a string, it isn't serialized.
    if ( ! is_string( $data ) ) {
        return false;
    }
    $data = trim( $data );
    if ( 'N;' == $data ) {
        return true;
    }
    if ( strlen( $data ) < 4 ) {
        return false;
    }
    if ( ':' !== $data[1] ) {
        return false;
    }
    if ( $strict ) {
        $lastc = substr( $data, -1 );
        if ( ';' !== $lastc && '}' !== $lastc ) {
            return false;
        }
    } else {
        $semicolon = strpos( $data, ';' );
        $brace     = strpos( $data, '}' );
        // Either ; or } must exist.
        if ( false === $semicolon && false === $brace )
            return false;
        // But neither must be in the first X characters.
        if ( false !== $semicolon && $semicolon < 3 )
            return false;
        if ( false !== $brace && $brace < 4 )
            return false;
    }
    $token = $data[0];
    switch ( $token ) {
        case 's' :
            if ( $strict ) {
                if ( '"' !== substr( $data, -2, 1 ) ) {
                    return false;
                }
            } elseif ( false === strpos( $data, '"' ) ) {
                return false;
            }
            // or else fall through
        case 'a' :
        case 'O' :
            return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
        case 'b' :
        case 'i' :
        case 'd' :
            $end = $strict ? '$' : '';
            return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
    }
    return false;
}

/**
 * uses global variable $options to retrive single option values and returns in
 * fallsback to constant if no settings are retrived
 *
 * @param  string $key option key to get value of . $key name is same as contant name but in all lower case .
 * @return mixed      returns value if found or false
 */
function get_option($key,$constant = true) {

    global $options;

    // for site url give precedence to site url
    if( $key == 'site_url' && defined( strtoupper($key) ) ) {
        return constant(strtoupper($key));
    }

    $value = isset($options[$key]) ? $options[$key] : false;
    if( ! $value && $constant && defined( strtoupper($key) ) ) {
        $value = constant(strtoupper($key));
    } else {
        $value = is_serialized( $value ) ? unserialize( $value ) : $value;
    }

    return $value;
}

/**
 * Checks if correct username & password is transferred.
 * Logs in the admin
 * @return void
 */
function feedsync_form_admin_login() {

    if( isset($_POST['username']) && isset($_POST['password'])  ) {

        if( $_POST['username'] == FEEDSYNC_ADMIN &&  $_POST['password'] == FEEDSYNC_PASS ){
            $_SESSION['uid'] = 1; // user id static for now since only one user is there
        } else {
            add_sitewide_notices('Username or password is incorrect','danger');
        }
    }
}
add_action('feedsync_form_admin_login','feedsync_form_admin_login');

/**
 * Logout of the feedsync application
 * @return void
 */
function feedsync_form_logout() {

    $_SESSION = array();
    session_destroy();

}
add_action('feedsync_form_logout','feedsync_form_logout');

/**
 * A little handly function to print a variable and exits it
 * @param  mixed $data
 * @return void
 */
function print_exit($data) {
    echo "<pre>";
    print_r($data);
    die;
}

function slugify($text) {

  // replace non letter or digits by -
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);

  // transliterate
  if( function_exists('iconv') ){
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  }

  // remove unwanted characters
  $text = preg_replace('~[^-\w]+~', '', $text);

  // trim
  $text = trim($text, '-');

  // remove duplicate -
  $text = preg_replace('~-+~', '-', $text);

  // lowercase
  $text = strtolower($text);

  if (empty($text)) {
    return 'n-a';
  }

  return $text;
}

/**
 * Adds css link to the page
 * @param  array  $css files to be linked
 *
 * @since 3.4.0 Use build number for versioning, if available
 */
function enqueue_css($css = array() ) {
    global $current_version, $build_number;

    $css_version = !empty ( $build_number ) ? slugify($build_number) : slugify($current_version);

    if( !empty ($css) ) {
        foreach($css as $file) {
            echo '<link rel="stylesheet" href="'.CSS_URL.$file.'?version='.$css_version.'" />';
        }
    }
}

/**
 * Adds script url to the page
 * @param  array  $js js files to added
 *
 * @since 3.4.0 Use build number for versioning, if available
 */
function enqueue_js($js = array() ) {
    global $current_version, $build_number;

    $js_version = !empty ( $build_number ) ? slugify($build_number) : slugify($current_version);
    if( !empty ($js) ) {
        foreach($js as $file) {
            $prefix = is_absolute_url($file) ? '' : JS_URL;
            $version_string = is_absolute_url($file) ? '' : '?version='.$js_version;
            echo '<script type="text/javascript" src="'.$prefix.$file.$version_string.'" ></script>';
        }
    }
}

function is_absolute_url($url) {
    $pattern = "/^(?:ftp|https?|feed):\/\/(?:(?:(?:[\w\.\-\+!$&'\(\)*\+,;=]|%[0-9a-f]{2})+:)*
    (?:[\w\.\-\+%!$&'\(\)*\+,;=]|%[0-9a-f]{2})+@)?(?:
    (?:[a-z0-9\-\.]|%[0-9a-f]{2})+|(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\]))(?::[0-9]+)?(?:[\/|\?]
    (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})*)?$/xi";

    return (bool) preg_match($pattern, $url);
}

/**
 * Generates bootstrap error html
 * @param  string $error [description]
 * @return [type]        [description]
 */
function get_error_html($error='') {
    return '
            <div class="alert alert-danger" role="alert">
              <span class="sr-only">Error:</span>
              '.$error.'
            </div>
        ';
}

/**
 * Generates bootstrap success html
 * @param  string $msg [description]
 * @return [type]      [description]
 */
function get_success_html($msg='') {
    return '
            <div class="alert alert-success" role="alert">
              <span class="sr-only">Error:</span>
              '.$msg.'
            </div>
        ';
}

function upgrade_options() {

    $option_exist = fsdb()->query("SELECT * FROM ".fsdb()->options." WHERE option_name = 'option' ");

    if( !$option_exist ) {
        return;
    }

    $data = fsdb()->get_row("SELECT * FROM ".fsdb()->options." WHERE option_name = 'option' ");
    $data = !empty($data) ? unserialize($data->option_value) : array() ;

    if( !empty($data) ) {
        foreach($data as $key => $value) {
            update_option($key,$value);
        }

        delete_option('option');
    }
}

/**
 * Check if feedsync's required folders exists already
 * If not already there, it attempts to create them
 * @return [type] [description]
 */
function check_folders_existance() {

    $permissions = '';

    /**
     * To overwrite folder permission define this constant in config.php
     * 
     * Example :
     * 
     * define( 'FEEDSYNC_FOLDER_PERMISSIONS', 0775 );
     */
    if( defined( 'FEEDSYNC_FOLDER_PERMISSIONS') ) {
        $permissions = FEEDSYNC_FOLDER_PERMISSIONS;
    }

    if( empty( $permissions ) ) {
        $permissions = 0755;
    }

    $paths = array(INPUT_PATH,OUTPUT_PATH,IMAGES_PATH,PROCESSED_PATH,ZIP_PATH,TEMP_PATH,LOG_PATH,UPGRADE_PATH,LOGS_FOLDER_PATH);

    foreach($paths as $path) {

        if (!file_exists($path) ) {
            @mkdir($path, $permissions, true);

        } else {
            @chmod($path, $permissions );

        }
    }
}

$feedsync_hook->add_action('init','check_folders_existance');

function upgrade_table_data() {

    $fu = new feedsync_upgrade();
    $fu->dispatch();

    die(
        json_encode(
            array(
                'status'    =>  'success',
                'message'   =>  'Upgrade Process Complete!',
                'buffer'    =>  'complete'
            )
        )
    );

}

add_action('ajax_upgrade_table_data','upgrade_table_data');

/**
 * Generate processing buttons on process page
 *
 * @since 2.2
 * @since 3.4 Changed appearance for force geocode button & hide geocode button if API key is not set.
 */
function feedsync_manual_processing_buttons() {

    if( is_reset_enabled() ) :
        $reset_tooltip = 'This will delete all listing data in FeedSync and cannot be undone';
        echo "<input data-toggle='tooltip' data-html='true' type='button' title='$reset_tooltip' class='btn btn-info pull-right' value='Reset Feedsync' id='reset_feedsync'>";
    endif;

    $geocode_button_style       = '';
    $geocode_button_label       = 'Process Missing Coordinates';
    $tooltip                    = 'Process lat/long coordinates for any listings that have missing coordinates';
    $db_upgrade_tooltip         = 'Perform a one time database upgrade';
    // Force Geocode Processing Button Warning.
    if ( 'on' === get_option( 'force_geocode' ) ) {
        $geocode_button_style       = 'btn-danger';
        $geocode_button_label       = 'Delete and Process all coordinates';
        $tooltip                    = 'This will delete & re-process lat/long coordinates for all your listings. Only use this if your listings coordinates are incorrect';
    }

    if( get_option('feedsync_google_api_key') != '' ) {
        echo "<input data-toggle='tooltip' data-html='true' title='$tooltip' data-placement='top' type='button' class='btn $geocode_button_style btn-info pull-right' value='$geocode_button_label' id='process_missing_coordinates'>";
    }
    

    $agent_tooltip = 'Process all listings for missing agent details';
    echo "<input data-toggle='tooltip' data-html='true' type='button' title='$agent_tooltip' class='btn btn-info pull-right' value='Process Listing Agents' id='process_missing_listing_agents'>";

    if( feedsync_upgrade_required() ):
        echo "<input data-toggle='tooltip' data-html='true' type='button' title='$db_upgrade_tooltip' class='btn btn-info pull-right' value='Database Upgrade' id='upgrade_table_data'>";
    endif;
    ?>

    <?php if( is_reset_enabled() ) : ?>
        <div class="feedsync-reset-wrap">
        <div class="alert alert-danger">
            <p>Please continue only if you know what you are doing. <b>This process cannot be undone</b>.</p>
        </div>
            <input class="reset_confirm_pass" id="reset_confirm_pass" placeholder="Enter admin password" type="text">
            <input class="btn btn-info pull-right" value="Confirm Reset" id="confirm_table_reset" type="button">
        </div> <?php
    endif;
}

function update_option_data($name='',$data) {

    if($name == '')
        return false;


    $data   = serialize($data);
    $exist = fsdb()->query("SELECT * FROM ".fsdb()->options." WHERE option_name = '{$name}' ");
    if($exist) {
        // update the option
       $status = fsdb()->query("UPDATE ".fsdb()->options." SET option_value =  '".$data."' WHERE option_name = '{$name}' ");
    } else {
        // insert the option
       $status = fsdb()->query("INSERT INTO ".fsdb()->options."(option_name,option_value) VALUES ('{$name}','".$data."') ");
    }
}

function get_option_data($name) {
    $query = "SELECT * FROM ".fsdb()->options." WHERE option_name = '{$name}' ";
    $data = fsdb()->get_row($query);
    return !empty($data) ? unserialize($data->option_value) : array() ;
}

function is_reset_enabled() {
    if( defined( 'FEEDSYNC_RESET') && FEEDSYNC_RESET == true) {
        return true;
    }

    return false;
}

function is_home() {

    if( defined( 'FEEDSYNC_HOME') && FEEDSYNC_HOME == true) {
        return true;
    }

    return false;
}


function get_single_timezone_array() {
    return timezone_identifiers_list();
}

/**
 * Get region wise timezones
 * @since  :      3.0
 * @return array
 */
function get_timezone_array() {

    $zones = timezone_identifiers_list();

    foreach ($zones as $zone)
    {
        $zoneExploded = explode('/', $zone); // 0 => Continent, 1 => City

        // Only use "friendly" continent names
        if ($zoneExploded[0] == 'Africa' || $zoneExploded[0] == 'America' || $zoneExploded[0] == 'Antarctica' || $zoneExploded[0] == 'Arctic' || $zoneExploded[0] == 'Asia' || $zoneExploded[0] == 'Atlantic' || $zoneExploded[0] == 'Australia' || $zoneExploded[0] == 'Europe' || $zoneExploded[0] == 'Indian' || $zoneExploded[0] == 'Pacific')
        {
            if (isset($zoneExploded[1]) != '')
            {
                $area = str_replace('_', ' ', $zoneExploded[1]);

                if (!empty($zoneExploded[2]))
                {
                    $area = $area . ' (' . str_replace('_', ' ', $zoneExploded[2]) . ')';
                }

                $time = new DateTime(NULL, new DateTimeZone($zone));
                /** 12 hour format with am - pm */
                $ampm = $time->format('g:i a');
                $locations[$zoneExploded[0]][$zone] = $area .' - '.$ampm; // Creates array(DateTimeZone => 'Friendly name')
            }
        }
    }
    return $locations;
}

function get_access_key_default_status() {

    $access_key = get_option('feedsync_access_key');

    return (false !== $access_key && $access_key != '') ? 'on' : 'off';
}

/**
 * Determines if update available.
 * 
 * @since 3.4.4 Load updater on demand to optimise page load speed.
 * @return     boolean|feedsync_updater|string  True if update available, False otherwise.
 */
function is_update_available() {

    $dev_mode = defined('FEEDSYNC_UPDATE_MODE') ? FEEDSYNC_UPDATE_MODE : '';

    if($dev_mode == 'dev') {
        return true;
    }

    $update_available = get_transient('feedsync_update_available');
    $feedsync_updater = null;

    // no transient exist
    if( !$update_available ){

        if( !class_exists('FEEDSYNC_PROCESSOR') )
            include_once(CORE_PATH.'classes/class-feedsync-updater.php');

        $feedsync_updater = new feedsync_updater();

        global $current_version;

        $feedsync_updater->init() ;

        $update_available = $feedsync_updater->version > $current_version ? 'yes' : 'no';

        set_transient( 'feedsync_update_available', $update_available, 24*60*60 );
    }

    $status =  $update_available == 'yes' ? true : false;

    if( $status ) {
        update_notification($feedsync_updater,$status);
    }

    return $status;
}

/**
 * Update notification if available.
 * 
 * @since 3.4.4 Load updater on demand to optimise page load speed.
 *
 * @param      feedsync_updater  $feedsync_updater  The feedsync updater
 * @param      <type>            $update_available  The update available
 */
function update_notification($feedsync_updater,$update_available) {

    if( !get_transient('feedsync_update_notified')  ) {

        if( is_null( $feedsync_updater ) || ! is_object( $feedsync_updater ) ) {

            if( !class_exists('FEEDSYNC_PROCESSOR') )
                include_once(CORE_PATH.'classes/class-feedsync-updater.php');

            $feedsync_updater = new feedsync_updater();
        }

        global $feedsync_mailer;

        $update_url = CORE_URL.'pages/updates.php';

        $body = "
            <table>
                <tbody>
                    <tr>
                        <th><b>New Version available for Feedsync! </b></th>
                        <td><a href='".$update_url."'>Update</a> to the latest version for more features </td>
                    </tr>
                    <tr>
                        <th><b>See whats new in latest release</b></th>
                        <td>
                            <p>
                                ".$feedsync_updater->changelog."
                            </p>
                        </td>
                    </tr>

                </tbody>
            </table>";

        $subject = "Feedsync v".$feedsync_updater->version." is available now !";

        $to     = get_option('feedsync_debug_receiver');
        $feedsync_mailer->send($to,$subject,$body);

        set_transient( 'feedsync_update_notified', $update_available, 3*24*60*60 );
    }


}


function set_transient($key,$value,$expiration) {

    $key                =  '_transient_'.$key;
    $key_timeout        = '_transient_timeout_' . $key;

    $expiration = time() + $expiration;
    update_option($key_timeout, $expiration);
    return update_option($key, $value);
}

function get_transient($key) {

    $key                =  '_transient_'.$key;
    $key_timeout        = '_transient_timeout_' . $key;

    $timeout = get_option( $key_timeout );
    if ( false !== $timeout && $timeout < time() ){
        delete_option( $key  );
        delete_option( $key_timeout );
        $value = false;
    }

    if ( ! isset( $value ) )
        $value = get_option( $key );

    return $value;
}

/**
 * Add notice if zip file is there to process but zip extension is not enabled
 * @return [type]
 *
 * @since 3.4.0 Show systemwide notice if required modules are not loaded
 */
function feedsync_show_extension_errors() {

    $z_ex = get_files_list(get_path('input'),"zip|ZIP");
    if( !empty($z_ex) && !class_exists('ZipArchive') ) {
        add_sitewide_notices('Zip Extension is required','danger');
    }

    if (version_compare(PHP_VERSION, '5.6.0') < 0) {
        add_sitewide_notices('PHP version 5.6 or later is required','danger');
    }

    $dependency = array(
        'zip',
        'curl',
        'iconv',
        'allow_url_fopen',
        'ftp',
        'dom',
    );

    $disabled = array();

    foreach( $dependency as $dep ) {

        if( 'allow_url_fopen' == $dep) {
            if ( ! ini_get( $dep ) ) {
                $disabled[] = $dep;
            }
        } else if ( ! extension_loaded( $dep ) ) {
            $disabled[] = $dep;
        }

    }

    if ( ! empty( $disabled ) ) {
    $disabled_uppercase = array_map( 'strtoupper', $disabled );
    add_sitewide_notices( '<strong>'.implode(', ', $disabled_uppercase ).'</strong> PHP Modules are required for FeedSync to work properly, contact your hosting provider to enable them.', 'danger' );

    // Special iconv notice.
    if ( in_array( 'iconv' , $disabled )) {
        add_sitewide_notices( '<strong>NOTICE:</strong> NOTICE: Without <strong>ICONV</strong> enabled FeedSync is running in compatibility mode and special characters in the listing data is not able to be converted to html characters.', 'warning' );
    }

    }

}

add_action('after_functions_include','feedsync_show_extension_errors');

function feedsync_upgrade_required() {

    global $current_version;

    if( get_option('db_version') < $current_version ) {
        return true;
    } else {
        return false;
    }
}

function is_permalinks_enabled() {

    $permalinks = get_option('feedsync_enable_permalinks');

    return $permalinks == ''  ? false : $permalinks;
}

function feedsync_nav_link($url) {
   $replace =  is_permalinks_enabled() ? '.php' : '';
   return str_replace($replace,'',$url);
}

function feedsync_navigation() {

    if( ! is_user_logged_in() )
        return;

    global $page_now;
    $base_url = is_permalinks_enabled() ? SITE_URL : CORE_URL;
    $pages_url = is_permalinks_enabled() ? SITE_URL : CORE_URL.'pages/';


    $hide_settings_menu = false;
    $hide_help_menu     = false;

    if( defined('FEEDSYNC_SETTINGS_DISABLED') && FEEDSYNC_SETTINGS_DISABLED == true ) {
        $hide_settings_menu = true;
    }

    if( defined('FEEDSYNC_HELP_DISABLED') && FEEDSYNC_HELP_DISABLED == true  ) {
        $hide_help_menu = true;
    }

    ?>
    <div id="feedsync-navigation">
        <ul class="nav nav-pills pull-right">
            <li class="<?php echo $page_now == 'home' ? 'active':''; ?>">
                <a href="<?php echo SITE_URL ?>">Home</a>
            </li>
            <li class="<?php echo $page_now == 'process' ? 'active':''; ?>">
                <a href="<?php echo feedsync_nav_link($base_url.'process.php') ?>">Process</a>
            </li>
            <li class="<?php echo $page_now == 'export' ? 'active':''; ?>">
                <a href="<?php echo feedsync_nav_link($base_url.'export.php') ?>">Export</a>
            </li>
            <li class="<?php echo $page_now == 'listings' ? 'active':''; ?>">
                <a href="<?php echo feedsync_nav_link($base_url.'listings.php') ?>">Listings</a>
            </li>
            <?php if(!$hide_help_menu) : ?>
            <li class="<?php echo $page_now == 'help' ? 'active':''; ?>">
                <a href="<?php echo feedsync_nav_link($pages_url.'help.php') ?>">Help</a>
            </li>
            <?php endif; ?>

            <?php if( is_user_logged_in() ) { ?>

                <?php if(!$hide_settings_menu) : ?>
                <li class="<?php echo $page_now == 'settings' ? 'active':''; ?>">
                    <a href="<?php echo feedsync_nav_link($base_url.'settings.php') ?>">Settings</a>
                </li>
                <?php endif; ?>

                <li>
                    <a href="<?php echo SITE_URL.'?action=logout' ?>">Logout</a>
                </li>

            <?php } ?>

            <?php if( is_update_available() && !$hide_help_menu ) { ?>

                <li class="<?php echo $page_now == 'update' ? 'active':''; ?>">
                    <a class="btn btn-warning" href="<?php echo feedsync_nav_link($pages_url.'updates.php') ?>">Update</a>
                </li>

            <?php } ?>
        </ul>
    </div>
<?php
}

/**
 * Check if db supports certain features
 */
function db_supports( $feature ) {

    $version = get_db_version();

    switch ( strtolower( $feature ) ) {
        case 'collation' :
        case 'group_concat' :
        case 'subqueries' :
            return version_compare( $version, '4.1', '>=' );
        case 'set_charset' :
            return version_compare( $version, '5.0.7', '>=' );
        case 'utf8mb4' :
            if ( version_compare( $version, '5.5.3', '<' ) ) {
                return false;
            }
            $client_version = mysqli_get_client_info();

            /*
             * libmysql has supported utf8mb4 since 5.5.3, same as the MySQL server.
             * mysqlnd has supported utf8mb4 since 5.0.9.
             */
            if ( false !== strpos( $client_version, 'mysqlnd' ) ) {
                $client_version = preg_replace( '/^\D+([\d.]+).*/', '$1', $client_version );
                return version_compare( $client_version, '5.0.9', '>=' );
            } else {
                return version_compare( $client_version, '5.5.3', '>=' );
            }
        case 'utf8mb4_520' : // @since 4.6.0
            return version_compare( $version, '5.6', '>=' );
    }

    return false;
}

/**
 * Retrieves the MySQL server version.
 * @return null|string Null on failure, version number on success.
 */
function get_db_version() {

    $server_info = mysqli_get_server_info( fsdb()->dbh );
    return preg_replace( '/[^0-9.].*/', '', $server_info );
}

/**
 * returns charset and collation
 */
function init_charset() {

    $charset = 'utf8mb4';
    $collate = '';

    if ( defined( 'DB_COLLATE' ) ) {
        $collate = DB_COLLATE;
    }

    if ( defined( 'DB_CHARSET' ) ) {
        $charset = DB_CHARSET;
    }

    return fsdb()->determine_charset( $charset, $collate );
}

/**
 * Fix charset and collation for feedsync <= 3.2.3
 * @return null
 */
function fix_charset_collate() {

    global $feedsync_db, $current_version;

    if( get_option('chartset_collation_updated') != 'yes' ){

        $charset_collate = init_charset();

        $tables = fsdb()->get_col('SHOW TABLES');

        if( !empty($tables) ) {
            fsdb()->query('SET FOREIGN_KEY_CHECKS=0');
            foreach($tables as $table) {
                fsdb()->query("ALTER TABLE $table CONVERT TO CHARACTER SET ".$charset_collate['charset']." COLLATE ".$charset_collate['collate']);
            }
            fsdb()->query('SET FOREIGN_KEY_CHECKS=1');
        }

        update_option('chartset_collation_updated','yes');
    }


}

add_action('init','fix_charset_collate',15);

/**
 * Return string without trailing slash
 *
 * @param      string  $string  The string
 *
 * @return     string  without trailing slash.
 * @since      3.4.5 
 */
function untrailingslashit( $string ) {
    return rtrim( $string, '/\\' );
}

/**
 * Return string with trailing slash
 *
 * @param      string  $string  The string
 *
 * @return     string  with trailing slash.
 * @since      3.4.5
 */
function trailingslashit( $string ) {
    return untrailingslashit( $string ) . '/';
}