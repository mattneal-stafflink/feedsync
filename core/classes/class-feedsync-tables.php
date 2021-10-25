<?php
/**
 * FeedSync Tables Class.
 * 
 * This class is used for installation & upgradation of feedsync db tables.
 *
 *
 * @package FeedSync
 * @subpackage Tables
 * @since 3.4.5
 */

class FEEDSYNC_TABLES {

	public $charset_collate;

	function __construct() {


		$this->charset_collate = fsdb()->get_charset_collate();
	}

	/**
	 * Checks & inserts feedsync tables in database during installation & page load
	 */
	public function init_tables() {

		foreach(fsdb()->tables as $required_table) {

	        $required_table = fsdb()->{$required_table};
	        $exists = fsdb()->get_results('show tables like "'.$required_table.'" ');

	        if( is_null($exists) || empty($exists) )  {
	            $this->create_tables();
	            break;
	        }
	    }
	}

	/**
	 * Create tables required for functioning of feedsync
	 * 
	 */
	public function create_tables() {

		$this->create_listing_table();
		$this->create_listing_meta_table();
		$this->create_agent_table();
		$this->create_agent_meta_table();
		$this->create_options_table();
		$this->create_temp_table();
		$this->create_logs_table();

	}

	public function get_chartset() {

		return $this->charset_collate;
	}

	/**
	 * Creates a listing table.
	 */
	public function create_listing_table() {

		$sql = 'CREATE TABLE IF NOT EXISTS `'.fsdb()->listing.'` (
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`unique_id` varchar(120) NOT NULL,
		`feedsync_unique_id` varchar(120) NOT NULL,
		`agent_id` varchar(128) NOT NULL,
		`mod_date` varchar(28) NOT NULL,
		`type` varchar(28) NOT NULL,
		`status` varchar(28) NOT NULL,
		`xml` longtext NOT NULL,
		`firstdate` varchar(28) NOT NULL,
		`geocode` varchar(50) NOT NULL,
		`street` varchar(256) NOT NULL,
		`suburb` varchar(256) NOT NULL,
		`state` varchar(256) NOT NULL,
		`postcode` varchar(256) NOT NULL,
		`country` varchar(256) NOT NULL,
		`address` varchar(512) NOT NULL,
		PRIMARY KEY (`id`)
		) '.$this->get_chartset().'; ';

    	fsdb()->query($sql);
	}

	/**
	 * Creates a listing meta table.
	 */
	public function create_listing_meta_table() {

		$sql = '
        CREATE TABLE IF NOT EXISTS `'.fsdb()->listing_meta.'` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `listing_id` bigint(20) NOT NULL,
            `meta_key` varchar(191) NOT NULL,
            `meta_value` longtext NOT NULL,
          PRIMARY KEY (`id`)
        ) '.$this->get_chartset().';';

	    fsdb()->query($sql);
	}

	/**
	 * Creates an agent table.
	 */
	public function create_agent_table() {
		
		$sql = '
            CREATE TABLE IF NOT EXISTS `'.fsdb()->agent.'` (
               `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `office_id` varchar(128) NOT NULL,
              `name` varchar(128) NOT NULL,
              `telephone` varchar(128) NOT NULL,
              `email` varchar(128) NOT NULL,
               `xml` text NOT NULL,
               `listing_agent_id` varchar(256) NOT NULL,
               `username` varchar(256) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `name` (`name`)
            ) '.$this->get_chartset().';

        ';


    	fsdb()->query($sql);
	}

	/**
	 * Creates an agent meta table.
	 */
	public function create_agent_meta_table() {
		$sql = '
	            CREATE TABLE IF NOT EXISTS `'.fsdb()->agent_meta.'` (
	                `id` bigint(20) NOT NULL AUTO_INCREMENT,
	                `agent_id` bigint(20) NOT NULL,
	                `meta_key` varchar(191) NOT NULL,
	                `meta_value` longtext NOT NULL,
	              PRIMARY KEY (`id`)
	            ) '.$this->get_chartset().';

	    ';

	    fsdb()->query($sql);
	}

	/**
	 * Creates an options table.
	 */
	public function create_options_table() {
		$sql = '
            CREATE TABLE IF NOT EXISTS `'.fsdb()->options.'` (
                `option_id` bigint(20) NOT NULL AUTO_INCREMENT,
                `option_name` varchar(191) NOT NULL,
                `option_value` longtext NOT NULL,
              PRIMARY KEY (`option_id`)
            ) '.$this->get_chartset().';

	    ';

	    fsdb()->query($sql);
	}

	/**
	 * Creates a temporary table.
	 */
	public function create_temp_table() {
		$sql = '
	            CREATE TABLE IF NOT EXISTS `'.fsdb()->temp.'` (
	                `id` bigint(20) NOT NULL AUTO_INCREMENT,
	                `unique_id` varchar(191) NOT NULL,
	                `mod_date` varchar(28) NOT NULL,
	                `value` longtext NOT NULL,
	              PRIMARY KEY (`id`),
	              UNIQUE KEY `unique_id` (`unique_id`)
	            ) '.$this->get_chartset().';

	    ';

	    fsdb()->query($sql);
	}

	/**
	 * Creates a logs table.
	 */
	public function create_logs_table() {
		$sql = '
	            CREATE TABLE IF NOT EXISTS `'.fsdb()->logs.'` (
	              `id` bigint(20) NOT NULL AUTO_INCREMENT,
	              `file_name` varchar(256) NOT NULL,
	              `action` varchar(256) NOT NULL,
	              `status` varchar(256) NOT NULL,
	              `summary` longtext NOT NULL,
	              `log_file` varchar(256) NOT NULL,
	              PRIMARY KEY (`id`)
	            ) '.$this->get_chartset().';
	    ';
	    fsdb()->query($sql);
	}

	/**
	 * Upgrades table on upgradation from lower to higher version
	 * @return [type] [description]
	 */
	public function upgrade_listing_table() {

		$sql = "SELECT *
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = '".fsdb()->listing."'
                AND table_schema = '".DB_NAME."'
                AND column_name = 'street'";


    	$col_exists = fsdb()->get_results($sql);

    	if( !empty($col_exists) )
    		return;

	    /** add columns in case of upgrade to this version **/
	    $sql = "
            ALTER TABLE `".fsdb()->listing."`
                ADD `agent_id` varchar(256) NOT NULL,
                ADD `street` varchar(256) NOT NULL,
                ADD `suburb` varchar(256) NOT NULL,
                ADD `state` varchar(256) NOT NULL,
                ADD `postcode` varchar(256) NOT NULL,
                ADD `country` varchar(256) NOT NULL;
        ";
	    fsdb()->query($sql);

	}

	/**
	 * Upgrade agents table for older versions.
	 */
	public function upgrade_agent_table() {

		/** Check & ALter Table : fsdb()->agent */

	    $sql = "SELECT *
	            FROM INFORMATION_SCHEMA.COLUMNS
	            WHERE table_name = '".fsdb()->agent."'
	            AND table_schema = '".DB_NAME."'
	            AND column_name = 'listing_agent_id'";

	    $col_exists = fsdb()->get_results($sql);

	    if( empty($col_exists) ) {

	        /** add missing columns **/
	        $sql = "
	                ALTER TABLE `".fsdb()->agent."`
	                    ADD `listing_agent_id` varchar(256) NOT NULL,
	                    ADD `username` varchar(256) NOT NULL;
	            ";

	        fsdb()->query($sql);

	    }
	}
}