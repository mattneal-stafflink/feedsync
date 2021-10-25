<?php
/**
 * Title: XML Feed Processor Images Page
 * Author: Merv Barrett
 * Author URI: http://realestateconnected.com.au/
 * Version: 2.0
 */
require_once('../../config.php');
require_once('../functions.php');
do_action('init');
global $pagination;
$page_now = 'listing-details';
get_header('Imported');
get_listings_sub_header( $page_now );

if( isset( $_GET['id'] ) && $_GET['id'] >= 0 ) {
	$results = get_listing_data();
}

?>
<div class="panel panel-default">
	<!-- Default panel contents -->
	<div class="panel-heading">
		<?php
			if( isset( $_GET['id'] ) && $_GET['id'] >= 0 ) {
				echo '<span class="fs-image-address">'. $results['data']->address.'</span>';
			}
		?>
	</div>
	<div class="fs-listing-map-wrap">
		<?php
			$api_key      = get_option( 'feedsync_google_api_key' );
			$show_warning = apply_filters( 'feedsync_show_map_key_warning', true );
		
			if ( empty( $api_key ) ) {
				if ( $show_warning && is_user_logged_in() ) {
					feedsync_map_api_key_warning();
				}
				
			} else { ?>
				<div id="fs-listing-map" class="fs-listing-map" data-address="<?php echo $results['data']->address; ?>">
				</div> <?php
			}
		?>
		
	</div>
</div>

<div class="panel panel-default">
	<!-- Default panel contents -->
	<div class="panel-heading">
		<strong>
			Images
		</strong>
	</div>
	<div class="table fs-images-table"> 
	
			<?php
				
				if( isset( $_GET['id'] ) && $_GET['id'] >= 0 ) {
					
					foreach($results['images'] as $result) { 
						
						$line = '<span class="image-holder"><a rel="prettyPhoto" href="'.$result . '">' 
										. '<img  class="feedsync-image" src="' . $result .  '" width="149" height="150" />' .
									"</a><span class='fs-image-info'>
											<span class='fs-image-size'>".get_remote_file_size($result)."</span>
											<span class='fs-image-dimension'></span>
										</span>
								</span>"; 
						echo $line; 
					}

				}
				 
			?>
	</div>

</div>
		
<?php echo get_footer(); ?>
