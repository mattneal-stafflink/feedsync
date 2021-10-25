<?php
class feedsync_upgrade {

	protected $update_path;

	function __construct() {

		$this->update_path = CORE_PATH;
	}

	function versions() {

		/** Versions in increasing order. */
		$versions = array(

			'2.1',
			'3.0.5',
			'3.2',
			'3.2.1',
			'3.2.2',
			'3.2.3',
			'3.3',
			'3.4',
			'3.4.1',
			'3.4.2',
			'3.4.3',
			'3.4.4',
			'3.4.5',
			'3.5',
			'3.5.1',
			'3.5.2',
			'3.5.3'

		);

		return $versions;
	}

	function dispatch() {

		global $current_version;

		if( !empty( $this->versions() ) ) {

			foreach( $this->versions() as $version ){

				$this->db_upgrade_to($version);

			}

		}

		update_option( 'feedsync_current_version', $current_version );
	}

	function get_version_strong($version) {
		return str_replace('.', '_', $version);
	}

	function is_db_version_updated($version) {
		$version_string = $this->get_version_strong($version);
		return get_option('db_upgrade_'.$version_string);
	}

	function db_upgrade_to($version) {

		$version_string = $this->get_version_strong($version);
		$db_updated 	= $this->is_db_version_updated($version);

		if( !$this->is_db_version_updated($version) ) {

			if( file_exists($this->update_path.'update-'.$version.'.php') ) {

				include( $this->update_path.'update-'.$version.'.php' );
			}


			update_option('db_upgrade_'.$version_string,true);
			/** last upgrade version as db version */
			update_option('db_version',$version);

		}
	}

}
