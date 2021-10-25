<?php
include('class-processor.php');
require_once(CORE_PATH.'classes/class-feedsync-setup-preprocessor.php');
class XML2U_PROCESSOR extends FEEDSYNC_PROCESSOR {

	private $sources;

	private $sources_status;

	private $current_source;

	private $current_source_url;

	private $current_source_status;

	/**
     * Init the import process
     * @since  :      3.0.1
     * @return [type] [description]
     */
    function init() {

    	$this->sources 			= $this->get_agent_sources();
    	$this->sources_status 	= $this->get_sources_status();
        $this->xmls         	= $this->get_xmls();
        $this->total_files  	= count($this->xmls);

        if( $this->get_xml_to_process() ){

            /** configuration **/
            $this->xmlFile = new DOMDocument('1.0','UTF-8');
            libxml_use_internal_errors(true);
            $this->xmlFile->formatOutput = true;
            $this->xmlFile->preserveWhiteSpace = false;
            $this->xmlFile->recover = TRUE;
            $this->xmlFile->load($this->path);
            $this->xpath = new DOMXPath($this->xmlFile);
            /** configuration - end **/

            /** check if xml is not empty & and is valid **/
            $this->handle_blank_xml();
            $this->handle_invalid_xml();

            /** extract dom elements and cache it as class property **/
            $this->dom_elements();

        }
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

	function get_agent_sources() {

		$feed_urls = trim( get_option( 'feedsync_xml2u_feed_urls' ) );
		$feed_urls = array_filter( array_unique( explode( "\n", $feed_urls ) ) );
		if( !empty( $feed_urls ) ) {

			$return = array();
			foreach( $feed_urls as $url ) {

				$url = rawurldecode( $url );
				$fragements = parse_url($url);
				$paths = $fragements['path'];
				$paths = explode('/',$paths);
				$file_name = $paths[2];
				$file_name = preg_replace( '/[^a-z0-9_\-]/', '-', strtolower( $file_name ) );

				$return[ $file_name ] = $url;
			}

			return $return;
		}

	}

	function get_sources_status() {

		$status = get_option_data( 'feedsync_xml2u_sources_status' );

        $processing_complete = true;

		if( empty( $status ) ) { // first run or finished previous run
            $processing_complete = false;
			$status = array();
			$sources = $this->get_agent_sources();
			$i = 0;
			foreach ($sources as $key => $value) {

				$status[$key] = array(
					'fetched'	=>	false,
					'processed'	=>	false
				);

				if( 0 === $i ) {

					$this->current_source = $key;
					$this->current_source_url = $value;
					$this->current_source_status = $status[$key];
				}

				$i++;
			}
		} else {

			foreach ($status as $src_key => $src_value) {

				if( ! $src_value['fetched'] || ! $src_value['processed'] ) {
					$sources 						= $this->get_agent_sources();
					$this->current_source 			= $src_key;
					$this->current_source_url 		= $sources[ $src_key ];
					$this->current_source_status 	= $src_value;
                    $processing_complete = false;
                    break;
				}
			}

		}

        if( $processing_complete ) {
            update_option( 'feedsync_xml2u_sources_status', array() );
             die( json_encode( array( 'status' =>  'success', 'message'    =>  'All source files have been processed.', 'buffer'   =>  'complete') ) );
        }

		return $status;
	}

	function get_source_status( $source ) {

		$status = $this->get_sources_status();
		return $status[ $source ];
	}

	function is_source_fetched( $source ) {

		$status = $this->get_source_status( $source );
		return $status['fetched'];
	}

	function is_source_processed( $source ) {

		$status = $this->get_source_status( $source );
		return $status['processed'];
	}

	function generate_filename( $url ) {

		$url = rawurldecode( $url );
		$fragements = parse_url($url);
		$paths = $fragements['path'];
		$paths = explode('/',$paths);
		$file_name = $paths[2];
		$file_name = preg_replace( '/[^a-z0-9_\-]/', '-', strtolower( $file_name ) );
		$file_name .= '-'.date( "Y-m-d-H-i-s", time() ).'.xml';

		return $file_name;
	}

	function fetch_source($feed_url) {

		$feed_url = rawurlencode( trim( $feed_url ) );
        $feed_url = str_replace('%3A',':',str_replace('%2F','/',$feed_url));

        // set headers
        $headers = array();
        $headers['Content-type'] = "text/xml";

        $response       = feedsync_remote_get( $feed_url, $headers );
        $xml            = feedsync_remote_retrive_body( $response );

        $file_name_date = $this->generate_filename( $feed_url );

        $fp = fopen( get_path('input').$file_name_date,'w' );

        if(!$fp) {
            die( json_encode(array('status' =>  'fail', 'message'    =>  'Unable to create xml', 'buffer'   =>  'processing')) );
        } else {
            $fw=fwrite($fp,$xml);
            fclose($fp);

        }
    }

    /**
     * process agents from a listing node
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function process_agents($item) {

    	$all_agents = array('1','2');

    	foreach( $all_agents as $agent_id ) {

	    	$node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

			$f_name                  = $this->get_node_value($item,'agent'.$agent_id.'FirstName');
			$l_name                  = $this->get_node_value($item,'agent'.$agent_id.'LastName');
			$data_agent['telephone'] = $this->get_node_value($item,'agent'.$agent_id.'Phone');
			$data_agent['email']     = $this->get_node_value($item,'agent'.$agent_id.'Email');
			$data_agent['agent_id']  = $agent_id;
			$data_agent['office_id'] = '';;
			$data_agent['name']      = trim( $f_name.' '.$l_name );

			if( empty( $data_agent['name'] ) ) {
				$data_agent['name'] = $data_agent['email'];
			}

			$data_agent['username']  = sanitize_user_name( $data_agent['name'] );

			// create xml element
			$listing_agent = $this->add_node($node_to_add,'listingAgent','');
	        $item->appendChild($listing_agent);
			$listing_agent->setAttribute('id', $data_agent['agent_id'] );

			$name = $this->xmlFile->createElement( 'name', $data_agent['name'] );
			$listing_agent->appendChild($name);

			$email = $this->xmlFile->createElement( 'email', $data_agent['email'] );
			$listing_agent->appendChild($email);

			$tel = $this->xmlFile->createElement( 'telephone', $data_agent['telephone'] );
			$listing_agent->appendChild($tel);

			$uname = $this->xmlFile->createElement( 'username', $data_agent['username'] );
			$listing_agent->appendChild($uname);

			$data_agent['xml']  = $this->xmlFile->saveXML( $listing_agent );
			$data_agent         =   array_map(array($this->db,'escape'), $data_agent);

			/** check if listing agent exists already **/
	        $agent_exists = $this->db->get_row("SELECT * FROM feedsync_users where name = '{$data_agent['name']}' ");

	        if( empty($agent_exists) ) {

	            /** insert new data **/
	            $query = "INSERT INTO
	            feedsync_users (office_id,name,telephone,email,xml,listing_agent_id,username)
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
	            $query = "UPDATE feedsync_users SET
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

    function update_source_status( $fetched = false, $processed = false) {

    	foreach( $this->sources_status as $k => &$v ) {

    		if( $k == $this->current_source ) {
    			$v[ 'fetched' ] 	= $fetched;
    			$v[ 'processed' ] 	= $processed;
    		}
    	}
    	$update_status = update_option( 'feedsync_xml2u_sources_status', $this->sources_status );

    	// update class properties
    	$this->sources_status 	= $this->get_sources_status();

    }

    /**
     * Geocode the listing item :)
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode($item,$process_missing = false){

        $this->geocoded_addreses_list = "\n";

        /** add feedsyncGeocode node if not already there or if force geocode mode is on **/
        if( !$this->has_node($item,'feedsyncGeocode') || $this->force_geocode() || $this->get_node_value($item,'feedsyncGeocode') == '' ) {

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
     * get address from a listing element
     * @param  [domDocument Object]
     * @param  boolean
     * @return [mixed]
     */
    function get_address($item,$comma_seperated = true) {

        $address        = $this->get_first_node($item,'Address');

        $this->address['streetnumber']      = $this->get_node_value($address,'number');
        $this->address['street']            = $this->get_node_value($address,'street');
        $this->address['suburb']            = $this->get_node_value($address,'location');
        $this->address['state']             = $this->get_node_value($address,'region');
        $this->address['postcode']          = $this->get_node_value($address,'postcode');

        if( $this->has_node($address,'country') ) {
            $this->address['country']        = $this->get_node_value($address,'country');
        } else {
            $this->address['country']        = "Australia";
        }

        $address_array = array_filter($this->address);

        $address_string =  $comma_seperated == true ? implode(", ", $address_array) : implode(" ", $address_array);

        $this->address['lotNumber']         = '';
        $this->address['subNumber']         = '';

        if( $this->has_node($address,'lotNumber') )
            $this->address['lotNumber']         = $this->get_node_value($address,'lotNumber');

        if( $this->has_node($address,'subNumber') )
            $this->address['subNumber']         = $this->get_node_value($address,'subNumber');

        if( $this->address['lotNumber'] != '' && $this->address['streetnumber'] != ''){
            $address_string = $this->address['lotNumber'].'/'.$address_string;
        }

        if( $this->address['subNumber'] != '' && $this->address['streetnumber'] != ''){
            $address_string = $this->address['subNumber'].'/'.$address_string;
        }

        return $address_string;
    }

    /**
     * Add EPL nodes
     *
     * Add Image mod date
     * @return [type]
     */
    function epl_nodes($item) {

        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        $image_mod_date                   = $this->get_node_value($item,'lastUpdateDate');
        if($image_mod_date == '') {
            $image_mod_date = date("Y-m-d H:i:s",time());
        } else {
            $image_mod_date = date("Y-m-d H:i:s",strtotime( $image_mod_date ) );
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

        $feedsync_unique_id = $this->get_node_value($item,'propertyid');

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

    function guess_property_type($item) {

        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        $category = $this->get_node_value($item,'category');

        $map = array(
            'Residential For Sale'  =>  'property',
            'Commercial For Sale'   =>  'commercial',
            'Commercial For Rent'   =>  'commercial',
            'Residential For Rent'  =>  'rental',
            'Land For Sale'         =>  'land',
            'Vacation Rental'       =>  'holiday_rental'
        );

        if( $category == 'Commercial For Sale' ) {

            // if node not already exists, add it
            if( ! $this->has_node($item,'commercialListingType') ) {

                $element = $this->add_node($node_to_add,'commercialListingType','sale');
                $item->appendChild($element);

            } else {
                // if node already exists, just update the value
                $item = $this->set_node_value($item,'commercialListingType','sale');
            }

        }

        if( $category == 'Commercial For Rent' ) {

            // if node not already exists, add it
            if( ! $this->has_node($item,'commercialListingType') ) {

                $element = $this->add_node($node_to_add,'commercialListingType','lease');
                $item->appendChild($element);

            } else {
                // if node already exists, just update the value
                $item = $this->set_node_value($item,'commercialListingType','lease');
            }

        }

        $type = isset( $map[$category] ) ? $map[$category] : 'property';

        $this->set_node_value($item,'category',$type);

        return $type;
    }

    function guess_property_status($item) {

        $status = $this->get_node_value($item,'status');
        $node_to_add = !empty($this->xmlFile) ? $this->xmlFile : $item;

        if( empty( $status ) ){
            $status = 'current';
         }

         if( in_array( $status, array( 'SSTC', 'For Sale' ) ) ) {
            $status = 'current';
         }

        $item->setAttribute('status',strtolower($status) );

        if( $status == 'UNDER OFFER' ) {

            // if node not already exists, add it
            if( ! $this->has_node($item,'feedsync_under_offer') ) {

                $element = $this->add_node($node_to_add,'feedsync_under_offer','yes');
                $item->appendChild($element);

            } else {
                // if node already exists, just update the value
                $item = $this->set_node_value($item,'feedsync_under_offer','yes');
            }

        }
        return strtolower($status);
    }

    function add_required_nodes_and_atts($item,$data) {

        $item->setAttribute('status',$data['status']);
        $item->setAttribute('modTime',$data['mod_date']);
        $this->logger_log('Added status and modTime');
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
        $db_data['unique_id']          = $this->get_node_value($item,'propertyid');
        $db_data['feedsync_unique_id'] = $this->get_node_value($item,'feedsyncUniqueID');

        $agent_id = explode('-', $db_data['feedsync_unique_id'] );
        $agent_id = current( $agent_id );

        $db_data['agent_id']           = $agent_id;
        $mod_date                      = $this->get_node_value($item,'lastUpdateDate');
        $db_data['mod_date']           = date("Y-m-d H:i:s",strtotime( $mod_date) );
        $db_data['status']             = $this->guess_property_status( $item );
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
     * Handle undetermined listings
     * @return [type]
     */
    function xml2u_post_import($processed_ids) {

        if( empty($processed_ids) )
            return;

        $first_listing = current( $processed_ids );
        $agent_id = explode('-', $first_listing );
        $agent_id = current( $agent_id );
        $this->change_status('withdrawn',$processed_ids,$agent_id);
    }

    function change_status($status,$ids,$agent_id) {

        if( empty( $agent_id ) )
            return;

        $log_withdrawn = array();

        $query = "SELECT * 
                    FROM feedsync 
                    WHERE 1=1 
                    AND unique_id NOT IN ('".implode("','",$ids)."')  
                    AND agent_id = '{$agent_id}' ";

        $alllistings = $this->db->get_results( $query );

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {

                if( 'sold' == $listing->status ) {
                    continue;
                }

                $log_withdrawn[] = $listing->feedsync_unique_id;

                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->xpath = new DOMXPath($this->xmlFile);

                $item = $this->xmlFile->documentElement;

                $item->setAttribute('status',$status);

                $newxml         = $this->xmlFile->saveXML($item);

                $db_data   = array(
                    'xml'                   =>  $newxml,
                    'status'                =>  $status
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);

                $query = "UPDATE feedsync SET
                                xml                             = '{$db_data['xml']}',
                                status                          = '{$db_data['status']}'
                                WHERE id                        = '{$listing->id}'
                            ";

               $this->db->query($query);

            }

            $this->init_log();
            $log_withdrawn = implode(',',$log_withdrawn);
            $this->logger_log("Listings Marked Withdrawn :  \n ".$log_withdrawn."\n\r\n");
        }   
    }

    /**
     * Import listings to database
     * @return json
     */
    function import(){

        if( empty($this->elements) ) {

            if( !empty( $_SESSION['processed_ids'] ) ) {

                $this->xml2u_post_import( $_SESSION['processed_ids'] );
                $_SESSION['processed_ids'] = array();
                $this->update_source_status( true, true );

                if(!$this->cron_mode) {
                    die( json_encode( array('status' =>  'success', 'message'    =>  $this->current_source.' processing complete, Processing next feed...', 'buffer'   =>  'processing') ) );
                } else {
                    echo json_encode( array('status' =>  'success', 'message'    =>  $this->current_source.' processing complete, Processing next feed...', 'buffer'   =>  'processing') );
                    $this->init();
                }
                
            }

        	if( ! $this->is_source_fetched( $this->current_source ) || empty( $this->elements ) ) {

        		$this->fetch_source( $this->current_source_url );

                new FEEDSYNC_SETUP_PROCESSOR(); // create chunks

        		$this->update_source_status( true, false );

                if( !$this->cron_mode ) {

                    die( json_encode(array('status' =>  'success', 'message'    =>  $this->current_source.' Feed Fetched, Processing will follow...', 'buffer'   =>  'processing')) );
                } else {

                    echo json_encode( array('status' =>  'success', 'message'    =>  $this->current_source.'Cron : Feed Fetched, Processing will follow...', 'buffer'   =>  'processing') );

                    $this->init();
                    $this->import();

                }

        	}
        }

        $this->init_log();
        $this->logger_log('==== File processing Initiated  : '.basename($this->path).' ===='.PHP_EOL);

        foreach($this->elements->childNodes as $item) {

            if( isset($item->tagName) && !is_null($item->tagName) ) {

                $this->logger_log('---- Listing Process Initiated ----'.PHP_EOL);

                /** process agents **/
                $this->logger_log('Agent processing initiated...');
                $this->process_agents($item);
                $this->logger_log('Agent processing completed');

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
                $exists = $this->db->get_row("SELECT * FROM feedsync where feedsync_unique_id = '{$db_data['feedsync_unique_id']}' ");

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

                        /** check if this xml has img node */
                        if ( !$this->has_node($item,'img') ) {

                            $this->logger_log('No images detected, checking in existing xml in DB');

                            /** remove blank images node from XML */
                            if ( $this->has_node($item,'images') ) {
                                $parent_image_node = $this->get_first_node($item,'images');
                                $parent_image_node->parentNode->removeChild($parent_image_node);
                                $this->logger_log('blank images node removed');
                            }

                            /** Load existing xml from database */
                            $existing_xml = new DOMDocument('1.0', 'UTF-8');
                            $existing_xml->preserveWhiteSpace = FALSE;
                            $existing_xml->loadXML($exists->xml);
                            $existing_xml->formatOutput = TRUE;
                            
                            /** Import images node to XML if its not empty */
                            if( $this->has_node($existing_xml,'img') && $this->has_node($existing_xml,'images') ) {

                                /** fetch images node from xml in DB */
                                $backup_images = $this->get_first_node($existing_xml,'images');

                                $this->logger_log('images found in existing xml in DB');
                                $backup_images_node  = $this->xmlFile->importNode($backup_images, true);
                                $item->appendChild($backup_images_node);
                                $db_data['xml']             = $this->xmlFile->saveXML( $item);
                                $this->logger_log('Copied images from existing xml in DB');
                            }
                            
                        }

                        /** dont update whole xml if address is missing **/
                        if ( !$this->has_node($item,'Address') ) {

                            $this->logger_log('Address missing, skip updating whole xml');

                            $existing_xml = new DOMDocument('1.0', 'UTF-8');
                            $existing_xml->preserveWhiteSpace = FALSE;
                            $exists->xml = html_entity_decode($exists->xml, ENT_QUOTES | ENT_HTML5);
                            $exists->xml = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $exists->xml);
                            $existing_xml->loadXML( $exists->xml );
                            $existing_xml->formatOutput = TRUE;
                            $existing_listing = $existing_xml->getElementsByTagName('*');
                            $existing_listing->item(0)->setAttribute('modTime', $db_data['mod_date']);
                            $existing_listing->item(0)->setAttribute('status', $db_data['status']);
                            $db_data['xml'] = $existing_xml->saveXML($existing_listing->item(0));
                            $existing_listing_item  = $existing_listing->item(0);

                            if($existing_listing->item(0)->getElementsByTagName("address")->length != 0) {
                                $db_data['address']     = $this->get_address($existing_listing_item);
                            }

                            if( $this->has_node($existing_listing_item,'feedsyncGeocode'))
                                $db_data['geocode'] = $this->get_node_value($existing_listing_item,'feedsyncGeocode');

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

                $_SESSION['processed_ids'][] = $db_data['feedsync_unique_id'];
            }

        }
        $this->logger_log( json_encode( $this->sources_status ) );
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
                                'message'   =>  basename($this->path).'  processed .'.$this->total_files.' files remaining. Currently processing your files.',
                                'geocoded'  =>  $this->geocoded_addreses_list,
                                'buffer'    =>  'processing'
                            )
                        )
                    );

                } else {
                    echo json_encode(
                        array(
                            'status'    =>  'success',
                            'message'   =>  basename($this->path).'  processed .'.$this->total_files.' files remaining. <br /> Currently processing your files, do not navigate away from this page.',
                            'geocoded'  =>  $this->geocoded_addreses_list,
                            'buffer'    =>  'processing'
                        )
                    );

                    $this->reset();
                    $this->init();
                    $this->import();
                }
            }
        } catch(Exception $e) {
            if(!$cron_mode) {
                $this->logger_log('---- file moving error : '.$e->getMessage() );
                echo $e->getMessage(); die;
            }
        }

    }


}
