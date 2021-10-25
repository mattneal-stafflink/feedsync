<?php

/**
 * Upgrades table, add feedsync_unique_id column
 * 
 */
function upgrade_table_3_2() {

    

    /** alter table feedsync */

    $sql = "SELECT *
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE table_name = '".fsdb()->listing."'
            AND table_schema = '".DB_NAME."'
            AND column_name = 'feedsync_unique_id'";

    $col_exists = fsdb()->get_results($sql);

    if( empty($col_exists) ) {
        
        /** add columns in case of upgrade to this version **/
	    $sql = "
	            ALTER TABLE `".fsdb()->listing."`
	                ADD `feedsync_unique_id` varchar(256) NOT NULL
	        ";
	    fsdb()->query($sql);

        /** drop unique ID **/
        $sql = "
                ALTER TABLE `".fsdb()->listing."`
                    DROP INDEX unique_id
            ";
        fsdb()->query($sql);
    }

    /** alter table ".fsdb()->agent." */

    $sql = "SELECT *
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE table_name = '".fsdb()->agent."'
            AND table_schema = '".DB_NAME."'
            AND column_name = 'listing_agent_id'";

    $col_exists = fsdb()->get_results($sql);

    if( empty($col_exists) ) {
        
        /** add columns in case of upgrade to this version **/
        $sql = "
                ALTER TABLE `".fsdb()->agent."`
                    ADD `listing_agent_id` varchar(256) NOT NULL,
                    ADD `username` varchar(256) NOT NULL;
            ";
        fsdb()->query($sql);

    }

}

/**
 * DB upgrade for 3.0.5 feedsyncImageModtime node 
 * 
 */
function upgrade_version_3_2() {

    

    $feedtype = get_option('feedtype');
    $rex = new REAXML_PROCESSOR();
    
    switch($feedtype) {

        case 'reaxml' :
        case 'reaxml_fetch' :
        case 'blm' :
        case 'expert_agent' :
        case 'rockend' :
        case 'jupix' :
            $rex->upgrade_for_version_3_2();
            
        break;

         case 'eac' :
            $eac_api = new EAC_API(false);
            $eac_api->upgrade_for_version_3_2();

        break;

    }

    $rex->agent_upgrade_for_version_3_2();
}
upgrade_table_3_2();
upgrade_version_3_2();