<?php

class feedsync_updater {

	private $api = 'https://easypropertylistings.com.au/';

	public $response;

	function __construct() {
		$this->init();
	}

	/**
	 * Fetch the update info
	 * @since  :      1.0.0
	 * @return [type] [description]
	 */
	function init() {
		$this->get_update_info();

		$this->parse_info();
	}


	/**
	 * Returns the download url or false if download
	 * url is not available
	 * @since  :      1.0.0
	 * @return [type] [description]
	 */
	function download_package_url() {

		return isset($this->response['download_link']) ? $this->response['download_link'] : false;
	}

	/**
	 * Prior to downloading updates, empty the upgrade dir
	 * @since  :      1.0.0
	 * @return [type] [description]
	 */
	function clean_upgrade_folder() {

		$dir = get_path('upgrade').'feedsync';

		rrmdir($dir);

		$response = array(
			'message'		=>	'Upgrade folder is cleaned, downloading updated version. Please wait...',
			'next_step'		=>	'download',
			'status'		=>	'processing'
		);

		echo json_encode($response); die;
	}

	/**
	 * after finishing updates, clean upgrade folder again and mark files updation process complete
	 * @since  :      1.0.0
	 * @return [type] [description]
	 */
	function clean_upgrade_folder_end() {

		$missing_processed = get_option( 'is_missing_processed' );

		if( empty( $missing_processed ) ) {
			$this->check_missing_files();
		}

		if( 'init' === $missing_processed  ) {
			update_option( 'is_missing_processed', 'yes' );
		}
		

		$dir = get_path('upgrade').'feedsync';

		rrmdir($dir);

		$response = array(
			'message'			=>	'Files have been successfully updated. Please wait while we upgrade your database ..',
			'next_step'			=>	'db_upgrade',
			'status'			=>	'processing'
		);

		update_option( 'is_request_available', 'yes' );
		echo json_encode($response); die;
	}

	/**
	 * Final step after completing file updates, upgrade db for latest code
	 * @return [type]
	 */
	function db_upgrade() {

		$feedsync_upgrader = new feedsync_upgrade();
    	$feedsync_upgrader->dispatch();
    	
		set_transient( 'feedsync_update_available', 'no' , 24*60*60 );

		$response = array(
			'message'			=>	'Update process is successfully completed. Enjoy!',
			'next_step'			=>	'db_upgrade',
			'status'			=>	'complete'
		);

		echo json_encode($response); die;
	}

	/**
	 * Download latest package in upgrades folder
	 * @since  :      1.0.0
	 * @return [type] [description]
	 */
	function download_url(  ) {

		set_time_limit(300);

		if( !$this->download_package_url() )
			return;

		$dl_url = $this->download_package_url();
		$upload_dir = get_path('upgrade');

		if( file_put_contents($upload_dir.$this->package_name.".zip", fopen($dl_url, 'r')) != false ) {
			$response = array(
				'message'		=>	'Upgrade folder is cleaned, downloading updated version. Please wait...',
				'next_step'		=>	'unzip',
				'status'		=>	'processing'
			);
		} else {
			$response = array(
				'message'		=>	'Error downloading updated version. Update process halted.',
				'next_step'		=>	'unzip',
				'status'		=>	'error'
			);
		}



		echo json_encode($response); die;

	}

	/**
	 * Check if license is valid
	 * @since  :       1.0.0
	 * @return boolean [description]
	 */
	function is_license_valid() {

		$args = array(
			'edd_action'	=>	'check_license',
			'item_name'		=>	'Feedsync',
			'url'			=>	get_option('feedsync_license_url'),
			'license'		=>	get_option('feedsync_license_key')
		);


		$request 			= $this->api.'?'.http_build_query($args);

		$response			= $this->do_request($request);
		$response 			= (array) json_decode($response);

		return $response['license'] == 'valid' ? true : false;
	}

	/**
	 * Fetch feedsync info from server
	 * @since  :      1.0.0
	 * @return [type] [description]
	 */
	function get_update_info() {

		$args = array(
			'edd_action'	=>	'get_version',
			'item_name'		=>	'Feedsync',
			'url'			=>	get_option('feedsync_license_url'),
			'license'		=>	get_option('feedsync_license_key')
		);

		$request 			= $this->api.'?'.http_build_query($args);

		$response			= $this->do_request($request);
		$this->response 	= (array) json_decode($response);
	}


	function parse_info() {

		$this->slug 			= isset($this->response['slug']) ? $this->response['slug'] : '';
		$this->version 			= isset( $this->response['new_version'] ) ? $this->response['new_version'] : '';
		$this->package_name 	= $this->slug.'-'.$this->version;
		$sections 				= isset( $this->response['sections'] ) ? unserialize( $this->response['sections'] ) : '';
		$this->changelog 		= isset( $sections['changelog'] ) ? $sections['changelog'] : '';
	}

	/**
	 * Unzip the package, also deletes the zip
	 * @since  :      1.0.0
	 * @return [type] [description]
	 */
	function unzip_package() {

		$package = get_path('upgrade').$this->package_name.'.zip';
		$unzip_error = false;

		if ( class_exists('ZipArchive')  ) {
			$zip = new zip;
			if( $zip->open($package) === TRUE) {
				if( $zip->extractTo( get_path('upgrade') ) ) {

				}
				$zip->close();
				unlink($package);
			 } else {
			 	$response = array(
					'message'		=>	'Unable to unzip, please make sure upgrade folder has write permissions.',
					'status'		=>	'error'
				);

				echo json_encode($response); die;
			 }

		} else {
			$response = array(
				'message'		=>	'Zip extension is not enabled on your server, please enable it and try again.',
				'status'		=>	'error'
			);

			echo json_encode($response); die;
		}

		/** Delete the macox folder, if exists */
		$mac_dir = get_path('upgrade').'__MACOSX';
		if ( file_exists($mac_dir) ) {
            rrmdir($mac_dir);

        }

		$response = array(
			'message'		=>	'Unzipping complete, updating files...',
			'next_step'		=>	'update',
			'status'		=>	'processing'
		);

		echo json_encode($response); die;
	}

	/**
	 * Updates files
	 * @since  :      1.0.0
	 * @return [type] [description]
	 * @since 3.4.2 Create directory if not already exists
	 */
	function update_files() {

		$ds = DIRECTORY_SEPARATOR;

		$new_files = $this->get_latest_file_lists();
		$current_files = $this->get_current_file_lists();


		if( !empty($new_files) ){

			foreach($new_files as $new_file){

				$rel_path = str_replace(get_path('upgrade').'feedsync'.$ds,'',$new_file);
				$dest_path = SITE_ROOT.$rel_path;

				if($rel_path == 'config.php') {
					// skip config file
				} else {

					if( is_dir($new_file) ){

						if( !file_exists($dest_path) )
							mkdir(dirname($dest_path), 0755, true);

					} else {
						$path = pathinfo($dest_path);
					    if ( !file_exists($path['dirname']) ) {
                           mkdir( $path['dirname'], 0755, true);
                        }
						copy($new_file, $dest_path);
						@chmod($dest_path, 0644); // force file permissions to 644
					}


				}

			}

			/** delete old files */
			$this->delete_old_files();

			$response = array(
				'message'		=>	'Files updated successfully, please wait while we clean the upgrade folder.',
				'next_step'		=>	'clean_end',
				'status'		=>	'processing'
			);

		} else {
			$response = array(
				'message'		=>	'No files to update.',
				'status'		=>	'error'
			);


		}

		echo json_encode($response); die;
	}

	/**
	 * Check missing files & uploads them
	 * @return [type] [description]
	 * @since 3.4.2
	 */
	function check_missing_files() {

		$missing_processed = get_option( 'is_missing_processed' );

		if( 'yes' !== $missing_processed ) {
			update_option( 'is_missing_processed', 'init' );
			$response = array(
				'message'		=>	'Adding missing files...',
				'next_step'		=>	'update',
				'status'		=>	'processing'
			);
			echo json_encode($response); die;
		}

		
	}

	function delete_old_files() {

		require_once CORE_PATH."old-files.php";
		
		global $_old_files;

		if( !empty($_old_files) ) {
			foreach($_old_files as $old_file) {

				if (file_exists($old_file) ) {
					@unlink($old_file);
				}
			}
		}
	}

	function get_latest_file_lists() {
		return get_recursive_files_list(get_path('upgrade').'feedsync','.*');
	}

	function get_current_file_lists() {
		return get_recursive_files_list(SITE_ROOT,'.*');
	}

	/**
	 * [do_request description]
	 * @param  [type]  $url     [description]
	 * @param  array   $params  [description]
	 * @param  boolean $is_post [description]
	 * @since 3.4.2 Added support for old versions.
	 */
	function do_request($url,$params = array(), $is_post = false ) {

		$server_output = false;
		$is_request_available = get_option( 'is_request_available' );

		if( 'yes' !== $is_request_available ) {
			$server_output			= file_get_contents($url);
		} else {

			$server_output = false;

			try {

				if( $is_post )
					$response 	= feedsync_remote_post( $url, array(), $params );
				else 
					$response 	= feedsync_remote_get( $url, array('Expect'	=>	''), $params );

				$server_output 	= feedsync_remote_retrive_body( $response );
				
				if ($server_output === false) {
			        throw new Exception( 'No Response from server' );
			    }

			} catch(Exception $e) {

			    // trigger_error(sprintf(
			    //     'Request failed with error #%d: %s',
			    //     $e->getCode(), $e->getMessage()),
			    //     E_USER_ERROR);

			}
		}

		return $server_output;
	}

}