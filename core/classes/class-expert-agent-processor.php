<?php
include('class-processor.php');

class Expert_Agent_PROCESSOR extends FEEDSYNC_PROCESSOR {



    function get_sub_path(){
        return '/';
    }

    /**
     * parses dom elements to be procesessed in file
     * @return [type]
     */
    function dom_elements() {
        $this->elements = $this->get_first_node($this->xmlFile,'properties');
        $this->item     = current($this->elements);
    }

    /** handle blank xml **/
    function handle_blank_xml(){

        if($this->xmlFile->getElementsByTagName("properties")->length == 0) {
            try {
                if( rename($this->path,$this->get_path('processed').basename($this->path) ) ) {
                    if(!$this->cron_mode) {
                        die(
                            json_encode(
                                array(
                                    'status'    =>  'fail',
                                    'message'   =>  'empty file, skipped.',
                                    'geocoded'  =>  '',
                                    'buffer'    =>  'processing'
                                )
                            )
                        );
                    }
                }
            } catch(Exception $e) {
                if(!$cron_mode) {
                    echo $e->getMessage(); die;
                }
            }
        }


    }

    /**
     * get address from a listing element
     * @param  [domDocument Object]
     * @param  boolean
     * @return [mixed]
     */
    function get_address($item,$comma_seperated = true) {

        $this->address['streetnumber']      = $this->get_node_value($item,'house_number');
        $this->address['street']            = $this->get_node_value($item,'street');
        $this->address['street_1']          = $this->get_node_value($item,'district');
        $this->address['suburb']            = $this->get_node_value($item,'town');
        $this->address['state']             = $this->get_node_value($item,'county');
        $this->address['postcode']          = $this->get_node_value($item,'postcode');
        $this->address['country']           = $this->get_node_value($item,'country');
        return $comma_seperated == true ? implode(", ", $this->address) : implode(" ", $this->address);

    }

    /**
     * Geocode the listing item :)
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode($item,$process_missing = false){

        $this->geocoded_addreses_list = "\n";

        /** add feedsyncGeocode node if not already there or if force geocode mode is on **/
        if( !$this->has_node($item,'feedsyncGeocode') || $this->force_geocode() ) {

            // if item has geocode node, extract value from it and save it to feedsyncGeocode node
            if( $this->has_node($item,'latitude') && $this->get_node_value($item,'latitude') > 0 ) {


                $item = $this->geocode_from_geocode_node($item);

            } else {

                // if item doesnt have geocode node, geocode it
                if( $this->geocode_enabled() || $this->force_geocode()  || $process_missing )
                    $item = $this->geocode_from_google($item);
            }
        } else {
           $this->coord = $this->get_node_value($item,'feedsyncGeocode');
        }

        return $item; // return processed item
    }

    /**
     * attempts to fetch geocode from geocode node, if present
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode_from_geocode_node($item){

         $lat                = $this->get_node_value($item,'latitude');
         $long               = $this->get_node_value($item,'longitude');

         // make coordinates class wide available
         $this->coord        = $lat.','.$long;
         $this->logger_log('Geocoded from Latitude, Longitude node : '.$this->coord);
        return $this->update_feedsync_node($item,$this->coord);
    }

    /**
     * Geocode address from google geocode API
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode_from_google($item){

        $addr_readable  = $this->get_address($item);
        $addr           = urlencode(strtolower($addr_readable));
        $this->coord    = 'NULL';

        /** try to get lat & long from google **/
        if( trim($addr) != '') {

            $query_address  = trim($addr);

            $googleapiurl = "https://maps.google.com/maps/api/geocode/json?address=$query_address&sensor=false";
            if( get_option('feedsync_google_api_key') != '' ) {
                $googleapiurl = $googleapiurl.'&key='.get_option('feedsync_google_api_key');
            }

            $geo_response   = feedsync_remote_get( $googleapiurl );
            $geocode        = feedsync_remote_retrive_body( $geo_response );

            $this->geocoded_addreses_list .= "\n $query_address";
            $output         = json_decode($geocode);

            /** if address is validated & google returned response **/
            if( !empty($output->results) && $output->status == 'OK' ) {

                $lat            = $output->results[0]->geometry->location->lat;
                $long           = $output->results[0]->geometry->location->lng;
                $this->coord    = $lat.','.$long;
                $this->logger_log('Google Geocoded Result : '.$this->coord);
                return $this->update_feedsync_node($item,$this->coord);
            } else {

                return $item;
            }
        }
    }

    function add_required_nodes_and_atts($item,$data) {

        $item->setAttribute('status',$data['status']);
        $item->setAttribute('modTime',$data['mod_date']);
        $this->logger_log('Added status and modTime');
        return $item;
    }

    function guess_property_type($item) {


        $department = $this->get_node_value($item,'department');

        $map = array(
            'Residential Lettings'  =>  'rental',
            'Sales'                 =>  'property'
        );
        $type = isset( $map[$department] ) ? $map[$department] : 'property';

        $this->set_node_value($item,'department',$type);

        return $type;
    }

    /**
     * get initial values required to be updated / insereted for listing
     * @param  domDocument Object
     * @return domDocument Object
     *
     * @since 3.4.0 Fixed firstdate
     */
    function get_initial_values($item){

        $db_data                       = array();
        $db_data['type']               = $this->guess_property_type($item);
        $db_data['unique_id']          = $this->get_node_value($item,'property_reference');
        $db_data['feedsync_unique_id'] = $this->get_node_value($item,'feedsyncUniqueID');
        $db_data['agent_id']           = NULL;
        $mod_date                      = date("Y-m-d-H:i:s");
        $db_data['mod_date']           = feedsync_format_date( $mod_date );
        $db_data['firstdate']          = $this->convert_time_to_timezone( $db_data['mod_date'], get_option('feedsync_timezone') );
        $db_data['status']             = $this->get_node_value($item,'priority');
        $db_data['geocode']            = $this->get_node_value($item,'feedsyncGeocode');
        $db_data['street']             = $this->get_node_value($item,'street');
        $db_data['suburb']             = $this->get_node_value($item,'town');
        $db_data['state']              = $this->get_node_value($item,'county');
        $db_data['postcode']           = $this->get_node_value($item,'postcode');
        $db_data['country']            = $this->get_node_value($item,'country');
        $db_data['address']            = $this->get_address($item,true);
        $item                          = $this->add_required_nodes_and_atts($item,$db_data);
        $db_data['xml']                = $this->xmlFile->saveXML( $item);
        return $db_data;

    }

    /**
     * Copy files from server to input folder
     * @since  :      3.0.0
     * @return [type] [description]
     */
    function copy_files() {

        // define some variables
        $local_path  = get_path('input');
        $local_file  = '';

        // set up basic connection
        $conn_id = ftp_connect(get_option('feedsync_remote_host'));

        // login with username and password
        $login_result = ftp_login($conn_id, get_option('feedsync_remote_user'), get_option('feedsync_remote_pass'));

        // turn passive mode on
        ftp_pasv($conn_id, true);

        // get contents of the current directory
        $lists = ftp_nlist($conn_id, ".");

        if(!empty($lists)) {
            foreach($lists as $list) {
                $local_file = basename($list,'.xml').'-'.date("Y-m-d-H-i-s-T").'.xml';
                //try to download $server_file and save to $local_file
                if (ftp_get($conn_id, $local_path.$local_file, $list, FTP_BINARY)) {

                } else {
                    echo "There was a problem\n";
                }
            }
        }
        // close the connection
        ftp_close($conn_id);
    }

    /**
     * Add EPL nodes
     *
     * Add Image mod date
     * @return [type]
     */
    function epl_nodes($item) {

        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        $image_mod_date = false;

        $imgs = $this->xpath->query('//picture[@lastchanged]');
        if(!empty($imgs)) {

            foreach ($imgs as $k=>$img) {
                $image_mod_date = trim($img->getAttribute('lastchanged'));
                if(!empty($image_mod_date)) {
                    $image_mod_date = feedsync_format_date( $image_mod_date );
                }
                break;
            }

            if($image_mod_date) {
                if( ! $this->has_node($item,'feedsyncImageModtime') ) {
                    // if node not already exists, add it

                    $element = $this->add_node($node_to_add,'feedsyncImageModtime',$image_mod_date);
                    $item->appendChild($element);
                } else {
                    // if node already exists, just update the value
                    $item = $this->set_node_value($item,'feedsyncImageModtime',$image_mod_date);
                }
                $this->logger_log('feedsyncImageModtime processed : '.$image_mod_date);
            }

        }

        /** Feedsync Unique ID ( Unique ID + Agent ID ) */

        $feedsync_unique_id = $this->get_node_value($item,'property_reference');

        if( $this->has_node($item,'agentID') ) {

            $feedsync_unique_id = $this->get_node_value($item,'agentID').'-'.$feedsync_unique_id;

        }

         // if node not already exists, add it
        if( ! $this->has_node($item,'feedsyncUniqueID') ) {

            // if node not already exists, add it

            $element = $this->add_node($node_to_add,'feedsyncUniqueID',$feedsync_unique_id);
            $item->appendChild($element);

        } else {
            // if node already exists, just update the value
            $item = $this->set_node_value($item,'feedsyncUniqueID',$feedsync_unique_id);
        }

        $this->logger_log('feedsyncUniqueID processed : '.$feedsync_unique_id);

        if(!empty($this->xmlFile) ) {
            $this->xmlFile->save($this->path);
        }

        return $item;
    }

    function change_status($status,$ids) {

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where 1=1 AND unique_id NOT IN (".implode(',',$ids).")  ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {

                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->xpath = new DOMXPath($this->xmlFile);

                $item = $this->xmlFile->documentElement;

                if ( !$this->has_node($item,'feedsync_status')) {

                    $fss   = $this->add_node($this->xmlFile,'feedsync_status', $status);
                    $item->appendChild($fss);

                } else {
                     $this->set_node_value($item,'feedsync_status',$status);
                }

                $item->setAttribute('status',$status);

                $newxml         = $this->xmlFile->saveXML($item);

                $db_data   = array(
                    'xml'                   =>  $newxml,
                    'status'                =>  $status
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);

                $query = "UPDATE ".fsdb()->listing." SET
                                xml                             = '{$db_data['xml']}',
                                status                          = '{$db_data['status']}'
                                WHERE id                        = '{$listing->id}'
                            ";

               $this->db->query($query);
               $this->logger_log('Undetermined listing ID : '.$listing->id.' marked as '.$db_data['status']);

            }

            /*die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'preprocessed listings, please wait while feed is processing...',
                        'buffer'    =>  'processing'
                    )
                )
            );*/

        }  else {

            /*die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'preprocessed listings, please wait while feed is processing...',
                        'buffer'    =>  'processing'
                    )
                )
            );*/
        }
    }

        /**
     * Handle undetermined listings
     * @return [type]
     */
    function expert_agent_post_import($processed_ids) {

        if( empty($processed_ids) )
            return;

        $action = get_option('feedsync_expert_agent_unknown_listings_action');

        switch( $action ) {

            case 'leave':
                // no action
            break;

            case 'withdrawn':
                $this->change_status('withdrawn',$processed_ids);
            break;

            case 'offmarket':
                $this->change_status('offmarket',$processed_ids);
            break;
        }
    }

    /**
     * Import listings to database
     * @return json
     */
    function import(){

        if( empty($this->elements) ) {

            if( (!isset($_COOKIE['expert_agent_feed_fetched']) || $_COOKIE['expert_agent_feed_fetched'] != 1) ) {
                $this->copy_files();
                // set cookie for 30 mins
                setcookie("expert_agent_feed_fetched", 1, time()+60*30);
                die( json_encode(array('status' =>  'success', 'message'    =>  'Feed Fetched, Processing will follow...', 'buffer'   =>  'processing')) );
            }
            die( json_encode(array('status' =>  'success', 'message'    =>  'All files have been processed.', 'buffer'   =>  'complete')) );
        }

        $processed_ids = array();

        $this->init_log();

        $this->logger_log('==== File processing Initiated  : '.basename($this->path).' ===='.PHP_EOL);

        foreach($this->elements->childNodes as $item) {

            if( isset($item->tagName) && !is_null($item->tagName) ) {

                /** process geocode **/
                $this->logger_log('Geocode processing initiated...');
                $this->geocode($item);
                $this->logger_log('Geocode processing completed');

                /** add nodes */
                $this->logger_log('Node processing initiated...');
                $this->epl_nodes($item);
                $this->logger_log('Node processing completed');

                $db_data = $this->get_initial_values($item);
                $this->logger_log('Fetched initial values');

                /** check if listing exists already **/
                $exists = $this->db->get_row( $this->db->prepare( "SELECT * FROM ".fsdb()->listing." where feedsync_unique_id = '%s'", $db_data['feedsync_unique_id'] ) );

                if( !empty($exists) ) {
                    $this->logger_log('Duplicate listing detected with ID : '.$exists->id);
                    /** update if we have updated data **/
                    if(  strtotime($exists->mod_date) < strtotime($db_data['mod_date']) ) {

                        $this->logger_log('Updated content detected. New Mode Time : '.$db_data['mod_date'].'. Old Mode Time : '.$exists->mod_date);

                        /** add firstDate node to xml if its already not there **/

                        if ( !$this->has_node($item,'firstDate')) {

                            $firstDateValue             = $this->add_node($this->xmlFile,'firstDate',$exists->firstdate);
                            $item->appendChild($firstDateValue);
                            $db_data['xml']             = $this->xmlFile->saveXML( $item);
                            $this->logger_log('First Date not found, added firstDate:'.$exists->firstdate);
                        }

                        if ( get_post_meta($exists->id,'fav_listing',true) == 'yes' ) {

                            if ( !$this->has_node($item,'feedsyncFeaturedListing')) {

                                $fav             = $this->add_node($this->xmlFile,'feedsyncFeaturedListing','yes');
                                $item->appendChild($fav);
                                $db_data['xml']             = $this->xmlFile->saveXML( $item);
                                $this->logger_log('Fav listing detected, Set as fav');
                            }
                        }

                        $db_data    =   array_map(array($this->db,'escape'), $db_data);

                        $this->update_listing($db_data);
                        $this->log_report['listings_updated']++;
                        $this->logger_log('---- Updated listing ----'.PHP_EOL);
                    } else {
                        $this->log_report['listings_skipped']++;
                        $this->logger_log('---- No Updated content, Skipping ---- '.PHP_EOL);
                    }

                } else {

                    $this->logger_log('New listing detected');

                    /** insert firstDate node **/
                    if ( !$this->has_node($item,'firstDate')) {

                        $firstDate      = $this->xmlFile->createElement('firstDate', $db_data['mod_date']);
                        $item->appendChild($firstDate);
                        $this->logger_log('First Date added firstDate:'.$db_data['mod_date']);
                    }

                    $db_data['xml'] = $this->xmlFile->saveXML( $item);
                    $db_data        =   array_map(array($this->db,'escape'), $db_data);

                    $this->insert_listing($db_data);
                    $this->log_report['listings_created']++;
                    $this->logger_log('---- Inserted listing ----'.PHP_EOL);
                }

                $processed_ids[] = $db_data['feedsync_unique_id'];
            }

        }

        $this->logger_log('---- File processing complete ----');
        $this->expert_agent_post_import($processed_ids);
        try {
            if( rename($this->path,$this->get_path('processed').basename($this->path) ) ) {

                $this->logger_log('---- File successfully moved to processed folder ----');
                $this->complete_log();

                if(!$this->cron_mode) {
                    die(
                        json_encode(
                            array(
                                'status'    =>  'success',
                                'message'   =>  basename($this->path).'  processed .'.$this->total_files.' files remaining. <br> <strong>Currently processing your files, do not navigate away from this page. </strong>',
                                'geocoded'  =>  $this->geocoded_addreses_list,
                                'buffer'    =>  'processing'
                            )
                        )
                    );
                }
            } else {
                die(
                    json_encode(
                        array(
                            'status'    =>  'error',
                            'message'   =>  '<strong>Unable to rename file</strong>'.basename($this->path).'  processed .'.$this->total_files.' files remaining. <br> <strong>Currently processing your files, do not navigate away from this page. </strong>',
                            'geocoded'  =>  $this->geocoded_addreses_list,
                            'buffer'    =>  'processing'
                        )
                    )
                );
            }
        } catch(Exception $e) {
            if(!$cron_mode) {
                echo $e->getMessage(); die;
            }
        }
    }
}