<?php
/*
 * Title: FeedSync REAXML Pre-processor
 * Program URI: https://www.easypropertylistings.com.au
 * Description: Program created and written to process Australian REAXML feed for easy import into WordPress.
 * The program will process the input files and store them in a database on your server. The output is generated on the fly as requested by your import software.
 * Author: Merv Barrett
 * Author URI: http://realestateconnected.com.au/
 * Version: 2.2
 *
 * Copyright 2016 Merv Barrett
 *
 * FeedSync is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

require_once(CORE_PATH.'functions.php');
require_once(CORE_PATH.'classes/class-chunks.php');
require_once(CORE_PATH.'classes/class-zip.php');

/**
 * Pre processes zip files & other non xml formats, fix image paths
 * and breaks smaller files to chunks to avoid time out errrors
 */
class FEEDSYNC_SETUP_PROCESSOR {

	function __construct() {

		$this->pre_process_zip();

		$this->pre_process_blm();

		$this->create_chunks();
	}

	/**
	 * looks for zip folder in input folder and extracts it
	 * 
	 */
	function pre_process_zip() {
		// Zip Processor
		$z_ex = get_files_list(get_path('input'),"zip|ZIP");
		if(!empty($z_ex)) {
			$z_ex = array_map('trim', $z_ex);
			if ( class_exists('ZipArchive')  ) {
				$zip = new zip;
					foreach($z_ex as $z) { 
						if( $zip->open($z) === TRUE) {
							if( $zip->extractTo( get_path('temp') ) ) {
								$imgs_ex = get_files_list(get_path('temp'),"jpg|png|jpeg|JPG|JPEG|PNG");
								if(!empty($imgs_ex)) {
									foreach($imgs_ex as $img_ex) {
										$img_name = basename($img_ex);
										if(!empty($img_name)) {

											if (!file_exists(get_path('images') )) {
												@mkdir(get_path('images'), 0777, true);
											}

											if( rename ( $img_ex,  get_path('images').$img_name ) ) {

											}
										}
									}
								}

								$xmls_ex = get_recursive_files_list(get_path('temp'),"xml|XML|blm|BLM");
								if(!empty($xmls_ex)) {
									foreach($xmls_ex as $xml_ex) {
										$xml_name = basename($xml_ex);
										if(!empty($xml_name)) {
											if( rename ( $xml_ex,  get_path('input').$xml_name ) ) {
												@chmod( get_path('input').$xml_name , 0644);
											}
										}
									}
								}
							}
							$zip->close();
							$z_name = basename($z);
							@rename ( $z,  get_path('zip').$z_name );
						 }
					}
					
			}
		}
	}


	/**
	 * Looks for .blm files in input folder and converts them to xml for further processing
	 */
	function pre_process_blm() {
		// process blm files to xml
		convert_blm_to_xml();
	}

	/**
	 * Creates larger xml files to chunks for faster processing per file & avoiding timeouts
	 */
	function create_chunks(){

		// blank is for root path of input folder. Used by reaxml format
		//$subpaths = array('','blm','expert_agent');
		// @update no subpaths . every feed type will sit in input folder
		$subpaths = array('');

		foreach($subpaths as $subpath) {

			// process reaxml files
			
			if( is_dir(get_path('input').$subpath) ) :
				$x_ex = get_files_list(get_path('input').$subpath,"xml|XML");

				if( !empty($x_ex) ) {

					foreach($x_ex as $path) {
						@chmod($path, 0644);
						if(filesize($path) > FEEDSYNC_MAX_FILESIZE) {
							$xml_chunks = new XMl_CHUNKS($path);
							$xml_chunks->create_chunk(get_path('input').$subpath,get_path('processed').$subpath);
						}
					}
				}
			endif;

		}

	}

}

