<?php
include('class-processor.php');

class JUPIX_PROCESSOR extends FEEDSYNC_PROCESSOR {



    function get_sub_path(){
        return '/';
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
     * Return mapping from jupix availability to EPL status
     *
     * @param      boolean|integer  $status  The status
     * @param      <type>           $item    The item
     *
     * @return     string $status
     * @since      3.4.5 if feedsync_status exists, update it.
     */
    function jupix_property_status( $status,$item ) {

        $defaults = array();

        // Jupix    =>  Easy Property Listings
        $listing_type = $this->guess_property_type($item);

        $sold_stc = get_option('feedsync_jupix_sold_stc_action');
        $sold_stc = empty( $sold_stc ) ? 'current' : $sold_stc;

        $let_agreed = get_option('feedsync_jupix_let_agreed_action');
        $let_agreed = empty( $let_agreed ) ? 'current' : $let_agreed;

        switch($listing_type) {

            case 'property':
            case 'rural':

                $defaults = array(
                    '1' 	=>  'offmarket',    // On Hold
                    '2' 	=>  'current',  // For Sale
                    '3' 	=>  'current',  // Under Offer
                    '4' 	=>  $sold_stc, // Sold STC
                    '5' 	=>  'sold',     // Sold
                    '7' 	=>  'withdrawn' // Withdrawn
                );

                if ( $status == 4 && !$this->has_node($item,'feedsync_under_offer')) {

                    $fuo   = $this->add_node($this->xmlFile,'feedsync_under_offer','yes');
                    $item->appendChild($fuo);

                }

            break;

            case 'rental':
                $defaults = array(
                    '1' 	=>  __( 'offmarket' ,		'epl-jpi' ), // On Hold
                    '2' 	=>  __( 'current' ,		'epl-jpi' ), // To Let
                    '3' 	=>  __( 'current' ,		'epl-jpi' ), // References Pending - current
                    '4' 	=>  $let_agreed, // Let Agreed
                    '5' 	=>  __( 'leased' ,		'epl-jpi' ), // Let 
                    '6' 	=>  __( 'withdrawn' ,		'epl-jpi' ) // Withdrawn 
                );

                if ( $status == 4 && !$this->has_node($item,'feedsync_under_offer')) {

                    $fuo   = $this->add_node($this->xmlFile,'feedsync_under_offer','yes');
                    $item->appendChild($fuo);

                }
                
            break;

            case 'commercial':
            case 'Commercial':
                $defaults = array(
                    '1' 	=>  __( 'offmarket' ,         	'epl-jpi' ),
                    '2'		=>  __( 'current' ,        	'epl-jpi' ),
                    '3' 	=>  __( 'current' ,          	'epl-jpi' ),
                    '4' 	=>  __( 'current' ,   		'epl-jpi' ),
                    '5' 	=>  __( 'current' ,       	'epl-jpi' ),
                    '6' 	=>  $sold_stc,
                    '7' 	=>  __( 'sold' ,       		'epl-jpi' ),
                    '8' 	=>  __( 'sold' ,       		'epl-jpi' ),
                    '9' 	=>  $let_agreed,
                    '10'	=>  __( 'leased' ,		'epl-jpi' ),
                    '11'	=>  __( 'withdrawn' ,		'epl-jpi' )
                );
            break;
        }

        $status = isset($defaults[$status]) ? $defaults[$status] : $status ;
        if ( !$this->has_node($item,'feedsync_status')) {

            $fss   = $this->add_node($this->xmlFile,'feedsync_status', $status );
            $item->appendChild($fss);

        } else {
            $this->set_node_value( $item, 'feedsync_status', $status );
        }
        return  strtolower($status);
    }

    function guess_property_type($item) {


        $department = $this->get_node_value($item,'department');

        $map = array(
            'Lettings'  =>  'rental',
            'Sales'     =>  'property'
        );
        $type = isset( $map[$department] ) ? $map[$department] : $department;

        $this->set_node_value($item,'department',$type);

        return $type;
    }

    /**
     * Add EPL nodes
     * @return [type]
     */
    function epl_nodes($item) {

        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        $image_mod_date = false;

        $imgs = $this->xpath->query('//image[@modified]');
        if(!empty($imgs)) {

            foreach ($imgs as $k=>$img) {
                $image_mod_date = trim($img->getAttribute('modified'));
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

        /** Feedsync Unique ID ( propertyID + Agent ID ) */

        $feedsync_unique_id = $this->get_node_value($item,'propertyID');

        if( $this->has_node($item,'agentID') ) {

            $feedsync_unique_id = $this->get_node_value($item,'agentID').'-'.$this->get_node_value($item,'propertyID');

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

        $status =   $this->get_node_value($item,'availability');
        $status =   $this->jupix_property_status( $status,$item );

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
     * get initial values required to be updated / insereted for listing
     * @param  domDocument Object
     * @return domDocument Object
     */
    function get_initial_values($item){

            $db_data                       = array();
            $db_data['type']               = $this->guess_property_type($item);
            $db_data['unique_id']          = $this->get_node_value($item,'propertyID');
            $db_data['feedsync_unique_id'] = $this->get_node_value($item,'feedsyncUniqueID');
            $db_data['agent_id']           = $this->get_node_value($item,'branchID');
            $mod_time                      = $this->get_node_value($item,'timeLastModified');
            $mod_date                      = $this->get_node_value($item,'dateLastModified');
            $db_data['mod_date']           = $mod_date.' '.$mod_time;
            $db_data['firstdate']          = $this->convert_time_to_timezone( $db_data['mod_date'], get_option('feedsync_timezone') );
            
            $status                        = $this->get_node_value($item,'availability');
            $db_data['status']             = $this->jupix_property_status( $status,$item );
            
            
            $db_data['geocode']            = '';
            if( $this->geocode_enabled() )
            $db_data['geocode']            = $this->get_node_value($item,'feedsyncGeocode');
            
            $db_data['address']            = $this->get_address($item,true);
            
            // address components are available only after get_address method call **/
            $db_data['street']             = $this->get_address_component('street');
            $db_data['suburb']             = $this->get_address_component('suburb');
            $db_data['state']              = $this->get_address_component('state');
            $db_data['postcode']           = $this->get_address_component('postcode');
            $db_data['country']            = $this->get_address_component('country');
            
            $item                          = $this->add_required_nodes_and_atts($item,$db_data);
            $db_data['xml']                = $this->xmlFile->saveXML( $item);
            return $db_data;

    }

    /**
     * get address from a listing element
     * @param  [domDocument Object]
     * @param  boolean
     * @return [mixed]
     */
    function get_address($item,$comma_seperated = true) {

        $street_no = '';

        if($this->get_node_value($item,'addressName') != '') {
            $street_no = $this->get_node_value($item,'addressName');

            if($this->get_node_value($item,'addressNumber') != '') {
                $street_no .= ' '.$this->get_node_value($item,'addressNumber');
            }
        } elseif($this->get_node_value($item,'addressNumber') != '') {
            $street_no = $this->get_node_value($item,'addressNumber');
        }

        $street_addr  = $this->get_node_value($item,'addressStreet');
        if($this->get_node_value($item,'address2') != '') {
            $street_addr .= ' '.$this->get_node_value($item,'address2');
        }

        $this->address['streetnumber']   = $street_no;
        $this->address['street']         = $street_addr;
        $this->address['suburb']         = $this->get_node_value($item,'address3');
        $this->address['state']          = $this->get_node_value($item,'address4');
        $this->address['postcode']       = $this->get_node_value($item,'addressPostcode');
        $this->address['country']        = $this->get_node_value($item,'country');



        return $comma_seperated == true ? implode(", ", $this->address) : implode(" ", $this->address);

    }

    function fetch_xml() {

        $url        = get_option('feedsync_jupix_feed_url');
        $id         = get_option('feedsync_jupix_client_id');
        $pass       = get_option('feedsync_jupix_pass');

        $feed_url   = $url.'?clientID='.$id.'&passphrase='.$pass;

        $response   = feedsync_remote_get( $feed_url );
        $xml        = feedsync_remote_retrive_body( $response );

        $file_name_date = 'jupix-'.date("Y-m-d-H-i-s-T") ;

        $fp = fopen(get_path('input').$file_name_date.'.xml','w');

        if(!$fp) {
            die( json_encode(array('status' =>  'fail', 'message'    =>  'Unable to create xml', 'buffer'   =>  'processing')) );
        } else {
            $fw=fwrite($fp,$xml);
            fclose($fp);

        }
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
    function jupix_post_import($processed_ids) {

        if( empty($processed_ids) )
            return;

        $action = get_option('feedsync_jupix_unknown_listings_action');

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

                $this->jupix_post_import( $_SESSION['processed_ids'] );
                $_SESSION['processed_ids'] = array();
            }

            if( (!isset($_COOKIE['jupix_feed_fetched']) || $_COOKIE['jupix_feed_fetched'] != 1) ) {
                $this->fetch_xml();
                // set cookie for 30 mins
                setcookie("jupix_feed_fetched", 1, time()+60*30);
                die( json_encode(array('status' =>  'success', 'message'    =>  'Feed Fetched, Processing will follow...', 'buffer'   =>  'processing')) );
            }

            die( json_encode(array('status' =>  'success', 'message'    =>  'All files have been processed.', 'buffer'   =>  'complete')) );
        }

        $_SESSION['processed_ids'] = array();

        $this->init_log();

        $this->logger_log('==== File processing Initiated  : '.basename($this->path).' ===='.PHP_EOL);

        foreach($this->elements->childNodes as $item) {

            if( isset($item->tagName) && !is_null($item->tagName) ) {

                 $this->logger_log('---- Listing Process Initiated ----'.PHP_EOL);

                //$this->map_values_to_epl($item);

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

                        $existing_xml = new DOMDocument;
                        $existing_xml->preserveWhiteSpace = FALSE;
                        $existing_xml->loadXML($exists->xml);
                        $existing_xml->formatOutput = TRUE;
                        $existing_listing = $existing_xml->getElementsByTagName('*');
                        $existing_listing->item(0)->setAttribute('modTime', $db_data['mod_date']);
                        $existing_listing->item(0)->setAttribute('status', $db_data['status']);
                        $db_data['xml'] = $this->xmlFile->saveXML( $item);
                        $existing_listing_item  = $existing_listing->item(0);

                        if($existing_listing->item(0)->getElementsByTagName("address")->length != 0) {
                            $db_data['address']     = $this->get_address($existing_listing_item);
                        }

                        if( $this->has_node($existing_listing_item,'feedsyncGeocode'))
                            $db_data['geocode'] = $this->get_node_value($existing_listing_item,'feedsyncGeocode');

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

                        $firstDateValue             = $this->add_node($this->xmlFile,'firstDate',$db_data['mod_date']);
                        $item->appendChild($firstDateValue);
                        $this->logger_log('First Date added firstDate:'.$db_data['mod_date']);

                    }

                    $db_data['xml']     =   $this->xmlFile->saveXML( $item);
                    $db_data            =   array_map(array($this->db,'escape'), $db_data);

                    $this->insert_listing($db_data);
                    $this->log_report['listings_created']++;
                    $this->logger_log('---- Inserted listing ----'.PHP_EOL);
                }

                $_SESSION['processed_ids'][] = $db_data['feedsync_unique_id'];
            }

        }

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
            }
        } catch(Exception $e) {
            if(!$cron_mode) {
                echo $e->getMessage(); die;
            }
        }

    }

    function get_property_type_map($item) {

        $listing_type = $this->guess_property_type($item);

        switch($listing_type) {

            case 'property' :
            case 'rental' :

                return array(
                    '1' =>  __( 'Houses' ,          'epl-jpi' ),
                    '2' =>  __( 'Flats / Apartments' ,  'epl-jpi' ),
                    '3' =>  __( 'Bungalows' ,       'epl-jpi' ),
                    '4' =>  __( 'Other' ,           'epl-jpi' ),
                );

            break;

            case 'rural' :
                return array(
                    '1'     =>  __( 'Residential Farm' ,    'epl-jpi' ),
                    '2'     =>  __( 'Commercial Farm' ,     'epl-jpi' ),
                    '3'     =>  __( 'Poultry Farm' ,        'epl-jpi' ),
                    '4'     =>  __( 'Livestock Farm' ,      'epl-jpi' ),
                    '5'     =>  __( 'Arable Land' ,         'epl-jpi' ),
                    '6'     =>  __( 'Bare Land' ,       'epl-jpi' ),
                    '7'     =>  __( 'Grazing Land' ,        'epl-jpi' ),
                    '8'     =>  __( 'Paddocks' ,        'epl-jpi' ),
                    '9'     =>  __( 'Pasture Land' ,        'epl-jpi' ),
                    '10'    =>  __( 'Shooting' ,        'epl-jpi' ),
                    '11'    =>  __( 'Fishing' ,         'epl-jpi' ),
                    '12'    =>  __( 'General Leisure' ,     'epl-jpi' ),
                    '13'    =>  __( 'Woodland' ,        'epl-jpi' ),
                    '14'    =>  __( 'Investment Land' ,     'epl-jpi' ),
                    '15'    =>  __( 'Development Land' ,    'epl-jpi' ),
                    '16'    =>  __( 'Residential Land' ,    'epl-jpi' ),
                    '17'    =>  __( 'Residential Land' ,    'epl-jpi' ),
                    '18'    =>  __( 'Commercial / Industrial' , 'epl-jpi' )
                );
            break;

            case 'commercial' :
                return array(
                    '1'     =>  __( 'Offices' ,             'epl-jpi' ),
                    '2'     =>  __( 'Serviced Offices' ,        'epl-jpi' ),
                    '3'     =>  __( 'Business Park' ,           'epl-jpi' ),
                    '4'     =>  __( 'Science / Tech / R&D' ,        'epl-jpi' ),
                    '5'     =>  __( 'A1 - High Street' ,        'epl-jpi' ),
                    '6'     =>  __( 'A1 \96 Centre' ,           'epl-jpi' ),
                    '7'     =>  __( 'A1 - Out Of Town' ,        'epl-jpi' ),
                    '8'     =>  __( 'A1 \96 Other' ,            'epl-jpi' ),
                    '9'     =>  __( 'A2 - Financial Services' ,     'epl-jpi' ),
                    '10'    =>  __( 'A3 - Restaurants / Cafes' ,    'epl-jpi' ),
                    '11'    =>  __( 'A4 - Pubs / Bars / Clubs' ,    'epl-jpi' ),
                    '12'    =>  __( 'A5 - Take Away' ,          'epl-jpi' ),
                    '13'    =>  __( 'B1 - Light Industrial' ,       'epl-jpi' ),
                    '14'    =>  __( 'B2 - Heavy Industrial' ,       'epl-jpi' ),
                    '15'    =>  __( 'B8 - Warehouse / Distribution' ,   'epl-jpi' ),
                    '16'    =>  __( 'Science / Tech / R&D' ,        'epl-jpi' ),
                    '17'    =>  __( 'Other Industrial' ,        'epl-jpi' ),
                    '18'    =>  __( 'Caravan Park' ,            'epl-jpi' ),
                    '19'    =>  __( 'Cinema' ,              'epl-jpi' ),
                    '20'    =>  __( 'Golf Property' ,           'epl-jpi' ),
                    '21'    =>  __( 'Guest House / Hotel' ,         'epl-jpi' ),
                    '22'    =>  __( 'Leisure Park' ,            'epl-jpi' ),
                    '23'    =>  __( 'Leisure Other' ,           'epl-jpi' ),
                    '24'    =>  __( 'Day Nursery / Child Care' ,    'epl-jpi' ),
                    '25'    =>  __( 'Nursing & Care Homes' ,        'epl-jpi' ),
                    '26'    =>  __( 'Surgeries' ,           'epl-jpi' ),
                    '27'    =>  __( 'Petrol Stations' ,         'epl-jpi' ),
                    '28'    =>  __( 'Show Room' ,           'epl-jpi' ),
                    '29'    =>  __( 'Garage' ,              'epl-jpi' ),
                    '30'    =>  __( 'Industrial (land)' ,       'epl-jpi' ),
                    '31'    =>  __( 'Office (land)' ,           'epl-jpi' ),
                    '32'    =>  __( 'Residential (land)' ,      'epl-jpi' ),
                    '33'    =>  __( 'Retail (land)' ,           'epl-jpi' ),
                    '34'    =>  __( 'Leisure (land)' ,          'epl-jpi' ),
                    '35'    =>  __( 'Commercial / Other (land)' ,   'epl-jpi' ),
                    '36'    =>  __( 'Refurbishment Opportunities' ,     'epl-jpi' ),
                    '37'    =>  __( 'Residential Conversions' ,     'epl-jpi' ),
                    '38'    =>  __( 'Residential' ,             'epl-jpi' ),
                    '39'    =>  __( 'Commercial' ,          'epl-jpi' ),
                    '40'    =>  __( 'Ground Leases' ,           'epl-jpi' )
                );

            break;

        }
    }

    function map_values_to_epl($item) {

        /**
         * forSalePOA
         * toLetPOA
         * developmentOpportunity
         * investmentOpportunity
         * studentProperty
         * toLet
         */

        $map = array(

            '0' =>  'yes',  // Display Price
            '1' =>  'no'    // Hide Price
        );

        if ( $this->has_node($item,'forSalePOA') ) {

            $sale_poa = $this->get_node_value($item,'forSalePOA');
            $sale_poa = isset($map[$sale_poa]) ? $map[$sale_poa] : $sale_poa ;
            $this->set_node_value($item,'forSalePOA',$sale_poa);
        }

        if ( $this->has_node($item,'toLetPOA') ) {

            $toLetPOA = $this->get_node_value($item,'toLetPOA');
            $toLetPOA = isset($map[$toLetPOA]) ? $map[$toLetPOA] : $toLetPOA ;
            $this->set_node_value($item,'toLetPOA',$toLetPOA);
        }

        if ( $this->has_node($item,'developmentOpportunity') ) {

            $developmentOpportunity = $this->get_node_value($item,'developmentOpportunity');
            $developmentOpportunity = isset($map[$developmentOpportunity]) ? $map[$developmentOpportunity] : $developmentOpportunity ;
            $this->set_node_value($item,'developmentOpportunity',$developmentOpportunity);
        }

        if ( $this->has_node($item,'investmentOpportunity') ) {

            $investmentOpportunity = $this->get_node_value($item,'investmentOpportunity');
            $investmentOpportunity = isset($map[$investmentOpportunity]) ? $map[$investmentOpportunity] : $investmentOpportunity ;
            $this->set_node_value($item,'investmentOpportunity',$investmentOpportunity);
        }
        if ( $this->has_node($item,'studentProperty') ) {

            $studentProperty = $this->get_node_value($item,'studentProperty');
            $studentProperty = isset($map[$studentProperty]) ? $map[$studentProperty] : $studentProperty ;
            $this->set_node_value($item,'studentProperty',$studentProperty);
        }
        if ( $this->has_node($item,'toLet') ) {

            $toLet = $this->get_node_value($item,'toLet');
            $toLet = isset($map[$toLet]) ? $map[$toLet] : $toLet ;
            $this->set_node_value($item,'toLet',$toLet);
        }

        /**
         * saleBy
         */

        $saleby_map = array(
            '0' =>  'open',
            '1' =>  'exclusive',
            '2' =>  'auction',  // Important for EPL Pricing
            '3' =>  'confidential',
            '4' =>  'tender',
            '5' =>  'offers',
        );

        if ( $this->has_node($item,'saleBy') ) {

            $saleBy = $this->get_node_value($item,'saleBy');
            $saleBy = isset($saleby_map[$saleBy]) ? $saleby_map[$saleBy] : $saleBy ;
            $this->set_node_value($item,'saleBy',$saleBy);
        }

        /**
         * propertyAge
         */

        $age_map = array(
            '0' =>  __( 'Not Specified' ,       'epl-jpi' ),
            '1' =>  __( 'New Build' ,       'epl-jpi' ),
            '2' =>  __( 'Modern' ,          'epl-jpi' ),
            '3' =>  __( '1980s to 1990s' ,      'epl-jpi' ),
            '4' =>  __( '1950s, 1960s and 1970s' ,  'epl-jpi' ),
            '5' =>  __( '1940s' ,           'epl-jpi' ),
            '6' =>  __( '1920s to 1930s' ,      'epl-jpi' ),
            '7' =>  __( 'Edwardian (1901 - 1910)' , 'epl-jpi' ),
            '8' =>  __( 'Victorian (1837 - 1901)' , 'epl-jpi' ),
            '9' =>  __( 'Georgian (1714 - 1830)' ,  'epl-jpi' ),
            '10'    =>  __( 'Pre 18th Century' ,    'epl-jpi' )
        );
        if ( $this->has_node($item,'propertyAge') ) {

            $propertyAge = $this->get_node_value($item,'propertyAge');
            $propertyAge = isset($age_map[$propertyAge]) ? $age_map[$propertyAge] : $propertyAge ;
            $this->set_node_value($item,'propertyAge',$propertyAge);
        }

        /**
         * Price qualifier
         */
        $price_qualifier_map = array(
            '1' =>  __( 'Asking Price Of' ,     'epl-jpi' ),
            '2' =>  __( 'Fixed Price' ,         'epl-jpi' ),
            '3' =>  __( 'From' ,            'epl-jpi' ),
            '4' =>  __( 'Guide Price' ,         'epl-jpi' ),
            '5' =>  __( 'Offers In Region Of' ,     'epl-jpi' ),
            '6' =>  __( 'Offers Over' ,         'epl-jpi' ),
            '7' =>  __( 'Auction Guide Price' ,     'epl-jpi' ),
            '8' =>  __( 'Sale By Tender' ,      'epl-jpi' ),
            '9' =>  __( 'Shared Ownership' ,    'epl-jpi' ),
            '10'    =>  __( 'Offers In Excess Of' ,     'epl-jpi' )
        );

        if ( $this->has_node($item,'priceQualifier') ) {

            $priceQualifier = $this->get_node_value($item,'priceQualifier');
            $priceQualifier = isset($price_qualifier_map[$priceQualifier]) ? $price_qualifier_map[$priceQualifier] : $priceQualifier ;
            $this->set_node_value($item,'priceQualifier',$priceQualifier);
        }

        /**
         * Property Tenure
         */
        $tenure_map = array(
            '0' =>  __( 'Not Specified' ,       'epl-jpi' ),
            '1' =>  __( 'Freehold' ,        'epl-jpi' ),
            '2' =>  __( 'Leasehold' ,       'epl-jpi' ),
            '3' =>  __( 'Commonhold' ,      'epl-jpi' ),
            '4' =>  __( 'Share of Freehold' ,   'epl-jpi' ),
            '5' =>  __( 'Flying Freehold' ,     'epl-jpi' ),
            '6' =>  __( 'Share Transfer' ,      'epl-jpi' )
        );

        if ( $this->has_node($item,'propertyTenure') ) {

            $propertyTenure = $this->get_node_value($item,'propertyTenure');
            $propertyTenure = isset($tenure_map[$propertyTenure]) ? $tenure_map[$propertyTenure] : $propertyTenure ;
            $this->set_node_value($item,'propertyTenure',$propertyTenure);
        }

        /**
         * propertyType
         */

        $type_map = $this->get_property_type_map($item);

        if ( $this->has_node($item,'propertyType') ) {

            $propertyType = $this->get_node_value($item,'propertyType');
            $propertyType = isset($type_map[$propertyType]) ? $type_map[$propertyType] : $propertyType ;
            $this->set_node_value($item,'propertyType',$propertyType);
        }

        /**
         * propertyStyle
         */
        $style_map = array(

            '1'     =>   __( 'Barn Conversion' , 'epl-jpi' ),
            '2'     =>   __( 'Cottage' , 'epl-jpi' ),
            '3'     =>   __( 'Chalet' , 'epl-jpi' ),
            '4'     =>   __( 'Detached House' , 'epl-jpi' ),
            '5'     =>   __( 'Semi-Detached House' , 'epl-jpi' ),
            '28'    =>   __( 'Link Detached' , 'epl-jpi' ),
            '6'     =>   __( 'Farm House' , 'epl-jpi' ),
            '7'     =>   __( 'Manor House' , 'epl-jpi' ),
            '8'     =>   __( 'Mews' , 'epl-jpi' ),
            '9'     =>   __( 'Mid Terraced House' , 'epl-jpi' ),
            '10'    =>   __( 'End Terraced House' , 'epl-jpi' ),
            '11'    =>   __( 'Town House' , 'epl-jpi' ),
            '12'    =>   __( 'Villa' , 'epl-jpi' ),
            '29'    =>   __( 'Shared House' , 'epl-jpi' ),
            '31'    =>   __( 'Sheltered Housing' , 'epl-jpi' ),
            '13'    =>   __( 'Apartment' , 'epl-jpi' ),
            '14'    =>   __( 'Bedsit' , 'epl-jpi' ),
            '15'    =>   __( 'Ground Floor Flat' , 'epl-jpi' ),
            '16'    =>   __( 'Flat' , 'epl-jpi' ),
            '17'    =>   __( 'Ground Floor Maisonette' , 'epl-jpi' ),
            '18'    =>   __( 'Maisonette' , 'epl-jpi' ),
            '19'    =>   __( 'Penthouse' , 'epl-jpi' ),
            '20'    =>   __( 'Studio' , 'epl-jpi' ),
            '30'    =>   __( 'Shared Flat' , 'epl-jpi' ),
            '21'    =>   __( 'Detached Bungalow' , 'epl-jpi' ),
            '35'    =>   __( 'End Terraced Bungalow' , 'epl-jpi' ),
            '34'    =>   __( 'Mid Terraced Bungalow' , 'epl-jpi' ),
            '22'    =>   __( 'Semi-Detached Bungalow' , 'epl-jpi' ),
            '23'    =>   __( 'Building Plot / Land' , 'epl-jpi' ),
            '24'    =>   __( 'Garage' , 'epl-jpi' ),
            '25'    =>   __( 'House Boat' , 'epl-jpi' ),
            '26'    =>   __( 'Mobile Home' , 'epl-jpi' ),
            '27'    =>   __( 'Parking' , 'epl-jpi' ),
            '32'    =>   __( 'Equestrian' , 'epl-jpi' ),
            '33'    =>   __( 'Unconverted Barn' , 'epl-jpi' )
        );

        if ( $this->has_node($item,'propertyStyle') ) {

            $propertyStyle = $this->get_node_value($item,'propertyStyle');
            $propertyStyle = isset($style_map[$propertyStyle]) ? $style_map[$propertyStyle] : $propertyStyle ;
            $this->set_node_value($item,'propertyStyle',$propertyStyle);
        }

        /**
         * rentFrequency
         */
        $rent_frequency_map = array(
            '1' =>  __( 'pcm' ,         'epl-jpi' ),
            '2' =>  __( 'pw' ,          'epl-jpi' ),
            '3' =>  __( 'pa' ,          'epl-jpi' )
        );
        if ( $this->has_node($item,'rentFrequency') ) {

            $rentFrequency = $this->get_node_value($item,'rentFrequency');
            $rentFrequency = isset($rent_frequency_map[$rentFrequency]) ? $rent_frequency_map[$rentFrequency] : $rentFrequency ;
            $this->set_node_value($item,'rentFrequency',$rentFrequency);
        }

        return $item;
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

            // if item has Latitude,Longitude node, extract value from it and save it to feedsyncGeocode node
            if( $this->has_node($item,'Latitude') &&  $this->get_node_value($item,'Latitude') != '') {


                $item = $this->geocode_from_lat_long_node($item);

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
     * attempts to fetch geocode from Latitude,Longitude node, if present
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode_from_lat_long_node($item){

        $lat                = $this->get_node_value($item,'Latitude');
        $long               = $this->get_node_value($item,'Longitude');

        // make coordinates class wide available
        $this->coord        = $lat.','.$long;
        $this->logger_log('Geocoded from Latitude, Longitude node : '.$this->coord);
        return $this->update_feedsync_node($item,$coord);
    }

    function add_required_nodes_and_atts($item,$data) {

        $item->setAttribute('status',$data['status']);
        $item->setAttribute('modTime',$data['mod_date']);

         if ( !$this->has_node($item,'address')) {

            $address_node   = $this->add_node($this->xmlFile,'address','');
            $item->appendChild($address_node);

            foreach($this->address as $addr_key => $value) {
                $temp_node = $this->add_node($this->xmlFile,$addr_key,$value);
                $address_node->appendChild($temp_node);
            }
         }

        return $item;
    }
}