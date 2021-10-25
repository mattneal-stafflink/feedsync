<?php

require_once('../config.php');
require_once('functions.php');
require_once(CORE_PATH.'classes/class-chunks.php');
require_once(CORE_PATH.'classes/class-zip.php');
require_once(CORE_PATH.'classes/class-feedsync-setup-preprocessor.php');
$feedsync_hook->do_action('init');

set_time_limit(0);

new FEEDSYNC_SETUP_PROCESSOR();

get_header('process'); ?>

<?php if ( get_option('force_geocode') == 'on' ) { ?>
	<div class="alert bg-warning">
		<p>Force Geocode is set to Enabled, once you have re-processed your coordinates, set to Disabled in the Settings.</p>
	</div>
<?php } ?>


<div class="panel panel-default">
	<div class="panel-heading">
		<input type="button" id="import_listings" value="Process" class="btn btn-primary">
		<?php feedsync_manual_processing_buttons(); ?>
	</div>
	<div class="alert alert-success fs-status-panel">
		<p>Click on process to start processing files.</p>
	</div>
</div>

<?php echo get_footer(); ?>

