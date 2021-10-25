<?php
include('class-processor.php');

class ROCKEND_PROCESSOR extends FEEDSYNC_PROCESSOR {



    function get_sub_path(){
        return '/';
    }

    /**
     * parses dom elements to be procesessed in file
     * @return [type]
     */
    function dom_elements() {

        $this->elements = $this->get_first_node($this->xmlFile,'Properties');
        $this->contacts = $this->get_first_node($this->xmlFile,'Contacts');
        $this->item     = current($this->elements);
    }

    /** handle blank xml **/
    function handle_blank_xml(){

        if( $this->xmlFile->getElementsByTagName("Properties")->length == 0 ) {
            try {
                if( rename($this->path,$this->get_path('processed').basename($this->path) ) ) {
                    if(!$this->cron_mode) {
                        die(
                            json_encode(
                                array(
                                    'status'    =>  'fail',
                                    'message'   =>  'Empty file, skipped.',
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

    /** handle invalid xml **/
    function handle_invalid_xml(){

    }

    /**
     * get address from a listing element
     * @param  [domDocument Object]
     * @param  boolean
     * @return [mixed]
     */
    function get_address($item,$comma_seperated = true) {

        $this->address['streetnumber']      = $this->get_node_value($item,'StreetNumber');
        $this->address['street']            = $this->get_node_value($item,'StreetName');
        $this->address['suburb']            = $this->get_node_value($item,'Suburb');
        $this->address['state']             = $this->get_node_value($item,'State');
        $this->address['postcode']          = $this->get_node_value($item,'Postcode');

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

         $this->logger_log('Geocoded from latitude, longitude node : '.$this->coord);

        return $this->update_feedsync_node($item,$coord);
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

    function guess_property_type($item) {


        $listing_type = $this->get_first_node($item,'ListingType')->getAttribute('Type');
        $building_type = $this->get_first_node($item,'BuildingType')->getAttribute('Type');

        if($building_type == 'Residential') {
             if($listing_type == 'Rent'){
                $building_type = 'Rental';
             }
             if($listing_type == 'Sale'){
                $building_type = 'Property';
             }
        }
       return $building_type;
    }

    function guess_property_status($item) {

        $status = $this->get_first_node($item,'UpdateStatus')->getAttribute('Action');

        if($status == 'Active' || $status == 'New' ){
            $status = 'current';
         }

        $item->setAttribute('status',strtolower($status) );
        return strtolower($status);
    }

    function add_required_nodes_and_atts($item,$data) {

        $item->setAttribute('status',$data['status']);
        $item->setAttribute('modTime',$data['mod_date']);
        return $item;
    }



    /**
     * get initial values required to be updated / insereted for listing
     * @param  domDocument Object
     * @return domDocument Object
     */
    function get_initial_values($item){

        $db_data                    = array();
        $db_data['type']            = $this->guess_property_type($item);
        $db_data['unique_id']       = $item->getAttribute('PropertyID');
        $db_data['feedsync_unique_id']          = $this->get_node_value($item,'feedsyncUniqueID');
        $db_data['agent_id']        = $this->get_node_value($item,'ContactID');
        $mod_date                   = $this->get_node_value($item,"UpdateTime");
        $db_data['mod_date']        = date("Y-m-d H:i:s",strtotime($mod_date));
        $db_data['status']          = $this->guess_property_status($item);
        $db_data['geocode']         = '';

        if( $this->geocode_enabled() )
            $db_data['geocode']         = $this->get_node_value($item,'feedsyncGeocode');

        $db_data['street']          = $this->get_node_value($item,'StreetName');
        $db_data['suburb']          = $this->get_node_value($item,'Suburb');
        $db_data['state']           = $this->get_node_value($item,'State');
        $db_data['postcode']        = $this->get_node_value($item,'Postcode');
        $db_data['country']         = 'Australia';
        $db_data['address']         = $this->get_address($item,true);

        $item                       = $this->add_required_nodes_and_atts($item,$db_data);
        $db_data['xml']             = $this->xmlFile->saveXML( $item);

        return $db_data;

    }

    function partial_update_listing($item) {

        $db_data = array();
        $db_data['unique_id']       = $item->getAttribute('PropertyID');
        $mod_date                   = $this->get_node_value($item,"UpdateTime");
        $db_data['mod_date']        = date("Y-m-d H:i:s",strtotime($mod_date));
        $db_data['status']          = $this->guess_property_status($item);

        /** check if listing exists already **/
        $exists = $this->db->get_row( $this->db->prepare( "SELECT * FROM ".fsdb()->listing." where unique_id = '%s'", $db_data['unique_id'] ) );

        if( !empty($exists) ) {

            /** update if we have updated data **/
            if(  strtotime($exists->mod_date) < strtotime($db_data['mod_date']) ) {

                $existing_xml = new DOMDocument;
                $existing_xml->preserveWhiteSpace = FALSE;
                $existing_xml->loadXML($exists->xml);
                $existing_xml->formatOutput = TRUE;
                $existing_listing = $existing_xml->getElementsByTagName('*');
                $existing_listing->item(0)->setAttribute('modTime', $db_data['mod_date']);
                $existing_listing->item(0)->setAttribute('status', $db_data['status']);
                $db_data['xml'] = $existing_xml->saveXML($existing_listing->item(0));

                $db_data    =   array_map(array($this->db,'escape'), $db_data);

                $query = "UPDATE ".fsdb()->listing." SET
                    mod_date        = '{$db_data['mod_date']}',
                    status          = '{$db_data['status']}',
                    xml             = '{$db_data['xml']}'
                    WHERE unique_id = '{$db_data['unique_id']}'
                ";
                $this->logger_log(' --- Listing updated --- '.PHP_EOL);
                return $this->db->query($query);
            }

        }
    }

    /**
     * Add EPL nodes
     * @return [type]
     */
    function epl_nodes($item) {

        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        $image_mod_date = false;

        $imgs = $this->xpath->query('//image[@TimeModified]');
        if(!empty($imgs)) {

            foreach ($imgs as $k=>$img) {
                $image_mod_date = trim($img->getAttribute('modTime'));
                if(!empty($image_mod_date)) {
                    $image_mod_date = date("Y-m-d H:i:s",strtotime($image_mod_date));
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

        $feedsync_unique_id = $this->get_node_value($item,'PropertyID');

        if( $this->has_node($item,'ContactID') ) {

            $feedsync_unique_id = $this->get_node_value($item,'ContactID').'-'.$feedsync_unique_id;

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

        $status         = $item->getAttribute('status');

        if( 'delete' === $status ) {
            $publish_status = 'trash';
        } else {
            $publish_status = $this->get_publish_status($status);
        }
        
        // add feedsyncPostStatus if its not there already
         if( ! $this->has_node($item,'feedsyncPostStatus') ) {
            $element    = $this->add_node($node_to_add,'feedsyncPostStatus',$publish_status);
            $item->appendChild($element);

        } else {
            // if node already exists, just update the value
            $item = $this->set_node_value($item,'feedsyncPostStatus',$publish_status);
        }

        if(!empty($this->xmlFile) ) {
            $this->xmlFile->save($this->path);
        }

        return $item;
    }

    /**
     * Import listings to database
     * @return json
     */
    function import(){

         $this->init_log();

        $this->logger_log('Agent processing initiated...');
        $this->process_agents();
        $this->logger_log('Agent processing completed');

        if( empty($this->elements) ) {
            die( json_encode(array('status' =>  'success', 'message'    =>  'All files have been processed.', 'buffer'   =>  'complete')) );
        }


        $this->logger_log('==== File processing Initiated  : '.basename($this->path).' ===='.PHP_EOL);

        foreach($this->elements->childNodes as $item) {

            if( isset($item->tagName) && !is_null($item->tagName) ) {

                $this->logger_log('---- Listing Process Initiated ----'.PHP_EOL);

                $listing_type = $this->get_first_node($item,'ListingType');

                /** xml node doesnt have listing details  */
                if( is_null($listing_type) ){

                    $this->logger_log('Listing details missing in feed');

                    $status_updated = $this->get_first_node($item,'UpdateStatus');

                    /** possibly only status update ? */
                    if( !is_null($status_updated) ){
                        $this->logger_log('detected partial update (only status) ');
                        $this->partial_update_listing($item);
                    }

                } else {

                    $this->logger_log('Geocode processing initiated...');
                    /** process geocode **/
                    $this->geocode($item);
                    $this->logger_log('Geocode processing completed');

                    /** process image **/
                    $this->logger_log('Image processing initiated...');
                    $this->process_image();
                    $this->logger_log('Image processing completed');

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

                        /** insert firstDate node **/
                        if ( !$this->has_node($item,'firstDate') ) {

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
                }


            }

        }

        $this->logger_log('---- File processing complete ----');

        try {
            if( $this->move_processed_file( $this->path ) ) {

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

                echo json_encode(
                    array(
                        'status'    =>  'error',
                        'message'   =>  '<strong>Unable to rename file</strong>'.basename($this->path).'  processed .'.$this->total_files.' files remaining. <br> <strong>Currently processing your files, do not navigate away from this page. </strong>',
                        'geocoded'  =>  $this->geocoded_addreses_list,
                        'buffer'    =>  'processing'
                    )
                );

                $this->reset();
                $this->init();
                $this->import();
            }

        } catch(Exception $e) {

            if(!$cron_mode) {
                echo $e->getMessage(); die;
            }
        }
    }

    /**
     * Processes image
     * @return [domDocument Object]
     */
    function process_image($item = null) {

        $imgs = $this->xpath->query('//Image[@FileName]');

        if(!empty($imgs)) {
            foreach ($imgs as $k=>$img) {
                $img_name = trim($img->getAttribute('FileName'));
                if(!empty($img_name)) {
                    $img_name = basename($img_name);
                    $img_path = $this->get_url('images').$img_name;
                    $imgs->item($k)->setAttribute('url', $img_path);

                }

            }
        }

    }

    /**
     * Import listings to database
     * @return json
     */
    function process_agents($agent = null){

        if( empty($this->contacts) ) {
            return;
        }

        foreach($this->contacts->childNodes as $listing_agent) {

           /** init values **/
            $data_agent                 = array();
            $data_agent['office_id']    = '';
            $data_agent['name']         = $this->get_node_value($listing_agent,'ContactName');
            $data_agent['telephone']    = $this->get_node_value($listing_agent,'BHPhone');
            $data_agent['email']        = $this->get_node_value($listing_agent,'Email');
            $data_agent['agent_id']     = $listing_agent->getAttribute('ContactID');
            $data_agent['username']     = '';

            $agent_full_name        = explode(' ',$data_agent['name']);
            if( !$this->has_node($listing_agent,'agentFirstName') ) {
                $agent_first        = $agent_full_name[0];
                $create_fname       = $this->xmlFile->createElement('agentFirstName',  htmlentities($agent_first) );
                $listing_agent->appendChild($create_fname);
            }
            if( !$this->has_node($listing_agent,'agentLastName') ) {
                $agent_last         = isset($agent_full_name[1]) ? $agent_full_name[1] : '';
                $create_lname       = $this->xmlFile->createElement('agentLastName', htmlentities($agent_last) );
                $listing_agent->appendChild($create_lname);
            }
            if( !$this->has_node($listing_agent,'agentUserName') ) {
                $create_uname       = $this->xmlFile->createElement('agentUserName',sanitize_user_name($data_agent['name']));
                $listing_agent->appendChild($create_uname);
                 $data_agent['username']         = sanitize_user_name($data_agent['name']);
            } else {
                $data_agent['username']         = $this->get_node_value($listing_agent,'agentUserName');
            }

            $data_agent['xml']          = $this->xmlFile->saveXML( $listing_agent);
            $data_agent                 = array_map(array($this->db,'escape'), $data_agent);

            /** check if listing agent exists already **/
            $agent_exists = $this->db->get_row("SELECT * FROM ".fsdb()->agent." where name = '{$data_agent['name']}' ");

            if( empty($agent_exists) ) {

                /** insert new data **/
                $query = "INSERT INTO
                    ".fsdb()->agent." (office_id,name,telephone,email,xml,listing_agent_id,username)
                    VALUES (
                        '{$data_agent['office_id']}',
                        '{$data_agent['name']}',
                        '{$data_agent['telephone']}',
                        '{$data_agent['email']}',
                        '{$data_agent['xml']}',
                        '{$data_agent['agent_id']}',
                        '{$data_agent['username']}'
                    )";
                $this->logger_log('Imported Agent : Name : '.$data_agent['name'].' ID : '.$data_agent['office_id']);
                //print_exit($query);
                $this->db->query($query);
                //print_exit($this->db);
            } else {

                /** update data **/
                $query = "UPDATE ".fsdb()->agent." SET
                    office_id           = '{$data_agent['office_id']}',
                    telephone           = '{$data_agent['telephone']}',
                    email               =  '{$data_agent['email']}',
                    xml                 =  '{$data_agent['xml']}',
                    listing_agent_id    = '{$data_agent['agent_id']}',
                    username            = '{$data_agent['username']}'
                    WHERE name                =  '{$data_agent['name']}'
                ";

                $this->logger_log('Updated Agent : Name : '.$data_agent['name'].' ID : '.$data_agent['office_id']);
                $this->db->query($query);
            }

        }


    }
}