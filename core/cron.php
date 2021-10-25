<?php

define( 'DOING_CRON', true );

require_once('../config.php');
require_once('functions.php');
require_once(CORE_PATH.'classes/class-chunks.php');
require_once(CORE_PATH.'classes/class-zip.php');
require_once(CORE_PATH.'classes/class-feedsync-setup-preprocessor.php');


class FEEDSYNC_CRON {

	function __construct() {
		
		$this->set_time_limits();
		$this->setup_processor();
		$this->set_action();
		$this->execute_actions();
	}

	function set_time_limits() {
		set_time_limit(0);
	}

	function setup_processor() {
		new FEEDSYNC_SETUP_PROCESSOR();
	}

	private function set_action() {
		$this->action = isset($_GET['action']) ? (string) $_GET['action'] : 'default';
	}

	/**
	 * Fetch latest listing numbers and update them in database
	 * @return null
	 */
	private function execute_action_trigger() {
		
		import_listings(true,array('action'	=>	$this->action) );
	}

	/**
	 * Save details per listing
	 * @return null
	 */
	private function execute_action_process() {

		import_listings(true,array('action'	=>	$this->action) );
	}

	/**
	 * Default Task
	 * @return mixed
	 */
	private function execute_action_default() {
		
		import_listings(true);
	}

	private function execute_actions() {
		
		$method = 'execute_action_'.$this->action;
		if( method_exists($this,$method) ){
			$this->$method();
		}
	}
}

new FEEDSYNC_CRON();