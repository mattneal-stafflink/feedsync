<?php
/*
Title: XML Feed Processor Help Page
Program Author URI: http://realestateconnected.com.au/
Description: Program created and written to process Australian REAXML feed for easy import into WordPress.
The program will process the input files that are places in the XML directory from your feed provider and save the results into
three XML output files in the /feedsync/outputs directory. These files contain the results of the input files.

Author: Merv Barrett
Author URI: http://realestateconnected.com.au/

Version: 2.0

*/
	require_once('../../config.php');
	require_once('../functions.php');
	$page_now = 'info';
	do_action('init');

	
	get_header('info');

?>

	<div class="page-header">
		<h1>System Status</h1>
	</div>

	<h3 style="margin-top:2em;">Check if all necessary components are up & working before importing</h3>
	<div class="row">
  		<div class="col-md-6 col-sm-12">
			<ul class="list-group">
				<li class="list-group-item">
					<?php 
						if (version_compare(PHP_VERSION, '5.6.0') >= 0) {
						    echo '<span class="label label-default">'.__('PHP Version').'</span>';
						    echo '<span class="label label-success pull-right">'.PHP_VERSION.'</span>';
						} else {
							echo '<span class="label label-default">'.__('PHP Version').'</span>';
						    echo '<span class="label label-danger pull-right">At least 5.6 is required</span>';
						    echo '<span class="label label-danger pull-right">'.PHP_VERSION.'</span>';

						}
					?>
				</li>
				<li class="list-group-item">
					<?php 
						if (extension_loaded('zip')) {
						    echo '<span class="label label-default">'.__('Zip Extension').'</span>';
						    echo '<span class="label label-success pull-right">'.__('Enabled','feedsync').'</span>';
						} else {
							echo '<span class="label label-default">'.__('Zip Extension').'</span>';
						    echo '<span class="label label-danger pull-right">'.__('Not Enabled','feedsync').'</span>';
						}
					?>
				</li>
				<li class="list-group-item">
					<?php 
						if (extension_loaded('curl')) {
							echo '<span class="label label-default">'.__('Curl Extension').'</span>';
						    echo '<span class="label label-success pull-right">'.__('Enabled','feedsync').'</span>';
						} else {
							echo '<span class="label label-default">'.__('Curl Extension').'</span>';
						    echo '<span class="label label-danger pull-right">'.__('Not Enabled','feedsync').'</span>';
						}
					?>
				</li>
				<li class="list-group-item">
					<?php 
						if ( ini_get('allow_url_fopen') ) {
							echo '<span class="label label-default">'.__('File Get Contents').'</span>';
						    echo '<span class="label label-success pull-right">'.__('Enabled','feedsync').'</span>';
						} else {
							echo '<span class="label label-default">'.__('File Get Contents').'</span>';
						    echo '<span class="label label-danger pull-right">'.__('Not Enabled','feedsync').'</span>';
						}
					?>
				</li>
				<li class="list-group-item">
					<?php 
						if (extension_loaded('iconv')) {
							echo '<span class="label label-default">'.__('Iconv Extension').'</span>';
						    echo '<span class="label label-success pull-right">'.__('Enabled','feedsync').'</span>';
						} else {
							echo '<span class="label label-default">'.__('Iconv Extension').'</span>';
						    echo '<span class="label label-danger pull-right">'.__('Not Enabled','feedsync').'</span>';
						}
					?>
				</li>
				<li class="list-group-item">
					<?php 
						if (extension_loaded('ftp')) {
							echo '<span class="label label-default">'.__('FTP Extension').'</span>';
						    echo '<span class="label label-success pull-right">'.__('Enabled','feedsync').'</span>';
						} else {
							echo '<span class="label label-default">'.__('FTP Extension').'</span>';
						    echo '<span class="label label-danger pull-right">'.__('Not Enabled','feedsync').'</span>';
						}
					?>
				</li>
				<li class="list-group-item">
					<?php 
						if (extension_loaded('dom')) {
							echo '<span class="label label-default">'.__('DOM Extension').'</span>';
						    echo '<span class="label label-success pull-right">'.__('Enabled','feedsync').'</span>';
						} else {
							echo '<span class="label label-default">'.__('DOM Extension').'</span>';
						    echo '<span class="label label-danger pull-right">'.__('Not Enabled','feedsync').'</span>';
						}
					?>
				</li>
				<?php
					$paths = array(
								INPUT_PATH     =>	__('Input Folder','feedsync'),
								OUTPUT_PATH    =>	__('Output Folder','feedsync'),
								IMAGES_PATH    =>	__('Images Folder','feedsync'),
								PROCESSED_PATH =>	__('Processed Folder','feedsync'),
								ZIP_PATH       =>	__('ZIP Folder','feedsync'),
								TEMP_PATH      =>	__('Temp Folder','feedsync'),
								LOG_PATH       =>	__('Log File','feedsync')
							);

				    foreach($paths as $path =>	$name) { ?>

						<li class="list-group-item">
							<?php 
								if ( is_writable($path) ) {
								   echo '<span class="label label-default">'.__($name).'</span>';
								    echo '<span class="label label-success pull-right">'.__('Writable','feedsync').'</span>';
								} else {
									echo '<span class="label label-default">'.__($name).'</span>';
								    echo '<span class="label label-danger pull-right">'.__('Not Writable','feedsync').'</span>';

								}
							?>
						</li>
				        
				    <?php }
				?>
			</ul>
		</div>
	</div>

<?php echo get_footer(); ?>
