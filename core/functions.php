<?php
require_once('classes/class-feedsync-error.php');
require_once('classes/class-hook.php');
require_once('classes/class-logger.php');
require_once('classes/class-feedsync-datetime.php');
include_once('constants.php');
require_once('classes/class-validations.php');
// include the pagination class
require_once 'pagination.php';

global $feedsync_validations,$sitewide_notices,$feedsync_logger,$feedsync_mailer,$pagination;

do_action('init_constants');

/**
 * Use this for debugging purposes. availability sitewide
 * @var PHPLogger
 *
 * Example :
 *
 * global $feedsync_logger;
 * $feedsync_logger->i('key','description' );
 *
 */
$feedsync_logger = new PHPLogger(SITE_ROOT.'feedsync.log');

$feedsync_validations = new GUMP();
$sitewide_notices = array();

/**
 * Gets root path relative to server root
 *
 * @return [type] [description]
 * @since 3.4.0
 * @since 3.5.0 use of COOKIEPATH constant based on site url
 */
function get_rel_root_path() {
    return COOKIEPATH;
}

/**
 * Initialise Session
 *
 * @since 1.0.0
 * @since 3.4.0 Tweak : Session path variable made independent of SITE_URL constant.
 * @since 3.4.6 Tweak : Using session_id() to define session ID
 */
function init_session() {
    $cookie_params  = session_get_cookie_params();
    $root_path      = get_rel_root_path();
    session_set_cookie_params($cookie_params['lifetime'], $root_path);
    if( empty( session_id() ) ) {
        @session_start();
    }

}
add_action('init_session','init_session',21);


/**
 * Autoload classes whereever required
 * @param  [type] $classname Class to be instantiated
 */
function feedsync_autoload($classname) {

    /** @var file name for class */
    $filename = 'class-'.str_replace( '_','-',strtolower($classname) ).'.php';

    if( file_exists(CORE_PATH.'classes/'.$filename) )
        include_once(CORE_PATH.'classes/'.$filename);

    /** @var file name for api file */
    $filename = str_replace( '_api','',strtolower($classname) ).'.php';
    if( file_exists(CORE_PATH.'api/'.$filename) )
        include_once(CORE_PATH.'api/'.$filename);

}

/**
 * register autoload
 */
spl_autoload_register('feedsync_autoload');

/** only purpose of this function is to provide easy migration to wordpress */
function __($str,$text_domain='') {
    return $str;
}

/** only purpose of this function is to provide easy migration to wordpress */
function _e($str,$text_domain='') {
    echo __($str,$text_domain);
}

function apply_filters($tag,$value){
    global $feedsync_hook;
    return $feedsync_hook->apply_filters($tag,$value);
}
/**
 * Check if callback is added for a filter
 * @param  string  $tag   filter
 * @param  mixed  $value callback
 * @return boolean        true if filter is set
 * @since 3.4.0
 */
function has_filter($tag,$value){
    global $feedsync_hook;
    return $feedsync_hook->has_filter($tag,$value);
}

function add_filter($tag,$callback,$priority=10,$accepted_args=1){
    global $feedsync_hook;
    $feedsync_hook->add_filter($tag,$callback,$priority,$accepted_args);
}

function do_action($tag){
    global $feedsync_hook;
    $feedsync_hook->do_action($tag);
}

function add_action($tag,$callback,$priority=10,$accepted_args=1){
    global $feedsync_hook;
    $feedsync_hook->add_action($tag,$callback,$priority,$accepted_args);
}

function fs_debug_backtrace_summary( $ignore_class = null, $skip_frames = 0, $pretty = true ) {

    $trace       = debug_backtrace( false );
    $caller      = array();
    $check_class = ! is_null( $ignore_class );
    $skip_frames++; // skip this function

    foreach ( $trace as $call ) {
        if ( $skip_frames > 0 ) {
            $skip_frames--;
        } elseif ( isset( $call['class'] ) ) {
            if ( $check_class && $ignore_class == $call['class'] ) {
                continue; // Filter out calls
            }

            $caller[] = "{$call['class']}{$call['type']}{$call['function']}";
        } else {
            if ( in_array( $call['function'], array( 'do_action', 'apply_filters', 'do_action_ref_array', 'apply_filters_ref_array' ) ) ) {
                $caller[] = "{$call['function']}('{$call['args'][0]}')";
            } elseif ( in_array( $call['function'], array( 'include', 'include_once', 'require', 'require_once' ) ) ) {
                $filename = isset( $call['args'][0] ) ? $call['args'][0] : '';
                $caller[] = $call['function'] ;
            } else {
                $caller[] = $call['function'];
            }
        }
    }
    if ( $pretty ) {
        return join( ', ', array_reverse( $caller ) );
    } else {
        return $caller;
    }
}
/**
 * creates and exposes global $options
 * used by get_option function to retrives single values of option
 * @return [type] [description]
 */
function instantiate_options() {
    global $options;
    $options = fsdb()->get_results("SELECT * FROM ".fsdb()->options);
    if( !empty($options) ) {
        foreach($options as $option) {

            if( is_object( $option ) && !empty( $option ) ) {
                $value = is_serialized( $option->option_value ) ? unserialize( $option->option_value ) : $option->option_value;
                $options[$option->option_name] = $value;
            }

        }
    }
}

add_action('init_options','instantiate_options',1);

// IMPORTANT: edit class-feedsync-upgrade.php and add version.
$current_version    = '3.5.5';
$build_number       = '21-1020';

$application_name = 'FeedSync REAXML Processor';

include_once('classes/class-fsdb.php');

/**
 * [get_table_prefix description]
 * @return [type] [description]
 */
function get_table_prefix() {

    if( ! defined( 'FS_TABLE_PREFIX' ) || empty( FS_TABLE_PREFIX ) ) {
        $prefix = 'fs_';

    } else {
        $prefix = FS_TABLE_PREFIX;
    }

    return $prefix;
}


/**
 * Set the database table prefix and the format specifiers for database
 * table columns.
 *
 * Columns not listed here default to `%s`.
 *
 * @since 3.4.0
 * @access private
 *
 * @global fsdb   $fsdb         FeedSync database abstraction object.
 * @global string $table_prefix The database table prefix.
 */
function fs_set_wpdb_vars() {

    global $fsdb;
    if ( ! empty( $wpdb->error ) ) {
        die();
    }
    $fsdb->field_types = array(
        'ID'               => '%d'
    );
    $prefix = $fsdb->set_prefix( get_table_prefix() );
    if ( is_fs_error( $prefix ) ) {
        die(
            sprintf(
                /* translators: 1: $table_prefix, 2: wp-config.php */
                __( '<strong>ERROR</strong>: %1$s in %2$s can only contain numbers, letters, and underscores.' ),
                '<code>$table_prefix</code>',
                '<code>config.php</code>'
            )
        );
    }
}

/**
 * Init FSDB Instance
 * @return [type] [description]
 *
 * @since 3.4.0
 */
function require_fsdb() {
    global $fsdb;

    if ( isset( $fsdb ) ) {
        return;
    }
    $dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
    $dbpassword = defined( 'DB_PASS' ) ? DB_PASS : '';
    $dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
    $dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';
    $fsdb       = new fsdb( $dbuser, $dbpassword, $dbname, $dbhost );

    // force utf8 encoding
    $fsdb->set_charset( $fsdb->dbh, 'utf8');

}

function fsdb() {
    global $fsdb;
    return $fsdb;
}

global $fsdb;
require_fsdb();
fs_set_wpdb_vars();

include_once('eac-functions.php');

include_once('setup-functions.php');

include_once('classes/class-feedsync-mailer.php');

include_once('classes/class-feedsync-error-handler.php');

include_once('export-functions.php');

include_once('license-functions.php');

do_action('after_functions_include');

function cleanup_header_comment( $str ) {
    return trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $str ) );
}

function get_file_data( $file, $default_headers, $context = '' ) {
    // We don't need to write to the file, so just open for reading.
    $fp = fopen( $file, 'r' );

    if ( $fp ) {
        // Pull only the first 8 KB of the file in.
        $file_data = fread( $fp, 8 * KB_IN_BYTES );

        // PHP will close file handle, but we are good citizens.
        fclose( $fp );
    } else {
        $file_data = '';
    }

    // Make sure we catch CR-only line endings.
    $file_data = str_replace( "\r", "\n", $file_data );

    /**
     * Filters extra file headers by context.
     *
     * The dynamic portion of the hook name, `$context`, refers to
     * the context where extra headers might be loaded.
     *
     * @param array $extra_context_headers Empty array by default.
     */
    $extra_headers = $context ? apply_filters( "extra_{$context}_headers", array() ) : array();
    if ( $extra_headers ) {
        $extra_headers = array_combine( $extra_headers, $extra_headers ); // Keys equal values.
        $all_headers   = array_merge( $extra_headers, (array) $default_headers );
    } else {
        $all_headers = $default_headers;
    }

    foreach ( $all_headers as $field => $regex ) {
        if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
            $all_headers[ $field ] = cleanup_header_comment( $match[1] );
        } else {
            $all_headers[ $field ] = '';
        }
    }

    return $all_headers;
}

function get_plugin_data( $plugin_file, $markup = true, $translate = true ) {

    $default_headers = array(
        'Name'        => 'Plugin Name',
        'PluginURI'   => 'Plugin URI',
        'Version'     => 'Version',
        'Description' => 'Description',
        'Author'      => 'Author',
        'AuthorURI'   => 'Author URI',
        'RequiresPHP' => 'Requires PHP',
    );

    $plugin_data = get_file_data( $plugin_file, $default_headers, 'plugin' );

    return $plugin_data;
}

/** Load Plugins */
function load_plugins() {

    $plugins = get_files_list( get_path('plugins'),"php");

    foreach( $plugins as $plugin ) {
        $plugin_data = get_plugin_data( $plugin );
        if( !empty( $plugin_data['Name'] ) ) {
            require_once( $plugin );
        }
    }
}
load_plugins();

/**
 * Force file permissions :
 *
 * Directory to 0755 and files to 0644
 *
 * @return null
 */
function force_file_permissions() {

    $permissions = get_option('permission_setup');

    if( 'yes' == $permissions ) {
        return;
    }

    $new_files = get_recursive_files_list(SITE_ROOT,'.*');
    $site_root = rtrim( SITE_ROOT, DS).DIRECTORY_SEPARATOR;

    if( !empty($new_files) ){

        foreach($new_files as $new_file){

           $rel_path = str_replace( $site_root,'',$new_file);

            if($rel_path == 'config.php') {
                // skip config file
            } else {

                if( is_dir($new_file) ){
                    @chmod($new_file, 0755);

                } else {
                    @chmod($new_file, 0644);
                }

            }

        }

        update_option('permission_setup', 'yes' );
    }
}

force_file_permissions();

/*
** handle form submission
*/
if( isset($_REQUEST['action']) ) {
    date_default_timezone_set(get_option('feedsync_timezone'));
    do_action('feedsync_form_'.$_REQUEST['action']);
}



function get_header($page_now='') {
    include_once(CORE_PATH.'header.php');
}

function get_footer() {
    include_once(CORE_PATH.'footer.php');
}

function home() {
    include_once(CORE_PATH.'home.php');
}

// Jumbotron Processor Button
function feedsync_description_jumbotron() { ?>
    <img src="<?php echo CORE_URL.'images/feedsync-icon.png' ?>" width="128" height="128" />
    <h1>FeedSync</h1>
    <p class="lead">If you have XML files below waiting to be processed you can manually process them to test your FeedSync settings. Once you successfully process your xml files manually, you can set a timed schedule on your server via a simple <a href="<?php echo CORE_URL.'pages/help.php' ?>#cron">cron</a> command that will process your xml files regularly.</p>
    <p><a class="btn btn-primary btn-lg" href="core/process.php" role="button">Process Feed</a></p> <?php
}

// Jumbotron login box
function feedsync_login_jumbotron() { ?>
    <img src="<?php echo CORE_URL.'images/feedsync-icon.png' ?>" width="128" height="128" />
    <h1>FeedSync</h1>
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="login-panel panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Please Sign In</h3>
                    </div>
                    <div class="panel-body">
                        <form role="form" method="post">
                            <fieldset>
                                <div class="form-group">
                                    <input class="form-control" placeholder="Username" name="username" type="text" autofocus>
                                </div>
                                <div class="form-group">
                                    <input class="form-control" placeholder="Password" name="password" type="password" value="">
                                </div>
                                <input type="hidden" name="action" value="admin_login" />
                                <input type="submit" name="login_submit" class="btn btn-lg btn-success btn-block" value="Login" />
                            </fieldset>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
}

function get_files_list($folder,$pattern) {

    $pattern    = "/^.*\.(".$pattern.")$/";
    $dir        = new DirectoryIterator($folder);
    $ite        = new IteratorIterator($dir);
    $files      = new RegexIterator($ite, $pattern);
    $fileList = array();
    foreach($files as $file) {
        $fileList[] = $file->getpathname();
    }
      return $fileList;

}

class RecursiveDotFilterIterator extends  RecursiveFilterIterator {
    public function accept() {
        return '.' !== substr($this->current()->getFilename(), 0, 1);
    }
}

function get_recursive_files_list($folder,$pattern) {

    $pattern    = "/^.*\.(".$pattern.")$/";
    $dir        = new RecursiveDirectoryIterator($folder);
    $ite        = new RecursiveIteratorIterator( new RecursiveDotFilterIterator( $dir ) );
    $files      = new RegexIterator($ite, $pattern);
    $fileList = array();
    foreach($files as $file) {
        $fileList[] = $file->getpathname();
    }
    return $fileList;

}

function get_sub_path() {

    $feedtype = get_option('feedtype');
    $path = DS;
    switch($feedtype) {

        case 'blm' :
        case 'reaxml' :
        case 'reaxml_fetch' :
        case 'expert_agent' :
        case 'rockend' :
        case 'jupix' :
           $path = '';
        break;

    }
    return $path;
}

function get_path($folder) {
    $sub_path = get_sub_path();

    switch($folder) {

        case 'input' :
            $path =  INPUT_PATH.$sub_path;
        break;

        case 'output' :
            $path =  OUTPUT_PATH.$sub_path;
        break;

        case 'processed' :
            $path =  PROCESSED_PATH.$sub_path;
        break;

        case 'temp' :
            $path =  TEMP_PATH.$sub_path;
        break;

        case 'zip' :
            $path =  ZIP_PATH.$sub_path;
        break;

        case 'images' :
            $path =  IMAGES_PATH.$sub_path;
        break;

        case 'upgrade' :
            $path =  UPGRADE_PATH;
        break;

        case 'logs' :
            $path =  LOGS_FOLDER_PATH;
        break;

        case 'plugins' :
            $path =  PLUGINS_PATH;
        break;
    }

    return $path;
}

function get_url($folder) {
    $sub_path = get_sub_path();

    switch($folder) {

        case 'input' :
            $path =  INPUT_URL.$sub_path;
        break;

        case 'output' :
            $path =  OUTPUT_URL.$sub_path;
        break;

        case 'procesessed' :
            $path =  PROCESSED_URL.$sub_path;
        break;

        case 'temp' :
            $path =  TEMP_URL.$sub_path;
        break;

        case 'zip' :
            $path =  ZIP_URL.$sub_path;
        break;

        case 'images' :
            $path =  IMAGES_URL.$sub_path;
        break;

        case 'logs' :
            $path =  LOGS_FOLDER_URL;
        break;

    }
    return $path;
}

function get_input_xml() {
    $folder     = get_path('input');
    $pattern    = "xml|XML|zip|ZIP|blm|BLM";
    $files =  get_files_list($folder,$pattern);
    sort($files);
    return $files;
}

function get_output_xml() {
    return get_files_list(get_path('output'),"xml|XML");
}

function get_processed_xml() {
    return get_files_list(get_path('processed'),"xml|XML");
}

function feedsync_format_date( $date ) {
    // supress any timezone related notice/warning;
    error_reporting(0);
    return Feedsync_DateTime::fs_convert_format( $date );
}

function feedsync_format_sold_date( $date ) {
    // supress any timezone related notice/warning;
    error_reporting(0);
    $date_example = '2014-07-22-16:45:56';

    $pos = strpos($date, '-');

    if ($pos === false) {
        $date = new dateTime($date);
        return $date->format('Y-m-d');
    } else {
        $tempdate = explode('-',$date);
        $date = $tempdate[0].'-'.$tempdate[1].'-'.$tempdate[2];
        return  $date;
    }

}

function get_listings_sub_header($page_now='') {
    include_once(CORE_PATH.'sub-pages/listings-sub-header.php');
}


function human_filesize($bytes, $decimals = 2) {
    $sz = 'BKMGTP';
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

function sanitize_user_name( $title) {
    $title = strip_tags($title);
    // Preserve escaped octets.
    $title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
    // Remove percent signs that are not part of an octet.
    $title = str_replace('%', '', $title);
    // Restore octets.
    $title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

    $title = strtolower($title);
    $title = preg_replace('/&.+?;/', '', $title); // kill entities
    $title = str_replace('.', '-', $title);

    // Convert nbsp, ndash and mdash to hyphens
    $title = str_replace( array( '%c2%a0', '%e2%80%93', '%e2%80%94' ), '-', $title );

    // Strip these characters entirely
    $title = str_replace( array(
        // iexcl and iquest
        '%c2%a1', '%c2%bf',
        // angle quotes
        '%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
        // curly quotes
        '%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
        '%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
        // copy, reg, deg, hellip and trade
        '%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
        // acute accents
        '%c2%b4', '%cb%8a', '%cc%81', '%cd%81',
        // grave accent, macron, caron
        '%cc%80', '%cc%84', '%cc%8c',
    ), '', $title );

    // Convert times to x
    $title = str_replace( '%c3%97', 'x', $title );

    $title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
    $title = preg_replace('/\s+/', '-', $title);
    $title = preg_replace('|-+|', '-', $title);
    $title = trim($title, '-');

    return $title;
}

function import_listings($cron_mode = false,$args = array() ) {

    /** if user processes feed but DB is not updated, do it prior to processing */
    if( feedsync_upgrade_required() ){

        $_REQUEST['action'] = 'database_upgrade'; // to prevent processor class from logging / resulting in error

        $fu = new feedsync_upgrade();
        $fu->dispatch();

        die(
            json_encode(
                array(
                    'status'    =>  'success',
                    'message'   =>  'Database upgrade Process Complete!, Feed processing will follow',
                    'buffer'    =>  'processing'
                )
            )
        );
    }

    $feedtype = get_option('feedtype');

    switch($feedtype) {

        case 'blm' :
            $rex = new BLM_PROCESSOR($cron_mode);
            if( 'yes' !== get_option('reaxml_publish_processed') ) {
                $rex->process_publish();
            }
            $rex->import();
        break;

        case 'reaxml' :
        case 'reaxml_fetch' :
            $rex = new REAXML_PROCESSOR($cron_mode);
            if( 'yes' !== get_option('reaxml_publish_processed') ) {
                $rex->process_publish();
            }
            $rex->import();
        break;

        case 'expert_agent' :
            $rex = new Expert_Agent_PROCESSOR($cron_mode);
            if( 'yes' !== get_option('reaxml_publish_processed') ) {
                $rex->process_publish();
            }
            $rex->import();
        break;

        case 'eac' :
            $rex = new EAC_API($cron_mode);
            $rex->process($args);

        break;

        case 'rockend' :
            $rex = new ROCKEND_PROCESSOR($cron_mode);
            if( 'yes' !== get_option('reaxml_publish_processed') ) {
                $rex->process_publish();
            }
            $rex->import();
        break;

        case 'jupix' :
            $rex = new JUPIX_PROCESSOR($cron_mode);
            if( 'yes' !== get_option('reaxml_publish_processed') ) {
                $rex->process_publish();
            }
            $rex->import();
        break;

         case 'xml2u' :
            $rex = new XML2U_PROCESSOR($cron_mode);
            $rex->import();
        break;

    }

}
add_action('ajax_import_listings','import_listings');

// Navigation Settings
function feedsync_settings_navigation( $page ) {
?>
<div id="feedsync-settings-navigation">
    <ul class="nav nav-pills">

        <li<?php if ( $page == "Updates")
    echo " class=\"active\""; ?>>
            <a href="<?php echo CORE_URL;?>pages/updates.php">Updates</a>

        <li<?php if ( $page == "License")
        echo " class=\"active\""; ?>>
            <a href="<?php echo CORE_URL;?>pages/license.php">Status</a>

        <li<?php if ( $page == "Activate")
            echo " class=\"active\""; ?>>
            <a href="<?php echo CORE_URL;?>/pages/activate.php">Activate</a>

    </ul>
</div>
<?php
}

function process_missing_coordinates() {
    $feedtype = get_option('feedtype');
    switch($feedtype) {

        case 'blm' :
            $rex = new BLM_PROCESSOR();
            $rex->process_missing_geocode();
        break;
        case 'reaxml' :
        case 'reaxml_fetch' :
            $rex = new REAXML_PROCESSOR();
            $rex->process_missing_geocode();
        break;
        case 'expert_agent' :
            $rex = new Expert_Agent_PROCESSOR();
            $rex->process_missing_geocode();
        break;
        case 'eac' :
             $rex = new EAC_API(false);
            $rex->process_missing_geocode();
        break;
        case 'rockend' :
            $rex = new ROCKEND_PROCESSOR();
            $rex->process_missing_geocode();
        break;
        case 'jupix' :
            $rex = new JUPIX_PROCESSOR();
            $rex->process_missing_geocode();
        break;

    }
}

$feedsync_hook->add_action('ajax_process_missing_coordinates','process_missing_coordinates');

/**
 * Regenerate coordinates for listing.
 * @return json mixed
 * @since 3.4.0
 */
function regenerate_coordinates() {

    $feedtype = get_option('feedtype');

    switch($feedtype) {

        case 'blm' :
            $rex = new BLM_PROCESSOR();
            $rex->regenerate_coordinates();
        break;
        case 'reaxml' :
        case 'reaxml_fetch' :
            $rex = new REAXML_PROCESSOR();
            $rex->regenerate_coordinates();
        break;
        case 'expert_agent' :
            $rex = new Expert_Agent_PROCESSOR();
            $rex->regenerate_coordinates();
        break;
        case 'eac' :
             $rex = new EAC_API(false);
            $rex->regenerate_coordinates();
        break;
        case 'rockend' :
            $rex = new ROCKEND_PROCESSOR();
            $rex->regenerate_coordinates();
        break;
        case 'jupix' :
            $rex = new JUPIX_PROCESSOR();
            $rex->regenerate_coordinates();
        break;

    }
}

add_action('ajax_regenerate_coordinates','regenerate_coordinates');

/**
 * Regenerate status for listings.
 * @return json mixed
 * @since 3.5.0
 */
function switch_status() {

    $feedtype = get_option('feedtype');

    switch($feedtype) {

        case 'blm' :
            $rex = new BLM_PROCESSOR();
            $rex->switch_status();
        break;
        case 'reaxml' :
        case 'reaxml_fetch' :
            $rex = new REAXML_PROCESSOR();
            $rex->switch_status();
        break;
        case 'expert_agent' :
            $rex = new Expert_Agent_PROCESSOR();
            $rex->switch_status();
        break;
        case 'eac' :
             $rex = new EAC_API(false);
            $rex->switch_status();
        break;
        case 'rockend' :
            $rex = new ROCKEND_PROCESSOR();
            $rex->switch_status();
        break;
        case 'jupix' :
            $rex = new JUPIX_PROCESSOR();
            $rex->switch_status();
        break;

    }
}

add_action('ajax_switch_status','switch_status');

function process_missing_listing_agents() {

    $feedtype = get_option('feedtype');
    switch($feedtype) {

        case 'reaxml' :
         case 'reaxml_fetch' :
            $rex = new REAXML_PROCESSOR();
            $rex->process_missing_listing_agents();
        break;

    }

}

$feedsync_hook->add_action('ajax_process_missing_listing_agents','process_missing_listing_agents');

function convert_blm_to_xml() {
    include_once(CORE_PATH.'classes/class-bml-parser.php');
}

function is_user_logged_in() {

    return isset($_SESSION['uid']) ? true : false;
}

$feedsync_hook->add_action('init','restrict_access',30);
function restrict_access() {

    if( !is_user_logged_in() ) {
        header('Location: '.SITE_URL.'core/login.php');
        die;
    }
    global $page_now;

    $settings = array('settings');

    $help = array('help','info','license','geocode','updates','activate');

    if( defined('FEEDSYNC_SETTINGS_DISABLED') && FEEDSYNC_SETTINGS_DISABLED == true && in_array($page_now,$settings) ) {
        header('Location: '.SITE_URL);
        die;
    }

    if( defined('FEEDSYNC_SETTINGS_DISABLED') && FEEDSYNC_SETTINGS_DISABLED == true && in_array($page_now,$help) ) {
        header('Location: '.SITE_URL);
        die;
    }

}
function startsWith($haystack, $needle){
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle){
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function reset_feedsync_table() {

    if($_POST['pass'] != FEEDSYNC_PASS)
        die( json_encode(array( 'status'    =>  'danger', 'message'   =>  'incorrect password')) );


    $status_listing     =  fsdb()->query("TRUNCATE TABLE ".fsdb()->listing."");
    $status_temp        =  fsdb()->query("TRUNCATE TABLE ".fsdb()->temp );
    $status_users       =  fsdb()->query("TRUNCATE TABLE ".fsdb()->agent."");
    die(json_encode(array( 'status'    =>  'success', 'message'   =>  'All listings in the FeedSync database have been removed.')));
}

add_action('ajax_reset_feedsync_table','reset_feedsync_table');

function get_site_url() {
    return get_option('site_url');
}


function feedsync_js_vars() {

    $ajax_url = get_site_url().'core/ajax.php';

    if( is_permalinks_enabled() ) {

        $ajax_url = get_site_url().'ajax';

    }
    $vars = array(
        'site_url'      =>  get_site_url(),
        'ajax_url'      =>  $ajax_url,
        'images_url'    =>  get_site_url().'core/assets/images/'
    );

    echo '<script> var fs = '.json_encode($vars).'</script>';
}

add_action('feedsync_head','feedsync_js_vars');

function rrmdir($dir) {
    $ds = DIRECTORY_SEPARATOR;
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (is_dir($dir.$ds.$object))
           rrmdir($dir.$ds.$object);
         else
           unlink($dir.$ds.$object);
       }
     }
     rmdir($dir);
   }
 }

function feedsync_update_version() {

    $step = $_POST['step'];

    if($step == '')
        return;

    $dev_mode = defined('FEEDSYNC_UPDATE_MODE') ? FEEDSYNC_UPDATE_MODE : '';

    if($dev_mode == 'dev') {
        $fu = new feedsync_updater_dev();
    } else {
        $fu = new feedsync_updater();
    }



    switch ($step) {

        case 'clean':
            $fu->clean_upgrade_folder();
        break;

        case 'download':
            $fu->download_url();
        break;

        case 'unzip':
            $fu->unzip_package();
        break;

        case 'update':
            $fu->update_files();
        break;

        case 'clean_end':
            $fu->clean_upgrade_folder_end();
        break;

        case 'db_upgrade':
            $fu->db_upgrade();
        break;
    }

}

add_action('ajax_feedsync_update_version','feedsync_update_version');

function sitewide_notices() {
    global $sitewide_notices;
    if( !empty($sitewide_notices) ) {
        foreach($sitewide_notices as $notice) {
            if(is_fs_error($notice) ){
                echo '<div class="alert alert-'.$notice->get_error_code().'" >';
                    echo $notice->get_error_message();
                echo '</div>';
            }
        }
    }
}

function add_sitewide_notices($message,$code='warning') {
    global $sitewide_notices;
    $sitewide_notices[] = new FS_Error( $code, __( $message, "feedsync" ) );

}

/*
Dom helper functions
*/
/**
 * check if parent node has a child node
 * @param  [domDocument Object]
 * @param  [string]
 * @return boolean
 */
function has_node($item,$node){
    return $item->getElementsByTagName($node)->length == 0 ? false : true;
}

/**
 * get child nodes from parent nodes
 * @param  [domDocument Object]
 * @param  [string]
 * @return [domDocument Object]
 */
function get_nodes($item,$node){
    return $item->getElementsByTagName($node);
}

/**
 * Get first child node from parent node
 * @param  [domDocument Object]
 * @param  [string]
 * @return [domDocument Object]
 */
function get_first_node($item,$node){
    $nodes = get_nodes($item,$node);
    return $nodes->item(0);

}

/**
 * add node to element
 * @param [domDocument Object]
 * @param [string]
 * @param [mixed]
 */
function add_node($item,$node,$value){
    return $item->createElement($node, $value);
}

/**
 * get value of a node
 * @param  [domDocument Object]
 * @param  [string]
 * @return [mixed]
 */
function get_node_value($item,$node){
    if( has_node($item,$node) ) {
        return !is_null($item) ?  $item->getElementsByTagName($node)->item(0)->nodeValue : '';
    }
    return '';
}

/**
 * set node value and returns it
 * @param [domDocument Object]
 * @param [string]
 * @param [domDocument Object]
 */
function set_node_value($item,$node,$value){
    $item->getElementsByTagName($node)->item(0)->nodeValue = $value;
    return $item;
}

function feedsync_mark_fav() {


   $id = intval($_POST['id']);

    if($id <= 0)
        return;

    $listing = fsdb()->get_row("select * from ".fsdb()->listing." where id = {$id} ");

    $xmlFile = new DOMDocument('1.0', 'UTF-8');
    $xmlFile->preserveWhiteSpace = FALSE;
    $xmlFile->loadXML($listing->xml);
    $xmlFile->formatOutput = TRUE;
    $xpath = new DOMXPath($xmlFile);
    $item = $xmlFile->documentElement;

    if( ! has_node($item,'feedsyncFeaturedListing') ) {
        // if node not already exists, add it
        $element = add_node($xmlFile,'feedsyncFeaturedListing','yes');
        update_post_meta($listing->id,'fav_listing','yes');
        $item->appendChild($element);
    } else {
        // if node already exists, remove it
        $fl = get_first_node($item,'feedsyncFeaturedListing');
        $item->removeChild($fl);
        delete_post_meta($listing->id,'fav_listing');
    }
    $mod_date = date("Y-m-d H:i:s",strtotime($listing->mod_date) + 5 );
    $xmlFile->documentElement->setAttribute('modTime', $mod_date );
    $newxml         = $xmlFile->saveXML($xmlFile->documentElement);

    $db_data   = array(
        'xml'       =>  $newxml,
        'mod_date'  =>  $mod_date
    );

    $db_data    =   array_map(array( fsdb() ,'escape'), $db_data);

    $query = "UPDATE ".fsdb()->listing." SET
                    xml             = '{$db_data['xml']}',
                    mod_date        = '{$db_data['mod_date']}'
                    WHERE id        = '{$listing->id}'
                ";

   $status = fsdb()->query($query);
   print_exit($status);
}
add_action('ajax_feedsync_mark_fav','feedsync_mark_fav');

/** generate a unique ID everytime */
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0C2f ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0x2Aff ), mt_rand( 0, 0xffD3 ), mt_rand( 0, 0xff4B )
    );

}

function is_logging_enabled() {
    $status = get_option('feedsync_enable_logging') == 'on' ? true : false;

    if( $status ) {
        if( defined('DOING_AJAX') && DOING_AJAX && isset($_REQUEST['action']) && $_REQUEST['action'] == 'import_listings' ){
            return true;
        }

        if( defined('DOING_CRON') && DOING_CRON ){
            return true;
        }
    }

    return false;
}

function is_feedsync_license_valid() {

    $ls_valid = get_transient('feedsync_license_valid');

    if( !$ls_valid ){

        $feedsync_updater = new feedsync_updater();
        $ls_valid = $feedsync_updater->is_license_valid();
        set_transient( 'feedsync_license_valid', $ls_valid, 24*60*60 );
    }

    return $ls_valid;
}

/**
 * Make a Remote GET Request
 *
 * @param      <type>  $url      The url.
 * @param      array   $headers  The headers.
 * @param      array   $options  The options.
 *
 * @since 3.4.0
 * @since 3.4.5 verify set to false to avoid cacert.pem related errors.
 * @since 3.4.7 show exception in sitewide notice
 * @return     mixed  The Response.
 */
function feedsync_remote_get( $url, $headers = array(), $options = array('verify'   =>  false) ) {

    require_once '3rd-party/Requests/Requests.php';
    Requests::register_autoloader();

    try{
        return Requests::get( $url, $headers, $options);
    } catch (Exception $exc) {
        add_sitewide_notices( $exc->getMessage(), 'danger' );
        return false;
    }

}

/**
 * Make a Remote POST Request
 *
 * @param      <type>  $url      The url.
 * @param      array   $headers  The headers.
 * @param      array   $options  The options.
 *
 * @since 3.4.0
 * @return     mixed  The Response.
 */
function feedsync_remote_post( $url, $headers = array(), $options = array() ) {

    require_once '3rd-party/Requests/Requests.php';
    Requests::register_autoloader();
    return Requests::post( $url, $headers, $options);
}

/**
 * Returns the Response Body.
 *
 * @param      <type>  $response  The response
 *
 * @since 3.4.0
 * @return     <type>  ( description_of_the_return_value )
 * @since 3.4.2 check for response body added.
 */
function feedsync_remote_retrive_body( $response ) {
    return !empty( $response->body ) ? $response->body : null;
}

/**
 * Triggers when a function marked depricated is called
 * @param  [type] $function    [description]
 * @param  [type] $version     [description]
 * @param  [type] $replacement [description]
 * @return [type]              [description]
 */
function _deprecated_function( $function, $version, $replacement = null ) {

    /**
     * Fires when a deprecated function is called.
     *
     * @since 3.4.0
     *
     * @param string $function    The function that was called.
     * @param string $replacement The function that should have been called.
     * @param string $version     The version of WordPress that deprecated the function.
     */
    do_action( 'deprecated_function_run', $function, $replacement, $version );

    /**
     * Filters whether to trigger an error for deprecated functions.
     *
     * @param bool $trigger Whether to trigger the error for deprecated functions. Default true.
     */
    if ( FEEDSYNC_DEBUG && apply_filters( 'deprecated_function_trigger_error', true ) ) {

        if ( ! is_null( $replacement ) ) {
            trigger_error( sprintf( '%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', $function, $version, $replacement ) );
        } else {
            trigger_error( sprintf( '%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', $function, $version ) );
        }
    }
}

/**
 * Returns listing images along with listing data.
 * @return json mixed
 * @since 3.4.0
 */
function get_listing_data( $id = 0 ) {

    if( 0 == $id ) {
        $id         = intval( $_GET['id'] );
    }

    if($id <= 0)
        return;

    $alllistings = fsdb()->get_results("select * from ".fsdb()->listing." where id = {$id} ");

    $feedtype = get_option('feedtype');

    $images = array();
    if ( !empty( $alllistings ) ) {
        foreach ( $alllistings as $listing ) {
            $xmlFile = new DOMDocument('1.0', 'UTF-8');
            $xmlFile->preserveWhiteSpace = false;
            $xmlFile->loadXML($listing->xml);
            $xmlFile->formatOutput = true;
            $xpath = new DOMXPath($xmlFile);
            $item = $xmlFile->documentElement;

            switch($feedtype) {

                case 'expert_agent' :
                    // images are wrapped in picture > filename node value.
                    $imgs = $xpath->query('//picture[@lastchanged]/filename');
                    if ( !empty($imgs) ) {
                        foreach ($imgs as $k=>$img) {
                            $image_url = $img->nodeValue;
                            if( !empty( $image_url ) ) {
                                $images[] = $image_url;
                            }

                        }
                    }
                break;

                case 'blm' :
                    // target image tags, skip image text tags.
                    $imgs = $xpath->query("//*[starts-with(name(), 'MEDIA_IMAGE_') and not(starts-with(name(), 'MEDIA_IMAGE_TEXT_')) ]");
                    if (!empty($imgs)) {
                        foreach ($imgs as $k=>$img) {
                            $image_url = trim($img->getAttribute('url'));
                            if( !empty( $image_url ) ) {
                                $images[] = $image_url;
                            }

                        }
                    }
                break;

                case 'reaxml' :
                case 'reaxml_fetch' :
                case 'rockend' :
                case 'xml2u' :
                    $imgs = $xpath->query('//img[@url]');
                    if (!empty($imgs)) {
                        foreach ($imgs as $k=>$img) {
                            $image_url = trim($img->getAttribute('url'));
                            if( !empty( $image_url ) ) {
                                $images[] = $image_url;
                            }

                        }
                    }
                break;
                case 'jupix' :
                    $imgs = $xpath->query('//images/image');
                    if (!empty($imgs)) {
                        foreach ($imgs as $k=>$img) {
                            $image_url = trim($img->nodeValue);
                            if( !empty( $image_url ) ) {
                                $images[] = $image_url;
                            }

                        }
                    }
                break;

            }


        }
    }
    return [ 'images'    =>  $images, 'data' =>  $listing ];
}

function is_editing_allowed() {

    if( ( defined('FEEDSYNC_EDIT') && FEEDSYNC_EDIT ) || ( defined('FEEDSYNC_RESET') && FEEDSYNC_RESET ) ){
        return true;
    }

    return false;
}

/**
 * Returns the size of a file without downloading it, or -1 if the file
 * size could not be determined.
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @return The size of the file referenced by $url, or -1 if the size
 * could not be determined.
 */
function get_remote_file_size($url, $formatSize = true, $useHead = true) {

    if( empty( $url ) ) {
        return;
    }

    if (false !== $useHead) {
        stream_context_set_default(array('http' => array('method' => 'HEAD')));
    }
    $head = array_change_key_case(get_headers($url, 1));
    // content-length of download (in bytes), read from Content-Length: field
    $clen = isset($head['content-length']) ? $head['content-length'] : 0;

    // cannot retrieve file size, return "-1"
    if (!$clen) {
        return -1;
    }

    if (!$formatSize) {
        return $clen; // return size in bytes
    }

    $size = $clen;

    // fix for issue when content length is in array format.
    $clen = is_array( $clen ) ? end( $clen ) : $clen;
    switch ($clen) {
        case $clen < 1024:
            $size = $clen .' B'; break;
        case $clen < 1048576:
            $size = round($clen / 1024, 2) .' KiB'; break;
        case $clen < 1073741824:
            $size = round($clen / 1048576, 2) . ' MiB'; break;
        case $clen < 1099511627776:
            $size = round($clen / 1073741824, 2) . ' GiB'; break;
    }

    return $size; // return formatted size
}

function get_listing_count( $type = '', $status = '' ) {

    $query = "SELECT count(*) as `rows` FROM ".fsdb()->listing." WHERE 1 = 1 ";

    $types      = array('rental','property','residential','commercial','land','rural','business','commercialLand','holidayRental');
    $statuses   = array('leased','sold','withdrawn','current','offmarket','deleted');

    if( in_array($type,$types) ) {
        $query .= " AND type = '{$type}' ";
    }

    if( in_array($status,$statuses) ) {
        $query .= " AND status = '{$status}' ";
    }  elseif($status == 'all' ) {
        // do nothing
    } else {
        $query .= " AND status NOT IN ('withdrawn','offmarket','deleted') ";
    }

    return (int) fsdb()->get_var( $query );
}

/**
 * Missing map key warning message
 *
 * @since 3.5.0
 */
function feedsync_map_api_key_warning() { ?>

	<div class="alert-danger feedsync-warning-map-key" >
		<p>Ensure you have set a Google Maps API Key in Settings to display the map.<em></p>
	</div>
	<?php
}

/**
 * Test FTP connection.
 * @return json mixed
 * @since 3.5
 */
function test_reaxml_fetch_connection() {

    $host       = get_option('feedsync_reaxml_remote_host');
    $user       = get_option('feedsync_reaxml_remote_user');
    $pass       = get_option('feedsync_reaxml_remote_pass');
    $passive    = get_option('feedsync_reaxml_remote_passive');
    $ssl        = get_option('feedsync_reaxml_remote_is_ssl');
    $port       = get_option('feedsync_reaxml_remote_port');

    if( empty( $port ) ) {
        $port = 21;
    }

    if( empty( $host ) || empty( $user ) || empty( $pass ) ) {
        return;
    }

    if( 'yes' === $ssl ) {
        $connection_id = ftp_ssl_connect( $host, $port );
    } else {
        $connection_id = ftp_connect( $host, $port );
    }

    $login_result = @ftp_login( $connection_id, $user, $pass );
    if( true === $login_result ) {
        die( json_encode( array( 'status'   =>  'success', 'message'    =>  get_success_html( '<strong>Connection successful</strong>' ) ) ) );
    } else {
        die( json_encode( array( 'status'   =>  'fail', 'message'   =>  get_error_html( '<strong>Unable to connect, please check ftp details</strong>' ) ) ) );
    }
}
add_action('ajax_test_reaxml_fetch_connection','test_reaxml_fetch_connection');
