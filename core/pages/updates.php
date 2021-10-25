<?php
/*

Title: XML Feed Processor Geocode Test Page
Program Author URI: http://realestateconnected.com.au/
Description: Program created and written to process Australian REAXML feed for easy import into WordPress.
The program will process the input files that are places in the XML directory from your feed provider and save the results into
three XML output files in the /feedsync/outputs directory. These files contain the results of the input files.

Author: Merv Barrett
Author URI: http://realestateconnected.com.au/

Version: 2.0
@since 3.4.2 Fixed update notification class.
Version History
	See history.txt
*/
	require_once('../../config.php');
	require_once('../functions.php');
	$page_now = 'updates';
	
	global $current_version;
	do_action('init');
	get_header($page_now);
?>




		<div class="page-header">
			<h1>Check for Updates</h1>
			<p><?php echo '<strong>Installed:</strong> ' , $application_name , ' ' , $current_version ?></p>
		</div>
		<?php echo feedsync_settings_navigation($page_now) ?>

		<?php
			$dev_mode = defined('FEEDSYNC_UPDATE_MODE') ? FEEDSYNC_UPDATE_MODE : '';

		    if($dev_mode == 'dev') {
		        $feedsync_updater = new feedsync_updater_dev();
		    } else {
		        $feedsync_updater = new feedsync_updater();
		    }

			$feedsync_updater->init() ;
			 if ( $feedsync_updater->version > $current_version ) { 

		 		if( $feedsync_updater->is_license_valid() ) {

					$feedsync_updater->init() ;?>

			        <div class="alert alert-success fs-status-panel">
			            <strong>New Version Available!</strong> <?php echo $feedsync_updater->response['name'] , ' v', $feedsync_updater->response['new_version'] ?>
			        </div>

			        <div id="feedsync-settings-navigation">
				        <ul class="nav nav-pills">

					        <li>
					            <a class="btn btn-info" id="feedsync-upgrade" href="#" data-link="<?php echo $feedsync_updater->response['download_link']; ?>">Update</a>

					        </li>
					        <li>
					            <a class="btn btn-info" download="true" href="<?php echo $feedsync_updater->response['download_link']; ?>">Download</a>

					        </li>
					    </ul>
				    </div>
			    <?php } else { ?>
			    <div class="alert alert-success">
		            <strong>New Version Available!</strong> <?php echo $feedsync_updater->response['name'] , ' v', $feedsync_updater->response['new_version'] ?>
		        </div>
			    <div class="alert alert-warning">
		        	<p>
		        		Looks like your license key is Missing on Invalid. <a href="<?php echo CORE_URL.'settings.php#license'; ?>">Check your license</a> and try again. If you need a key purchase one <a href="//easypropertylistings.com.au/extensions/feedsync/">here</a>.
		        	</p>
		        </div>
		        <?php } ?>
				    <div class="panel panel-default">
				        <div class="panel-heading">
				            <h3 class="panel-title">Changelog</h3>
				        </div>
				        <div class="panel-body">
				            <?php
					    		echo $feedsync_updater->changelog;
					    	?>
				        </div>
				    </div>
			    <?php

			    }else { ?>
			        <div class="alert alert-success">
			            <strong>You're Up To Date!</strong> You are running the latest version of FeedSync <?php echo $feedsync_updater->version ?>.
			        </div>
			    <?php
			    }
		
		?>

<?php echo get_footer(); ?>
