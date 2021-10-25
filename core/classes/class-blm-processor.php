<?php
include('class-processor.php');

class BLM_PROCESSOR extends FEEDSYNC_PROCESSOR {



    function get_sub_path(){
        return '';
    }



    /**
     * get address from a listing element
     * @param  [domDocument Object]
     * @param  boolean
     * @return [mixed]
     */
    function get_address($item,$comma_seperated = true) {

        if( $this->has_node($item,'ADDRESS_1') )
            $this->address['streetnumber']      = $this->get_node_value($item,'ADDRESS_1');

        if( $this->has_node($item,'ADDRESS_2') )
            $this->address['street']            = $this->get_node_value($item,'ADDRESS_2');

        if( $this->has_node($item,'ADDRESS_3') )
            $this->address['street_1']          = $this->get_node_value($item,'ADDRESS_3');

        if( $this->has_node($item,'ADDRESS_4') )
            $this->address['suburb']            = $this->get_node_value($item,'ADDRESS_4');

        if( $this->has_node($item,'TOWN') )
            $this->address['state']             = $this->get_node_value($item,'TOWN');

        if( $this->has_node($item,'POSTCODE1') )
            $this->address['postcode']          = $this->get_node_value($item,'POSTCODE1').'-'.$this->get_node_value($item,'POSTCODE2');

        if( $this->has_node($item,'country') )
            $this->address['country']           = 'UK';
        $address = array_filter($this->address);
        return $comma_seperated == true ? implode(", ",  $address) : implode(" ",  $address);

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
                /** cant geocode set to '' */
                return $this->update_feedsync_node($item,'');
            }
        }

    }

    /**
     * Add EPL nodes
     *
     * Add Image mod date
     * @return [type]
     */
    function epl_nodes($item) {

        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        $image_mod_date                   = $this->get_node_value($item,'UPDATE_DATE');
        if($image_mod_date == '') {
            $image_mod_date = date("Y-m-d H:i:s",time());
        }
        $image_mod_date        = feedsync_format_date( $image_mod_date );


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

        /** Feedsync Unique ID ( Unique ID + Agent ID ) */

        $feedsync_unique_id = $this->get_node_value($item,'AGENT_REF');

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


    /**
     * get initial values required to be updated / insereted for listing
     * @param  domDocument Object
     * @return domDocument Object
     *
     * @since 3.4.0 Fixed firstdate
     */
    function get_initial_values($item){

        $db_data                       = array();
        $db_data['type']               = $this->get_node_value($item,'TRANS_TYPE_ID');
        $db_data['unique_id']          = $this->get_node_value($item,'AGENT_REF');
        $db_data['feedsync_unique_id'] = $this->get_node_value($item,'feedsyncUniqueID');
        $db_data['agent_id']           = NULL;
        $mod_date                      = $this->get_node_value($item,'UPDATE_DATE');
        if($mod_date == '') {
            $mod_date = date("Y-m-d H:i:s",time());
        }
        $db_data['mod_date']        = feedsync_format_date( $mod_date );
        $db_data['firstdate']       = $this->convert_time_to_timezone( $db_data['mod_date'], get_option('feedsync_timezone') );
        $db_data['status']          = $this->get_node_value($item,'STATUS_ID');
        $db_data['geocode']         = $this->get_node_value($item,'feedsyncGeocode');
        $db_data['street']          = $this->get_node_value($item,'ADDRESS_2');
        $db_data['suburb']          = $this->get_node_value($item,'ADDRESS_4');
        $db_data['state']           = $this->get_node_value($item,'TOWN');
        $db_data['postcode']        = $this->get_node_value($item,'POSTCODE1').'-'.$this->get_node_value($item,'POSTCODE2');
        $db_data['country']         = 'UK';
        $db_data['address']         = $this->get_address($item,true);
        $item                       = $this->add_required_nodes_and_atts($item,$db_data);
        $db_data['xml']             = $this->xmlFile->saveXML( $item);
        return $db_data;

    }

    function add_required_nodes_and_atts($item,$data) {

        $item->setAttribute('status',$data['status']);
        $item->setAttribute('modTime',$data['mod_date']);
        return $item;
    }

    /**
     * Processes image
     * @return [domDocument Object]
     */
    function process_docs() {

        $docs = $this->xpath->query("//*[starts-with(name(), 'MEDIA_DOCUMENT_') and not(starts-with(name(), 'MEDIA_DOCUMENT_TEXT_')) ]");

        if(!empty($docs)) {
            foreach ($docs as $k=>$doc) {
                $doc_name = trim($doc->nodeValue);
                if(!empty($doc_name)) {
                    $doc_name   = basename($doc_name);
                    $doc_path   = $this->get_path('input').$doc_name;
                    $doc_url    =  $this->get_url('input').$doc_name;

                    if( file_exists($doc_path) ){
                        if( rename ( $doc_path,  get_path('images').$doc_name ) ) {
                            $doc_url    =  $this->get_url('images').$doc_name;
                        }
                    } else {
                        $doc_url    =  $this->get_url('images').$doc_name;
                    }
                    $docs->item($k)->setAttribute('url', $doc_url);
                    $this->logger_log('Doc Processed : '.$doc_url);

                }

            }
        }

    }

        function change_status($status,$ids) {

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where 1=1 AND unique_id NOT IN ('".implode("','",$ids)."')  ");

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
               //$this->logger_log('Undetermined listing ID : '.$listing->id.' marked as '.$db_data['status']);

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
    function blm_post_import($processed_ids) {

        if( empty($processed_ids) )
            return;

        $action = get_option('feedsync_blm_unknown_listings_action');

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

            if( !empty( $_SESSION['processed_ids'] ) ) {

                $this->blm_post_import( $_SESSION['processed_ids'] );
                $_SESSION['processed_ids'] = array();
            }


            die( json_encode(array('status' =>  'success', 'message'    =>  'all files have been processed', 'buffer'   =>  'complete')) );
        }

        $_SESSION['processed_ids'] = array();

        $this->init_log();

        foreach($this->elements->childNodes as $item) {

            if( isset($item->tagName) && !is_null($item->tagName) ) {

                /** process geocode **/
                $this->logger_log('Geocode processing initiated...');
                $this->geocode($item);
                $this->logger_log('Geocode processing completed');

                /** process image **/
                $this->logger_log('Image processing initiated...');
                $this->process_image();
                $this->logger_log('Image processing completed');

                /** add nodes */
                $this->logger_log('Node processing initiated...');
                $this->epl_nodes($item);
                $this->logger_log('Node processing completed');

                /** process documents **/
                $this->process_docs();

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
                            $this->logger_log('First Date not found, added firstDate:'.$exists->firstdate);

                        }

                        if ( get_post_meta($exists->id,'fav_listing',true) == 'yes' ) {

                            if ( !$this->has_node($item,'feedsyncFeaturedListing')) {

                                $fav             = $this->add_node($this->xmlFile,'feedsyncFeaturedListing','yes');
                                $item->appendChild($fav);
                                $this->logger_log('Fav listing detected, Set as fav');
                            }
                        }

                        $db_data['xml']             = $this->xmlFile->saveXML( $item);

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
                     if ( !$this->has_node($item,'firstDate')) {

                        $firstDate      = $this->xmlFile->createElement('firstDate', $db_data['mod_date']);
                        $item->appendChild($firstDate);

                     }

                    $db_data['xml'] = $this->xmlFile->saveXML( $item);
                    $db_data        =   array_map(array($this->db,'escape'), $db_data);

                    $this->insert_listing($db_data);
                    $this->log_report['listings_created']++;
                    $this->logger_log('---- Inserted listing ----'.PHP_EOL);
                }

                $_SESSION['processed_ids'][] = $db_data['feedsync_unique_id'];
            }

        }

        try {
            if( rename($this->path,$this->get_path('processed').basename($this->path) ) ) {

                $this->logger_log('---- File successfully moved to processed folder ----');
                $this->complete_log();

                if(!$this->cron_mode) {
                    die(
                        json_encode(
                            array(
                                'status'    =>  'success',
                                'message'   =>  basename($this->path).'  processed .'.$this->total_files.' files remaining. <br> <strong>Currently processing your files, do not navigate away from this page </strong>',
                                'geocoded'  =>  $this->geocoded_addreses_list,
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

    /**
     * Processes image
     * @return [domDocument Object]
     */
    function process_image($item = null) {

        $imgs = $this->xpath->query("//*[starts-with(name(), 'MEDIA_IMAGE_') and not(starts-with(name(), 'MEDIA_IMAGE_TEXT_')) ]");

        if(!empty($imgs)) {
            foreach ($imgs as $k=>$img) {
                $img_name = trim($img->nodeValue);
                if(!empty($img_name)) {
                    $img_name   = basename($img_name);
                    $img_path   = $this->get_path('input').$img_name;
                    $img_url    =  $this->get_url('input').$img_name;

                    if( file_exists($img_path) ){
                        if( rename ( $img_path,  get_path('images').$img_name ) ) {
                            $img_url    =  $this->get_url('images').$img_name;
                        }
                    } else {
                        $img_url    =  $this->get_url('images').$img_name;
                    }

                    $imgs->item($k)->setAttribute('url', $img_url);

                }

            }
        }

        /** @var floor path media */
        $imgs = $this->xpath->query("//*[starts-with(name(), 'MEDIA_FLOOR_PLAN_') and not(starts-with(name(), 'MEDIA_FLOOR_PLAN_TEXT_')) ]");

        if(!empty($imgs)) {
            foreach ($imgs as $k=>$img) {
                $img_name = trim($img->nodeValue);
                if(!empty($img_name)) {
                    $img_name   = basename($img_name);
                    $img_path   = $this->get_path('input').$img_name;
                    $img_url    =  $this->get_url('input').$img_name;

                    if( file_exists($img_path) ){
                        if( rename ( $img_path,  get_path('images').$img_name ) ) {
                            $img_url    =  $this->get_url('images').$img_name;
                        }
                    } else {
                        $img_url    =  $this->get_url('images').$img_name;
                    }

                    $imgs->item($k)->setAttribute('url', $img_url);

                }

            }
        }
    }

}

