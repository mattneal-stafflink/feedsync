<?php
/**
 * The base configurations of FeedSync.
 *
 * This file has the following configurations: MySQL settings, License, URL
 * processing settings, and ABSPATH.
 *
 * You can get the MySQL settings from your web host.
 *
 * @package FeedSync
 * @since 1.0
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for FeedSync */
define('DB_NAME', 'database_name' );

/** MySQL database username */
define('DB_USER', 'database_user_name' );

/** MySQL database password */
define('DB_PASS', 'database_password' );

/** MySQL hostname */
define('DB_HOST', 'localhost' );

// ** FeedSync Login Details - Set your username and password to prevent unauthorised access ** //
/** Username to access feedsync  */
define('FEEDSYNC_ADMIN', 'admin' );

/** Password to access feedsync  */
define('FEEDSYNC_PASS', 'password' );

/** Set to true to enable listing database reset and selecting and deleting entries */
define('FEEDSYNC_RESET', false );


/** Uncomment to enable setting of site URL  **/
//define('SITE_URL', 'http://YOUR_URL/XML/feedsync/' );

/** Uncomment to disable and hide the settings page **/
//define('FEEDSYNC_SETTINGS_DISABLED' , true );

/** Uncomment to disable and hide the help page **/
//define('FEEDSYNC_HELP_DISABLED' , true );

/**
 * Keep a record of the FTP account you created for your provider.
 *
 * We recommended creating an unique FTP account for the feed provider which limits access to the
 * YOUR_URL.com.au/XML/feedsync/input folder. They don't need access to anything else on your server. This lets
 * you move FeedSync later and all you have to do is edit the providers FTP Account Directory on your hosting.
 *
 * REAXML FTP Account Details
 *
 * FTP Account:	your_site.com.au
 * User name: 	reaxml@your_site.com.au
 * Password:	XSDEerf12a (10 characters is recommended for older providers)
 * Directory:	/
 */

/** FeedSync Debug  */
define('FEEDSYNC_DEBUG', false );


/* That's all, stop editing! Happy REAXML processing with FeedSync. */

/** Absolute path to the FeedSync directory. */
if( defined('DIRECTORY_SEPARATOR') ){
	define('DS',constant('DIRECTORY_SEPARATOR') );
} else {
	define('DS','/');
}
define('SITE_ROOT',dirname(__FILE__).DS );
