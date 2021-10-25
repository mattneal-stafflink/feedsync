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
	$page_now = 'images';
	get_header('Imported');
	get_listings_sub_header( $page_now );

			# The current directory
			$directory = dir(IMAGES_PATH);
			
			# If you want to turn on Extension Filter, then uncomment this:
			### $allowed_ext = array(".sample", ".png", ".jpg", ".jpeg", ".txt", ".doc", ".xls"); 
			$allowed_ext = array(".jpg", ".JPG",".jpeg", ".JPEG",".gif", ".GIF"); 



			## Description of the soft: list_dir_files.php  
			## Major credits: phpDIRList 2.0 -(c)2005 Ulrich S. Kapp :: Systemberatung ::

			$do_link = TRUE; 
			$sort_what = 0; //0- by name; 1 - by size; 2 - by date
			$sort_how = 0; //0 - ASCENDING; 1 - DESCENDING
			$new_array = [];

			# # #
			function dir_list($dir){ 
				$i=0; 
				$dl = array(); 
				if ($hd = opendir($dir))    { 
					while ($sz = readdir($hd)) {  
						if (preg_match("/^\./",$sz)==0) $dl[] = $sz;$i.=1;  
					} 
				closedir($hd); 
				} 
				asort($dl); 
				return $dl; 
			} 
			if ($sort_how == 0) { 
				function compare0($x, $y) {  
					if ( $x[0] == $y[0] ) return 0;  
					else if ( $x[0] < $y[0] ) return -1;  
					else return 1;  
				}  
				function compare1($x, $y) {  
					if ( $x[1] == $y[1] ) return 0;  
					else if ( $x[1] < $y[1] ) return -1;  
					else return 1;  
				}  
				function compare2($x, $y) {  
					if ( $x[2] == $y[2] ) return 0;  
					else if ( $x[2] < $y[2] ) return -1;  
					else return 1;  
				}  
			}else{ 
				function compare0($x, $y) {  
					if ( $x[0] == $y[0] ) return 0;  
					else if ( $x[0] < $y[0] ) return 1;  
					else return -1;  
				}  
				function compare1($x, $y) {  
					if ( $x[1] == $y[1] ) return 0;  
					else if ( $x[1] < $y[1] ) return 1;  
					else return -1;  
				}  
				function compare2($x, $y) {  
					if ( $x[2] == $y[2] ) return 0;  
					else if ( $x[2] < $y[2] ) return 1;  
					else return -1;  
				}  

			} 

			################################################## 
			#    We get the information here 
			################################################## 

			$i = 0; 
			while($file=$directory->read()) { 
				//$file = strtolower($file);
				$ext = strrchr($file, '.');
				if (isset($allowed_ext) && (!in_array($ext,$allowed_ext)))
					{
						// dump 
					}
				else { 
					$temp_info = stat(IMAGES_PATH.$file); 
					$new_array[$i][0] = $file; 
					$new_array[$i][1] = $temp_info[7]; 
					$new_array[$i][2] = $temp_info[9]; 
					$new_array[$i][3] = date("F d, Y", $new_array[$i][2]); 
					$i = $i + 1; 
				} 
			} 
			$directory->close(); 

			################################################## 
			# We sort the information here 
			################################################# 

			if(!empty($new_array)) {
				switch ($sort_what) { 
					case 0: 
							usort($new_array, "compare0"); 
					break; 
					case 1: 
							usort($new_array, "compare1"); 
					break; 
					case 2: 
							usort($new_array, "compare2"); 
					break; 
				}
			} 

			############################################################### 
			#    We display the infomation here 
			############################################################### 
			
			$results = $new_array;
			
			$i2 = count($new_array); 
			
			// how many records should be displayed on a page?
		    $records_per_page = get_option('feedsync_gallery_pagination');

		    // instantiate the pagination object
		    $pagination = new Zebra_Pagination();

		    // set position of the next/previous page links
		    $pagination->navigation_position(isset($_GET['navigation_position']) && in_array($_GET['navigation_position'], array('left', 'right')) ? $_GET['navigation_position'] : 'outside');

		    // the number of total records is the number of records in the array
		    $pagination->records(count($results));

		    // records per page
		    $pagination->records_per_page($records_per_page);

		    // here's the magick: we need to display *only* the records for the current page
		    $results = array_slice(
		        $results,                                             //  from the original array we extract
		        (($pagination->get_page() - 1) * $records_per_page),    //  starting with these records
		        $records_per_page                                       //  this many records
		    );

		    if( isset( $_GET['id'] ) && $_GET['id'] >= 0 ) {
				$results = get_listing_data();
			}
		    
			?>
			<div class="panel panel-default">
				<!-- Default panel contents -->
				<div class="panel-heading">Images
					<?php
						if( isset( $_GET['id'] ) && $_GET['id'] >= 0 ) {
							echo ' : <span class="fs-image-address">'. $results['data']->address.'</span>';
						}
					?>
				</div>
					<div class="table fs-images-table"> 
					
							<?php
								
								if( isset( $_GET['id'] ) && $_GET['id'] >= 0 ) {

									foreach($results['images'] as $result) { 
										if (!$do_link) { 
											
										}else{ 
											$line = '<span><a rel="prettyPhoto" href="'.$result . '">' 
														. '<img  class="feedsync-image" src="' . $result .  '" width="149" height="150" />' .
													"</a><span class='fs-image-size'>".get_remote_file_size($result)."</span></span>"; 
											
										} 
										echo $line; 
									}

								} else {
									foreach($results as $result) { 
										if (!$do_link) { 
											
										}else{ 
											$line = '<span><a rel="prettyPhoto" href="'.IMAGES_URL .  $result[0] . '">' 
														. '<img  class="feedsync-image" src="' . IMAGES_URL . $result[0] .  '" width="149" height="150" />' .
													"</a></span>"; 
											
										} 
										echo $line; 
									}
								}
								 
							?>
					</div>
			<?php if( empty( $_GET['id'] ) ) : ?>
			<div class="row"> <div class="col-lg-12"> <?php echo $pagination->render(true); ?> </div> </div>
			<?php endif; ?>
			</div>
		
<?php echo get_footer(); ?>
