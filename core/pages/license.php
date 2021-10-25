<?php
/*

Title: XML Feed Processor Geocode Test Page
Program Author URI: http://realestateconnected.com.au/
Description: Program created and written to process Australian REAXML feed for easy import into WordPress. 
The program will process the input files that are places in the XML directory from your feed provider and save the results into 
three XML output files in the /feedsync/outputs directory. These files contain the results of the input files.

Author: Merv Barrett
Author URI: http://realestateconnected.com.au/

Version: 1.1 (beta)

Version History
	See history.txt
*/

	require_once('../../config.php');
	require_once('../functions.php');

	
	$page_now = 'license';
	do_action('init');
	get_header($page_now);
?>

		<div class="page-header">
			<h1>License Status</h1>
		</div>
		<?php echo feedsync_settings_navigation($page_now) ?>
		
		<?php 
		$request = 'check_license';
		echo feedsync_license_validator( $request ); 
		?>
		
		<div>
		<a class="btn btn-sm btn-warning" style="margin-bottom: 2em;" type="button" href="updates.php">Check For Updates</a>
		</div>

<?php echo get_footer(); ?>
