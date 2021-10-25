<?php

/**
 * Priority is given to settings . If one of the settings is not defined than constants will be referred
 * acts as backup if one of te constants definition is missing in config.php
 */

function define_constants() {

    if( !defined('FEEDSYNC_MAX_FILESIZE') )
        define('FEEDSYNC_MAX_FILESIZE',512000 );

    if( !defined('FEEDSYNC_CHUNK_SIZE') )
        define('FEEDSYNC_CHUNK_SIZE',50 );

    if( !defined('FEEDTYPE') )
        define('FEEDTYPE','reaxml' );

    if( !defined('GEO_ENABLED') )
        define('GEO_ENABLED','OFF' );

    if( !defined('FORCE_GEOCODE') )
        define('FORCE_GEOCODE','OFF' );

    if( !defined('FEEDSYNC_PAGINATION') )
        define('FEEDSYNC_PAGINATION',   1000 );

    if( !defined('FEEDSYNC_GALLERY_PAGINATION') )
        define('FEEDSYNC_GALLERY_PAGINATION',   24 );

    if( !defined('CORE_PATH') )
        define('CORE_PATH', SITE_ROOT.'core'.DS );

    if( !defined('UPGRADE_PATH') )
        define('UPGRADE_PATH', SITE_ROOT.'upgrades'.DS );


    if( !defined('VIEW_PATH') )
        define('VIEW_PATH', CORE_PATH.'views'.DS );

    if( !defined('ASSETS_PATH') )
        define('ASSETS_PATH',   CORE_PATH.'assets'.DS );

    if( !defined('INPUT_PATH') )
        define('INPUT_PATH',    SITE_ROOT.'input'.DS );

    if( !defined('OUTPUT_PATH') )
        define('OUTPUT_PATH',   SITE_ROOT.'output'.DS );

    if( !defined('IMAGES_PATH') )
        define('IMAGES_PATH',   OUTPUT_PATH.'images'.DS );

    if( !defined('PROCESSED_PATH') )
        define('PROCESSED_PATH',SITE_ROOT.'processed'.DS );

    if( !defined('ZIP_PATH') )
        define('ZIP_PATH',  PROCESSED_PATH.'zips'.DS );

    if( !defined('TEMP_PATH') )
        define('TEMP_PATH', ZIP_PATH.'temp'.DS );

    if( !defined('LOG_PATH') )
        define('LOG_PATH',  SITE_ROOT );

    if( !defined('LOGS_FOLDER_PATH') )
        define('LOGS_FOLDER_PATH',  SITE_ROOT.'logs'.DS );

    if( !defined('LOG_FILE') )
        define('LOG_FILE',  'error.log' );

    if( !defined('FEEDSYNC_DEBUG') )
        define('FEEDSYNC_DEBUG' , false );

    if( !defined('FEEDSYNC_DEBUG_DISPLAY') )
        define('FEEDSYNC_DEBUG_DISPLAY' , false );

    if( !defined('FEEDSYNC_TIMEZONE') )
        define('FEEDSYNC_TIMEZONE', 'Australia/Victoria' );

    if( !defined('FEEDSYNC_ADMIN') )
        define('FEEDSYNC_ADMIN', 'admin' );

    if( !defined('FEEDSYNC_PASS') )
        define('FEEDSYNC_PASS', 'password' );

    if( !defined('DB_CHARSET') )
        define('DB_CHARSET', 'utf8mb4' );

    if( !defined('DB_COLLATE') )
        define('DB_COLLATE', '' );

    if( !defined('FS_TABLE_PREFIX') )
        define('FS_TABLE_PREFIX', 'fs_' );
    
    if( !defined('PLUGINS_PATH') )
        define('PLUGINS_PATH',  SITE_ROOT.'plugins'.DS );
    
    define( 'KB_IN_BYTES', 1024 );
}

add_action('init_constants','define_constants',1);

/**
 * Site URL Based Constants, must be initiated after DB init.
 * 
 * @since 3.4.2
 */
function init_url_constants() {

    if( !defined('SITE_URL') )
        define('SITE_URL',  get_site_url() );

    if( !defined('CORE_URL') )
        define('CORE_URL',  SITE_URL.'core/' );

    if( !defined('ASSETS_URL') )
        define('ASSETS_URL',    CORE_URL.'assets/' );

    if( !defined('CSS_URL') )
        define('CSS_URL',   ASSETS_URL.'css/' );

    if( !defined('JS_URL') )
        define('JS_URL',    ASSETS_URL.'js/' );

    if( !defined('INPUT_URL') )
        define('INPUT_URL', SITE_URL.'input/' );

    if( !defined('OUTPUT_URL') )
        define('OUTPUT_URL',    SITE_URL.'output/' );

    if( !defined('IMAGES_URL') )
        define('IMAGES_URL',    SITE_URL.'output/images/' );

    if( !defined('PROCESSED_URL') )
        define('PROCESSED_URL', SITE_URL.'processed/' );

    if( !defined('ZIP_URL') )
        define('ZIP_URL',   PROCESSED_URL.'zips/' );

    if( !defined('TEMP_URL') )
        define('TEMP_URL',  ZIP_URL.'temp/' );

    if( !defined('LOGS_FOLDER_URL') )
        define('LOGS_FOLDER_URL',  SITE_URL.'logs/' );
    
    /**
     * path for which session cookie is stored
     * @since 3.5
     */
    if( !defined('COOKIEPATH') )
        define( 'COOKIEPATH', preg_replace( '|https?://[^/]+|i', '', SITE_URL ) );

}

add_action('init_url_constants','init_url_constants',1);