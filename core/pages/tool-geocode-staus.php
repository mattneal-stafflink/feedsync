<?php
/**
 * Geocode Page Testing Maps API
 *
 * @package     FEEDSYNC
 * @subpackage  pages/googletools
 * @copyright   Copyright (c) 2020, Merv Barrett
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0

 * @since       3.4.0 Corrected notice wrapping to bootstrap alert vs notice to better wrap.
 */

require_once('../../config.php');
require_once('../functions.php');


$page_now = 'geocode';
do_action('init');
$input_addr = '1600 Amphitheatre Parkway, Mountain View, CA';
$addr = $input_addr;
$addr = str_replace(" ", "+", $addr);
$googleapiurl = "https://maps.google.com/maps/api/geocode/json?address=$addr&sensor=false&key=".get_option('feedsync_google_api_key');
$geo_response   = feedsync_remote_get( $googleapiurl );
$geocode        = feedsync_remote_retrive_body( $geo_response );
$output = json_decode($geocode);
$result = (array) json_decode($geocode);
get_header('geocode');
?>
	<div class="page-header">
		<h1>Geocode Status</h1>
	</div>

	<h3 style="margin-top:2em;">Test Geocode Credit Status</h3>

	<p>FeedSync uses the <a href="https://developers.google.com/maps/documentation/geocoding/">Google Geocoding API</a> to convert the property addresses in your XML file during import into lat/long coordinates so your website can display the address with a map. <em>NOTE: Google has usage limits of 2,500 requests per day and you can check the status with the button below.</em></p>

	<p>To allow geocoding to function correctly please create a <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">Google Maps API Key</a> and enter it on the <a href="<?php echo feedsync_nav_link('../settings.php') ?>">Settings page</a>.</p>

	<h4>Processing Address</h4>

	<p><?php echo $input_addr; ?></p>

	<div class="button-margin" style="margin: 2em 0;">
		<div class="fs-google-api-notices">
			<?php
			if ($result['status'] == 'OK') { ?>
				<div class="label label-success" style="font-size: 100%;">
					<span class="fs-google-message">Geocode Successful!</span>
				</div>
			<?php
			} else { ?>
				<div class="label label-danger" style="font-size: 100%;">
					<span  class="fs-google-message">Warning :( <?php echo $result['status']; ?></span>
				</div>
			<?php
			}

			if( get_option('feedsync_google_api_key') == '') {
				?>
				<div class="alert alert-warning">
					<span class="fs-google-message">Please provide Google MAP API key for generating listing coordinates on settings page.</span>
				</div>
				<?php
			}

			if ( !empty($result['error_message']) ) { ?>
				<div class="alert alert-danger">
					<span class="fs-google-message">
						<?php echo $result['error_message']; ?>
					</span>
				</div> <?php
			}

			?>
		</div>
	</div>
<?php echo get_footer(); ?>
