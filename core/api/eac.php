<?php

class EAC_API{

	public $params;

	private $api_url = 'http://api.eac.com.au/';

	private $api_test_url = 'https://www.eac.net.au/api/';

	private $dev_mode = true;

	private $processing_limit = 5;

	private $logger;

	private $cron_mode;

	public static $agent_process_mode = 'default'; // once if agent id is set in params

	public static $agent_processed_once = false;

	function __construct($cron_mode = false) {
		$requests = get_option('feedsync_eac_listings_per_request');
		if( intval($requests) >= 1 ){
			$this->processing_limit = intval($requests);
		}

		$this->logger = new PHPLogger(SITE_ROOT.'eac.log');

		$this->cron_mode = $cron_mode;

		$api_mode = get_option('feedsync_eac_api_mode');
		$this->dev_mode = $api_mode == 'test' ? true : false;
		

        $this->db           = fsdb();

		$this->process_params();
	}

    /**
     * check if parent node has a child node
     * @param  [domDocument Object]
     * @param  [string]
     * @return boolean
     */
    function has_node($item,$node){
        return $item->getElementsByTagName($node)->length == 0 ? false : true;
    }

    /**
     * get child nodes from parent nodes
     * @param  [domDocument Object]
     * @param  [string]
     * @return [domDocument Object]
     */
    function get_nodes($item,$node){
        return $item->getElementsByTagName($node);
    }

    /**
     * Get first child node from parent node
     * @param  [domDocument Object]
     * @param  [string]
     * @return [domDocument Object]
     */
    function get_first_node($item,$node){
        $nodes =$this->get_nodes($item,$node);
        return $nodes->item(0);

    }

    /**
     * add node to element
     * @param [domDocument Object]
     * @param [string]
     * @param [mixed]
     */
    function add_node($item,$node,$value){
        return $item->createElement($node, $value);
    }

    /**
     * get value of a node
     * @param  [domDocument Object]
     * @param  [string]
     * @return [mixed]
     */
    function get_node_value($item,$node){
    	if( $this->has_node($item,$node) ) {
        	return !is_null($item) ?  $item->getElementsByTagName($node)->item(0)->nodeValue : '';
    	}
    	return '';
    }

    /**
     * set node value and returns it
     * @param [domDocument Object]
     * @param [string]
     * @param [domDocument Object]
     */
    function set_node_value($item,$node,$value){
        $item->getElementsByTagName($node)->item(0)->nodeValue = $value;
        return $item;
    }

    /**
     * check if force geocode mode is enabled
     * @return boolean
     */
    function force_geocode(){
        return get_option('force_geocode') == 'on' ? true : false;
    }

    /**
     * check if geocode mode is enabled
     * @return boolean
     */
    function geocode_enabled(){
        return get_option('geo_enabled') == 'on' ? true : false;
    }

    /**
     * Returns the API url test or live
     * @return string
     */
	function get_api_url() {

		if($this->dev_mode == true)
			return $this->api_test_url;
		else
			return $this->api_url;
	}

	/**
	 * Gets API params from db and cache it in class property
	 * @return string
	 */
	function process_params() {
		global $options;
		$params = array();
		foreach($options as $key	=>	$value) {
			if(startsWith($key,'EAC_') ) {

				$params[str_replace('EAC_','',$key)] = $value;
			}
		}
		$this->params = array_filter(array_map(array($this,'array_to_csv'), $params));
	}

	/**
	 * Performs implode with ,
	 * @param  array $val
	 * @return string
	 */
	function array_to_csv($val) {
		if(is_array($val))
			return implode(',', array_filter($val) );
		else
			return $val;
	}

	/**
	 * Fetch data from api based on the command
	 * @param  string $command [description]
	 * @return [type]          [description]
	 */
	function fetch_from_api($command = 'GetLNArray', $format = 'xml',$args = array() ) {
		$supported_commands = array(
			'GetLNArray'				=>	'LN Array',
			'Search'					=>	'Search',
			'Display'					=>	'Display',
			'TopTenRecentSales'			=>	'Top 10 Recent Sales',
			'OFI'						=>	'Open for Inspections',
			'FindAgent'					=>	'Find an Agent',
			'GetPropTypes'				=>	'Get Property Types',
			'GetSuburbs'				=>	'Get Suburbs',
			'GetStreets'				=>	'Get Streets',
			'GetSLSPhone'				=>	'Get Salesperson Phone',
			'GetSLSMobile'				=>	'Get Salesperson Mobile',
			'GetSLSAndOfficeMail'		=>	'Get Salesperson and Office Emails',
			'GetOfficeProfile'			=>	'Get Office Profile',
			'GetOfficeSalespeople'		=>	'Get Office Salespeople',
			'GetFullSalespersonProfile'	=>	'Get Full Salesperson Profile',
			'FindMember'				=>	'Find Members'

		);

		if(!array_key_exists($command, $supported_commands))
			return;

		$args = empty($args) ? $this->params : $args;
		$params = array('Command'	=>	$command,'Format'	=>	$format) + $args;
		$this->logger->i('EAC REQUSEST',json_encode($params) );

		try {

			$response 		= feedsync_remote_post( $this->get_api_url(), array(), $params );
			$server_output 	= feedsync_remote_retrive_body( $response );

			if ($server_output === false) {
		        throw new Exception( 'No Response from server' );
		    }

		} catch(Exception $e) {

		    trigger_error( sprintf(
		        'Request failed with error #%d: %s',
		        $e->getCode(), $e->getMessage() ),
		        E_USER_ERROR );

		}
		return $server_output;

	}

	/**
	 * convert xml string response to xml object
	 * @param  [type] $server_output [description]
	 * @return [type]                [description]
	 */
	function response_to_xml($server_output) {

		if(!$server_output)
			return false;

		// replace utf-16 to utf-8 to avoid errors
		$server_output = preg_replace('/(<\?xml[^?]+?)utf-16/i', '$1utf-8', $server_output);
		$dom = new DOMDocument('1.0');
		libxml_use_internal_errors(true);
        $dom->preserveWhiteSpace = FALSE;
        $dom->loadXML($server_output);
        $dom->formatOutput = TRUE;
        return $dom;

	}

	/**
	 * Get listing numbers from API based on configurations
	 * @return [type] [description]
	 */
	function get_listing_numbers() {
		$lns = array();
		$prop_classes = array();
		if( isset($this->params['PROPCLASS']) ) {
			$prop_classes = explode(',',$this->params['PROPCLASS']);

			if( !empty($prop_classes) ) {
				foreach($prop_classes as $prop_class) {
					$params = $this->params;
					$params['PROPCLASS'] = $prop_class;
					if($params['PROPCLASS'] == 6) {
						unset($params['STAT']);
					}
					$lns[$prop_class] = json_decode($this->fetch_from_api('GetLNArray','json',$params));
				}
			}
		}
		return $lns;

	}

	/**
	 * Get listing details from API on the bases of listing number / unique_id
	 * @param  [type] $ln [description]
	 * @return [type]     [description]
	 */
	function get_listing_details($ln) {

		$params = array(
			'APIKEY'	=>	$this->params['APIKEY'],
			'Command'	=>	'Display' ,
			'LN'		=>	$ln
		);

		$response 		= feedsync_remote_post( $this->get_api_url(), array(), $params );
		$server_output 	= feedsync_remote_retrive_body( $response );
		return $server_output;

	}

	function clear_obselete_records() {
    	return $this->db->query("TRUNCATE TABLE feedsync");
	}

	function temp_store_records($listings) {
		$data = array();
		$remove_except = array();
    	foreach($listings as $listing) {
    		$mod_date = date("Y-m-d H:i:s",strtotime($listing->m));
    		$value = serialize($listing);
    		$data[] = "('$listing->l', '$mod_date', '$value')";
    		$remove_except[] = $listing->l;
    	}

    	$fields = implode(', ', array('unique_id','mod_date','value'));

    	/** INSERT LNArray in temp table  */
		$query = "INSERT INTO ".fsdb()->temp." ($fields) VALUES " . implode (', ', $data) ;

		$res = $this->db->query($query);
		/** REMOVE other listings from feedsync table  */
		$query_remove = "DELETE FROM ".fsdb()->listing." WHERE unique_id NOT IN (" . implode (', ', $remove_except)." )" ;
		$this->db->query($query_remove);
	}

	/**
	 * Save listing numbers in database
	 * @return [type] [description]
	 */
	function save_ln_in_db() {

		$eac_options = get_option_data('eac_cron');
    	/** fetch 100 listings at a time from temp table to process */
    	$listings = $this->db->get_Results("SELECT * FROM ".fsdb()->temp." LIMIT 100");

    	/** if no listings in temp table, fetch lnarray from API & store in temp table */
    	if( empty($listings) &&
    		(!isset($eac_options['list_number_fetched']) || $eac_options['list_number_fetched']  != true) ) {


			$servers_output = $this->get_listing_numbers();
			$lnarray = array();
			foreach($servers_output as $server_output ) {

				if($server_output->Success == True) {
					$lnarray = array_merge($lnarray,$server_output->Results);
				}
			}
			$lnarray = array_map("unserialize", array_unique(array_map("serialize", $lnarray)));
			if( !empty($lnarray) ) {
	        	/** store lnarray in temp table and delete other listings from feedsync table which are not part of temp table */
	        	$this->temp_store_records($lnarray);
	        }

	        $status = array(
	        	'list_number_fetched' => true,
	    	);

	        update_option_data('eac_cron',$status);

	        echo json_encode(
	            array(
	                'status'    =>  'success',
	                'message'   =>  '<strong> Listing numbers fetched : <br> Please do not navigate from this page <br> Listing Details processing will follow</strong><br>',
	                'geocoded'  =>  '',
	                'buffer'    =>  'processing'
	            )
	        );
	        die;

    	}

        $log_counter = array();

        /** loop through all listings in temp table & compare it with listings in feedsnc table */
    	if( !empty($listings) ) {

    		$processed_lns = array();
        	foreach($listings as $listing) {

        		$processed_lns[] = $listing->unique_id;
        		$lsdata 	= unserialize($listing->value);
        		$db_data 	= array();
        		$db_data['unique_id'] 		= $listing->unique_id;
        		$db_data['mod_date']  		= $listing->mod_date;


        		$propclasses = array(
					'1'			=>	__('residential','epl-feedsync'),
					'2'			=>	__('land','epl-feedsync'),
					'3'			=>	__('rural','epl-feedsync'),
					'4'			=>	__('business','epl-feedsync'),
					'5'			=>	__('commercial','epl-feedsync'),
					'6'			=>	__('rental','epl-feedsync')
				);
        		$db_data['type']            = $lsdata->pc;
        		$db_data['type']			= isset($propclasses[$db_data['type']]) ?
        										$propclasses[$db_data['type']] : 'property';
        		$db_data        			=  array_map(array($this->db,'escape'), $db_data);

        		/** check if listing exists already **/
                $exists = $this->db->get_row("SELECT * FROM ".fsdb()->listing." where unique_id = '{$db_data['unique_id']}' ");

                if( !empty($exists) ) {

                	// listing already exists
                	if(  strtotime($exists->mod_date) < strtotime($db_data['mod_date']) ) {
                		/**
                		 * updated content in API .. remove old data
                		 * remove all those fields based on which we are checking
                		 * which listing needs processing in get_limited_listing() method
                		 */
                		$query = "UPDATE ".fsdb()->listing." SET
				            status          = '',
				            xml             = '',
				            mod_date        = '{$db_data['mod_date']}',
				            address         = ''
				            WHERE unique_id = '{$db_data['unique_id']}'
				        ";
				        $this->db->query($query);
				        $log_counter['updated'][] = $db_data['unique_id'];
                	} else {

                		$log_counter['skipped'][] = $db_data['unique_id'];

                		/** show this message only for cron - for testing */
                		if($this->cron_mode) {

                			$this_listing_status = array(
	                			'status'	=>	'skip',
	                			'message'	=>	$db_data['unique_id'].' already contain latest data, skipping.'
	            			);

	            			echo "<br>".json_encode($this_listing_status)."<br>";
                		}
                	}


                } else {

                	// fresh listing

			        $db_data['agent_id']        = '';
			        $db_data['status']          = '';
			        $db_data['xml']             = '';
			        $db_data['xml']             = '';
			        $db_data['geocode']         = '';
			        $db_data['street']          = '';
			        $db_data['suburb']          = '';
			        $db_data['state']           = '';
			        $db_data['postcode']        = '';
			        $db_data['country']         = '';
			        $db_data['address']         = '';
			        $db_data['address']     	= '';
		            $db_data['street']      	= '';
		            $db_data['suburb']      	= '';
		            $db_data['state']       	= '';
		            $db_data['postcode']    	= '';
		            $db_data['country']     	= '';
		            $db_data['feedsync_unique_id'] = '';

                	$query = "INSERT INTO
			        ".fsdb()->listing." (type, agent_id,unique_id,feedsync_unique_id, mod_date, status,xml,firstdate,street,suburb,state,postcode,country,geocode,address)
			        VALUES (
			            '{$db_data['type']}',
			            '{$db_data['agent_id']}',
			            '{$db_data['unique_id']}',
			            '{$db_data['feedsync_unique_id']}',
			            '{$db_data['mod_date']}',
			            '{$db_data['status']}',
			            '{$db_data['xml']}',
			            '{$db_data['mod_date']}',
			            '{$db_data['street']}',
			            '{$db_data['suburb']}',
			            '{$db_data['state']}',
			            '{$db_data['postcode']}',
			            '{$db_data['country']}',
			            '{$db_data['geocode']}',
			            '{$db_data['address']}'
			        )";
			        $this->db->query($query);
			        $log_counter['inserted'][] = $db_data['unique_id'];
                }

        	}

        	/** REMOVE Listings from temp table, once processed  */
			$query_remove = "DELETE FROM ".fsdb()->temp." WHERE unique_id IN (" . implode (', ', $processed_lns)." )" ;
			$this->db->query($query_remove);

    	}

    	/** mark listing number updated when we have fetched LNArray from API and compared all listings in feedsync table */
        if( empty($listings) && isset($eac_options['list_number_fetched']) && $eac_options['list_number_fetched']  == true ) {


        	$status = array(
	        	'list_number_fetched' => true,
	        	'list_number_updated' => true,
	    	);

	        update_option_data('eac_cron',$status);
        }

        echo json_encode(
            array(
                'status'    =>  'success',
                'message'   =>  '<strong> Listing numbers compared : <br> Please dont navigate from this page <br> Listing Details processing will follow</strong><br>',
                'log'		=>	$log_counter,
                'geocoded'  =>  '',
                'buffer'    =>  'processing'
            )
        );
        die;
	}

	/**
	 * Init the import process from API to DB
	 * @return [type] [description]
	 */
	function process($args) {

		if($this->cron_mode) {
            $action = isset($args['action']) ? $args['action'] : '';
            if($action != '') {
                $this->process_cron($action);
            }
        } else {
        	$eac_options = get_option_data('eac_cron');
			/** fetch listing numbers, if not already fetched **/
			if( !isset($eac_options['list_number_updated']) || $eac_options['list_number_updated'] != true) {
				$this->save_ln_in_db();
			} else {
				/** fetch details for each listings **/
				$this->save_details();
			}
        }


	}

	function trigger_cron_job($status) {
		return update_option('eac_cron_trigged',$status);
	}

	/**
	 * Init the import process from API to DB via Cron
	 */
	function process_cron($action) {
		global $options;
		switch($action) {
			case 'trigger':
				$trigger_status = get_option('eac_cron_trigged');
				if($trigger_status) {
					echo json_encode( array('status'	=>	'fail','message'	=>	__('Cron is already triggered, Please wait for processing to complete','feedsync') ) );
					die;
				}
				$status = $this->trigger_cron_job(true);
				if($status) {
					echo json_encode( array('status'	=>	'success','message'	=>	__('Job Process Triggered, Processing will follow','feedsync') ) );
					die;
				}
			break;

			case 'process':
				$trigger_status = get_option('eac_cron_trigged');
				if(!$trigger_status) {
					echo json_encode( array('status'	=>	'fail','message'	=>	__('No Cron Job Process Triggered or all processing have been completed','feedsync') ) );
					die;
				}
				$eac_options = get_option_data('eac_cron');
				/** fetch listing numbers, if not already fetched **/
				if( !isset($eac_options['list_number_updated']) || $eac_options['list_number_updated'] != true) {
					$this->save_ln_in_db();
				} else {
					/** fetch details for each listings **/
					$this->save_details();
				}
			break;
		}

	}

	/**
	 * Performs necessary changes in nodes like change of node names, node values
	 * also adds some missing nodes in each of the listing nodes
	 * @param  [type] $dom            [description]
	 * @param  [type] $listing_object [description]
	 * @return [type]                 [description]
	 */
	function process_single_xml($dom,$listing_object) {

		$dom = $this->update_node_values($dom,$listing_object);
		$dom = $this->add_images_node($dom,$listing_object);
		$dom = $this->add_feedsync_node($dom,$listing_object);
		$dom = $this->add_firstdate_and_moddate_node($dom,$listing_object);
		$dom = $this->add_ofi_node($dom,$listing_object);
		$dom = $this->add_fui_node($dom,$listing_object);
		//echo $dom->saveXML($dom->getElementsByTagName($listing_object->type)->item(0)); die;
		return $dom;
	}

	/**
	 * Update node values to support epl
	 * @param  [type] $dom            [description]
	 * @param  [type] $listing_object [description]
	 * @return [type]                 [description]
	 */
	function update_node_values($dom,$listing_object) {


		$status_array = array(
			'CR'			=>	__('current','epl-feedsync'),
			'SOLD'			=>	__('sold','epl-feedsync'),
			'WD'			=>	__('withdrawn','epl-feedsync'),
			'EX'			=>	__('expired','epl-feedsync'),
			'CANC'			=>	__('cancelled','epl-feedsync'),
			'AVAIL'			=>	__('current','epl-feedsync'),
			'LEASED'		=>	__('leased','epl-feedsync'),
			'SOLD'			=>	__('sold','epl-feedsync'),
			'NLM'			=>	__('offmarket','epl-feedsync'),
		);

		$listing 	= $dom->getElementsByTagName('Property')->item(0);
		$status 	= $this->get_node_value($listing,'STAT');
		$r_status 	= $this->get_node_value($listing,'RSTAT');
		if($r_status != '')
			$status = $r_status;
		$status = isset($status_array[$status]) ? $status_array[$status] : $status;
		$ps  = $this->add_node($dom,'property_status',$status);
		$listing->appendChild($ps);
		$listing->setAttribute('status',$status);

		$property_type = array(
			'A'		=>	'Apartment',
			'BLK'		=>	'Block of Units' ,
			'BH'		=>	'Backpacker-Hostel',
			'BNB'		=>	'Bed and Breakfast',
			'C/H'		=>	'Cluster Homes' ,
			'CG'		=>	'Campground',
			'CHP'		=>	'Caravan - Holiday Park',
			'CR'		=>	'Country Residence',
			'DO'		=>	'Dual Occupancy',
			'D'		=>	'Duplex',
			'F'		=>	'Flat',
			'FS'		=>	'Farm Stay',
			'H'		=>	'House',
			'HB'		=>	'House Boat',
			'HM'		=>	'Hotel/Motel',
			'L'		=>	'Lodge',
			'RELOC'		=>	'Relocatable Home',
			'RET'		=>	'Retirement',
			'R'		=>	'Resort',
			'S'		=>	'Semi-detached',
			'SA'		=>	'Serviced Apartment',
			'STU'		=>	'Studio',
			'STUD'		=>	'Studio',
			'T'		=>	'Terrace',
			'TWN'		=>	'Townhouse',
			'U'		=>	'Unit',
			'V'		=>	'Villa',
			'O'		=>	'Other'
		);
		$proptype 	= $this->get_node_value($listing,'PROPTYPE');
		$proptype   = isset($property_type[$proptype]) ? $property_type[$proptype] : $proptype;

		$this->set_node_value($listing,'PROPTYPE',$proptype);

		/** @var Rent type */
		$renttypes = array(
						'RES'	=>	'Residential',
						'HOL'	=>	'Holiday',
						'COMM'	=>	'Commercial'
					);

		$renttype 	= $this->get_node_value($listing,'RENTTYPE');
		$renttype   	= isset($renttypes[$renttype]) ? $renttypes[$renttype] : 'Residential';

		$this->set_node_value($listing,'RENTTYPE',$renttype);

		/** @var Property class */
		$propclass = array(
			'1'			=>	__('property','epl-feedsync'),
			'2'			=>	__('land','epl-feedsync'),
			'3'			=>	__('rural','epl-feedsync'),
			'4'			=>	__('business','epl-feedsync'),
			'5'			=>	__('commercial','epl-feedsync'),
			'6'			=>	__('rental','epl-feedsync')
		);

		$item_class 	= $this->get_node_value($listing,'PROPCLASS');
		$item_class   	= isset($propclass[$item_class]) ? $propclass[$item_class] : 'property';

		if($renttype == 'Commercial' && $item_class == 'rental') {
			$item_class   	= 'commercial';
		}
		$this->set_node_value($listing,'PROPCLASS',$item_class);

		/** property com type node */
		if($item_class == 'commercial') {

			if($renttype == 'Commercial') {
				$com_listing_type   	= 'lease';
			} else {
				$com_listing_type   	= 'sale';
			}
			$cl = $this->add_node($dom,'property_com_listing_type',$com_listing_type);
			$listing->appendChild($cl);
		}
		

		/** date available format - required format : 2016-04-20 input format : 28/02/2017 12:00:00 AM */
		$avail_date 	= $this->get_node_value($listing,'AVAILDATE');

		if( $avail_date != ''){
			$avail_date 	= current( explode(' ',$avail_date) );
			$avail_date 	= str_replace('/', '-', $avail_date);
			$avail_date 	= date('Y-m-d', strtotime($avail_date) );
			//$this->set_node_value($listing,'AVAILDATE',$avail_date);
			$ad = $this->add_node($dom,'date_available',$avail_date);
			$listing->appendChild($ad);

		}

		/** add auction node */
		$auction_date 	= $this->get_node_value($listing,'AUCDATE');
		$auction_time 	= $this->get_node_value($listing,'AUC_TIME');

		if( $auction_date != '' && $auction_time != ''){

			$auction_date 	= str_replace('/', '-', $auction_date);
			$auction_date 	= date('Y-m-d', strtotime($auction_date) );
			$auction_time 	= date('H:i', strtotime($auction_time) );
			$auction 		= $auction_date.'T'.$auction_time;
			$auc_node 		= $this->add_node($dom,'auction_date',$auction);
			$listing->appendChild($auc_node);

		}

		/** floor plan url */
		$floor_plan 	= $this->get_node_value($listing,'FLOORPLAN');
		if($floor_plan == 'true'){
			$ln = $this->get_node_value($listing,'LN');
			$floor_plan = 'http://images.realestateworld.com.au/floorplans/' . $ln . '.gif';
			$this->set_node_value($listing,'FLOORPLAN',$floor_plan);
		}else{
			$this->set_node_value($listing,'FLOORPLAN','');
		}

		/** land dimensions nodes */
		$land_dimens 	= $this->get_node_value($listing,'LAND_DIMENS');
		if( $land_dimens != '' ){
			$land_array	= explode( " " , $land_dimens );

			$land_size_node = $this->add_node($dom,'property_land_size',$land_array[0]);
			$land_unit_node = $this->add_node($dom,'property_land_unit',$land_array[1]);
			$listing->appendChild($land_size_node);
			$listing->appendChild($land_unit_node);
		}

		/** map nodes */
		$eac_node_mapping 	= eac_node_mapping();

		foreach($eac_node_mapping as $node_key 	=>	$mapping){

			$new_key			= 'property'.ucfirst( strtolower($node_key) );
			$node_val 			= $this->get_node_value($listing,$node_key);
			$mapped_node_val 	= isset($mapping[$node_val]) ? $mapping[$node_val] : $node_val;
			$complex_node 		= $this->add_node($dom,$new_key,$mapped_node_val);
			$listing->appendChild($complex_node);

			// if( !empty($mapping_nodes) ){

			// 	foreach($mapping_nodes as $mapping_node){
			// 		$node_val 			= $this->get_node_value($mapping_node,$node_key);
			// 		$mapped_node_val 	= isset($mapping[$node_val]) ? $mapping[$node_val] : $node_val;
			// 		$this->set_node_value($listing,$node_key,$mapped_node_val);
			// 	}
			// }
		}

		if ( get_post_meta($listing_object->id,'fav_listing',true) == 'yes' ) {

            $fav             = $this->add_node($dom,'feedsyncFeaturedListing','yes');
            $listing->appendChild($fav);
        }
        
		return $dom;
	}

	/**
	 * Add Image node to the the listing node
	 * @param [type] $dom            [description]
	 * @param [type] $listing_object [description]
	 */
	function add_images_node($dom,$listing_object) {

		$listing = $dom->getElementsByTagName('Property')->item(0);
		$photo_node  = $this->add_node($dom,'images','');
		$listing->appendChild($photo_node);
		$photo_count = $this->get_node_value($listing,'PHOTO_CNT');
		$folder = substr($listing_object->unique_id, -2);

		$url = 'http://images.realestateworld.com.au/photos/'.$folder.'/'.$listing_object->unique_id.'.jpg';

		$this_node = $this->add_node($dom,'img','');
		$this_node->setAttribute('url',$url);
		$photo_node->appendChild($this_node);

		for($i=1;$i<$photo_count;$i++) {
			$this_node = $this->add_node($dom,'img','');
			$url = 'http://images.realestateworld.com.au/photos/'.$folder.'/'.$listing_object->unique_id.'_'.$i.'.jpg';
			$this_node->setAttribute('url',$url);
			$photo_node->appendChild($this_node);
		}
		return $dom;
	}

	/**
	 * Add Feedsync Unique ID Node
	 * @param [type] $dom            [description]
	 * @param [type] $listing_object [description]
	 */
	function add_fui_node($dom,$listing_object = null) {

		$listing = $dom->getElementsByTagName('Property')->item(0);

		/** Feedsync Unique ID ( Unique ID + Agent ID ) */

        $feedsync_unique_id = $this->get_node_value($listing,'LN');;

        if( $this->has_node($listing,'AGT_ID') ) {

            $feedsync_unique_id = $this->get_node_value($listing,'AGT_ID').'-'.$feedsync_unique_id;

        }

         // if node not already exists, add it
        if( ! $this->has_node($listing,'feedsyncUniqueID') ) {

            // if node not already exists, add it

            $element = $this->add_node($dom,'feedsyncUniqueID',$feedsync_unique_id);
            $listing->appendChild($element);

        } else {
            // if node already exists, just update the value
            $listing = $this->set_node_value($listing,'feedsyncUniqueID',$feedsync_unique_id);
        }

		return $dom;
	}

	/**
	 * Add Open For Inspection (OFI) Node
	 * @param [type] $dom            [description]
	 * @param [type] $listing_object [description]
	 */
	function add_ofi_node($dom,$listing_object) {

		$listing 	 = $dom->getElementsByTagName('Property')->item(0);
		$status 	 = $this->get_node_value($listing,'property_status');

		/** only fetch OFIs for current listings */
		if($status != 'current') {
			return $dom;
		}

		/** Fetch OFI dates from API */
		$params = array(
			'APIKEY'	=>	$this->params['APIKEY'],
			'Command'	=>	'OFI' ,
			'LN'		=>	$listing_object->unique_id
		);

		$response 		= feedsync_remote_post( $this->get_api_url(), array(), $params );
		$ofis 			= feedsync_remote_retrive_body( $response );

		$ofi_dom = $this->response_to_xml($ofis);

		if($ofi_dom) {
			/** only process OFIs if API has returned OFIs */
			if( $this->get_node_value($ofi_dom,'Success') == 'True' &&
				$this->get_node_value($ofi_dom,'Count') > 0) {

				$ofi_list = $this->get_nodes($ofi_dom,'OFI');
				if( !empty($ofi_list) ) {

					$ofi_node  = $this->add_node($dom,'inspection_times','');
					$listing->appendChild($ofi_node);

					foreach($ofi_list as $ofi_item) {

						$ofi_date 	= $this->get_node_value($ofi_item,'OFI_DATE');
						$ofi_tstart = $this->get_node_value($ofi_item,'DT_START');
						$ofi_tstart = preg_replace('/\s+/', '', strtolower($ofi_tstart));
						$ofi_tend 	= $this->get_node_value($ofi_item,'DT_END');
						$ofi_tend = preg_replace('/\s+/', '', strtolower($ofi_tend));
						$ofi_dt = date('d-M-Y',strtotime(str_replace('/','-',$ofi_date))).' '.$ofi_tstart.' to '.$ofi_tend;
						$this_node = $this->add_node($dom,'inspection_time',$ofi_dt);
						$ofi_node->appendChild($this_node);
					}
				}
			}
		}
		return $dom;
	}

	/**
	 * Add feedsync node to the listing node
	 * @param [type] $dom            [description]
	 * @param [type] $listing_object [description]
	 */
	function add_feedsync_node($dom,$listing_object) {

		if( $this->geocode_enabled() )
			$dom = $this->geocode($dom);

		return $dom;
	}

	/**
	 * Add firstdate node & mod date attribute to listing node
	 * @param [type] $dom            [description]
	 * @param [type] $listing_object [description]
	 */
	function add_firstdate_and_moddate_node($dom,$listing_object) {

		$listing 	= $dom->getElementsByTagName('Property')->item(0);
		$fd  		= $this->add_node($dom,'firstDate',$listing_object->firstdate);
		$listing->appendChild($fd);
		$listing->setAttribute('modTime',$listing_object->mod_date);

		if( $this->has_node($listing,'PHOTO_DATE') ) {
			$mod_date 	= $this->get_node_value($listing,'PHOTO_DATE');
			$mod_date	= date("Y-m-d H:i:s",strtotime($mod_date));
			//$this->logger->i('test mod time - new',$mod_date );
		} else {
			$mod_date	= $listing_object->mod_date;
		}
		
		$md  		= $this->add_node($dom,'feedsyncImageModtime',$mod_date);
		$listing->appendChild($md);

		return $dom;
	}

	/**
     * process image modtime
     * @return
     */
    function process_image_modtime(){

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where 1=1 ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {
                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->xpath = new DOMXPath($this->xmlFile);
                
                $dom_el 	= $this->xmlFile->getElementsByTagName('Property')->item(0);

				if( $this->has_node($dom_el,'PHOTO_DATE') ) {
					$mod_date 	= $this->get_node_value($dom_el,'PHOTO_DATE');
					$mod_date	= date("Y-m-d H:i:s",strtotime($mod_date));
				} else {
					$mod_date	= $listing->mod_date;
				}

				if( $this->has_node($dom_el,'feedsyncImageModtime') ) {
					$mod_date 	= $this->set_node_value($dom_el,'PHOTO_DATE');
					$this->set_node_value($dom_el,'feedsyncImageModtime',$mod_date);
				} else {
					$md  		= $this->add_node($this->xmlFile,'feedsyncImageModtime',$mod_date);
					$dom_el->appendChild($md);
				}

                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}'
                                WHERE id        = '{$listing->id}'
                            ";

               $this->db->query($query);

            }

        }
    }

	/**
	 * Returns list of listing numbers that should be processed next
	 * @return
	 */
	function get_limited_listing(){

		$query 			= "SELECT id,unique_id,type,mod_date,firstdate FROM ".fsdb()->listing."
							WHERE address = '' AND xml = '' AND status = ''
							ORDER BY id ASC
							LIMIT $this->processing_limit";
		return $this->db->get_results($query);

	}

	/**
	 * Fetches Details of each listing on the basis of Listing number from API
	 * and updates details in database
	 * @return [type] [description]
	 */
	function save_details() {

		$eac_options 	= get_option_data('eac_cron');
		$listings 		= $this->get_limited_listing();
		if( empty($listings) ) {

			/**
			 * If no listings left to process, re init the process
			 * starting with fetching listing numbers
			 */

			$status = array(
	        	'list_number_updated' 	=> false,
	        	'list_number_fetched'	=>	false
	    	);

	        update_option_data('eac_cron',$status);

        	/** for cron. mark false so that cron can be trigerred again */
        	$this->trigger_cron_job(false);

	        die( json_encode(array('status' =>  'success', 'message'    =>  'All listings have been processed.', 'buffer'   =>  'complete')) );

		}
		//print_exit($listings);
		$log_counter = array();
		foreach($listings as $listing) {

			$server_output = $this->get_listing_details($listing->unique_id);

			$dom = $this->response_to_xml($server_output);

			if(!$dom) {
				return false;
			}

			/** only process listing details if API has returned listing details */
			if( $this->get_node_value($dom,'Success') == 'True' &&
				$this->get_node_value($dom,'Count') > 0) {



				$dom 		= $this->process_single_xml($dom,$listing);
				$DOMELEM 	= $dom->getElementsByTagName('Property')->item(0);
				$xml 		= $dom->saveXML($dom->getElementsByTagName('Property')->item(0));
				$street_name = $this->get_node_value($DOMELEM,'STNUM').' '.
							   $this->get_node_value($DOMELEM,'STNME').' '.
							   $this->get_node_value($DOMELEM,'STTYP');

			   $listing_type = $this->get_node_value($DOMELEM,'PROPCLASS') == 'property' ? 'residential' : $this->get_node_value($DOMELEM,'PROPCLASS');

				$db_data['unique_id']          = $this->get_node_value($DOMELEM,'LN');
				$db_data['feedsync_unique_id'] = $this->get_node_value($DOMELEM,'feedsyncUniqueID');
				$db_data['agent_id']           = $this->get_node_value($DOMELEM,'AGT_ID');
				$db_data['status']             = $DOMELEM->getAttribute('status');
				$db_data['xml']                = $xml;
				$db_data['geocode']            = $this->get_node_value($DOMELEM,'feedsyncGeocode');
				$db_data['street']             = $street_name;
				$db_data['suburb']             = $this->get_node_value($DOMELEM,'SUB');
				$db_data['state']              = $this->get_node_value($DOMELEM,'STATE');
				$db_data['postcode']           = $this->get_node_value($DOMELEM,'PCD');
				$db_data['country']            = 'AU';
				$db_data['type']               = $listing_type;
		        $address = $street_name.', '.$db_data['suburb'].', '.$db_data['postcode'].', '.$db_data['state'].', '.$db_data['country'];

		        $db_data['address']         = $address;
		        $db_data        	=   array_map(array($this->db,'escape'), $db_data);

		        /** process agents */
		        $this->eac_agents($db_data['agent_id']);

			} else {

				/** mark status invalid & prevent it from coming in process queue again */
				$db_data['unique_id']          = $listing->unique_id;
				$db_data['feedsync_unique_id'] = $listing->feedsync_unique_id;
				$db_data['type']               = $listing->type;
				$db_data['agent_id']           = '';
				$db_data['status']             = 'invalid';
				$db_data['xml']                = '';
				$db_data['geocode']            = '';
				$db_data['street']             = '';
				$db_data['suburb']             = '';
				$db_data['state']              = '';
				$db_data['postcode']           = '';
				$db_data['country']            = '';
				$db_data['address']            = '';
			}

	        /** update with new data from api */
        	$query = "UPDATE ".fsdb()->listing." SET
				agent_id           = '{$db_data['agent_id']}',
				feedsync_unique_id = '{$db_data['feedsync_unique_id']}',
				status             = '{$db_data['status']}',
				xml                = '{$db_data['xml']}',
				geocode            = '{$db_data['geocode']}',
				address            = '{$db_data['address']}',
				street             = '{$db_data['street']}',
				suburb             = '{$db_data['suburb']}',
				state              = '{$db_data['state']}',
				postcode           = '{$db_data['postcode']}',
				country            = '{$db_data['country']}',
				type               = '{$db_data['type']}'
				WHERE unique_id    = '{$db_data['unique_id']}'
	        ";

	        $status = $this->db->query($query);
	        $log_counter[$db_data['feedsync_unique_id']] = $status;
		}

		echo json_encode(
            array(
                'status'    =>  'success',
                'message'   =>  '<strong> Listing details updated </strong><br><strong>Unique IDS Processed : </strong>'.implode( ', ', array_keys($log_counter) ).'<br>
                <strong> Processing .. Please dont navigate from this page </strong>',
                'geocoded'  =>  '',
                'buffer'    =>  'processing'
            )
        );
        die;

	}

	/**
     * process missing feedsync_unique_id < 3.2
     * @since 3.2
     * @return
     */
    function upgrade_for_version_3_2(){

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where 1=1 AND feedsync_unique_id  = '' ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {

                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->xpath = new DOMXPath($this->xmlFile);
                $this->add_fui_node($this->xmlFile);
                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                    'feedsync_unique_id'    =>  $this->get_node_value($this->xmlFile->documentElement,'feedsyncUniqueID')
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}',
                                feedsync_unique_id              = '{$db_data['feedsync_unique_id']}'
                                WHERE id        = '{$listing->id}'
                            ";

               $this->db->query($query);


            }

        }
    }

	function eac_agents($agent_id = 0) {

		$params = $this->params;

		if( isset($this->params['AGT_ID']) && $this->params['AGT_ID'] != ''){
			self::$agent_process_mode = 'once';
		}

		if(self::$agent_process_mode == 'default') {

			$params['AGT_ID'] = $agent_id;
			$this->process_eac_agents($params);
		}

		if(self::$agent_process_mode == 'once') {

			if(self::$agent_processed_once == false) {
				$this->process_eac_agents($params);
				self::$agent_processed_once = true;
			}
		}

	}

	function process_eac_agents($params) {
		$agents = $this->fetch_from_api('GetOfficeSalespeople','xml',$params);
		$agents_dom = $this->response_to_xml($agents);
		if($agents_dom) {
			if( $this->get_node_value($agents_dom,'Success') == 'True' &&
				$this->get_node_value($agents_dom,'Count') > 0) {

				$persons = $this->get_nodes($agents_dom,'Person');
				if( !empty($persons) ) {

					foreach($persons as $person) {

						/** init values **/
		                $data_agent                 = array();
		                $data_agent['agent_id']     = $this->get_node_value($person,'SLSNMB');
		                $data_agent['office_id']    = $params['AGT_ID'];
		                $data_agent['name']         = $this->get_node_value($person,'SLSNAM').' '.$this->get_node_value($person,'SLSSUR');
		                $data_agent['telephone']    = $this->get_node_value($person,'SLSFON');
		                $data_agent['email']        = $this->get_node_value($person,'SLSEML');
		                $data_agent['xml']        	= $agents_dom->saveXML($person);
		                $data_agent         =   array_map(array($this->db,'escape'), $data_agent);

		                 /** check if listing agent exists already **/
		                $agent_exists = $this->db->get_row("SELECT * FROM ".fsdb()->agent." where name = '{$data_agent['name']}' ");

		                if( empty($agent_exists) ) {

		                    /** insert new data **/
		                    $query = "INSERT INTO
		                    ".fsdb()->agent." (office_id,name,telephone,email,xml)
		                    VALUES (
		                        '{$data_agent['office_id']}',
		                        '{$data_agent['name']}',
		                        '{$data_agent['telephone']}',
		                        '{$data_agent['email']}',
		                        '{$data_agent['xml']}'
		                    )";

		                    $this->db->query($query);
		                }

					}
				}
			}
		}
	}

	function geocode_from_lat_long_node($listing) {

		$lat 		= $this->get_node_value($listing,'LATITUDE');
		$long 		= $this->get_node_value($listing,'LONGITUDE');
		if(isset($lat) && isset($long) ) {
			$coordinates = $lat.','.$long;
		} else {
			$coordinates = '';
		}
		return $coordinates;
	}

	/**
     * Geocode address from google geocode API
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
	function geocode_from_google($listing) {

		$address = $this->get_address($listing);
        $address           = urlencode(strtolower($address));
        /** try to get lat & long from google **/
        if( trim($address) != '') {

            $query_address  = trim($address);
            $googleapiurl = "https://maps.google.com/maps/api/geocode/json?address=$query_address&sensor=false";

            if( get_option('feedsync_google_api_key') != '' ) {
				$googleapiurl = $googleapiurl.'&key='.get_option('feedsync_google_api_key');
			}

			$geo_response 	= feedsync_remote_get( $googleapiurl );
    		$geocode 		= feedsync_remote_retrive_body( $geo_response );
            $output         = json_decode($geocode);

            /** if address is validated & google returned response **/
            if( !empty($output->results) && $output->status == 'OK' ) {

                $lat            = $output->results[0]->geometry->location->lat;
                $long           = $output->results[0]->geometry->location->lng;
            }
        }

        if(isset($lat) && isset($long) ) {
			$coordinates = $lat.','.$long;
		} else {
			$coordinates = '';
		}
		return $coordinates;
	}

    /**
     * Geocode the listing item :)
     * @param  [domDocument Object]
     * @return [domDocument Object]
     */
    function geocode($dom,$process_missing = false){

    	$listing 	= $dom->getElementsByTagName('Property')->item(0);
    	$coordinates = '';
    	 $this->geocoded_addreses_list = "\n";

    	 /** add feedsyncGeocode node if not already there or if force geocode mode is on **/
        if( !$this->has_node($listing,'feedsyncGeocode') || $this->get_node_value($listing,'feedsyncGeocode') == '' || $this->force_geocode() ) {

        	// if listing has latitude node, extract value from it and save it to feedsyncGeocode node
	    	if( $this->has_node($listing,'LATITUDE') ) {
				$coordinates = $this->geocode_from_lat_long_node($listing);
			} else {

					// if item doesnt have geocode node, geocode it
           			 if( $this->geocode_enabled() || $this->force_geocode()  || $process_missing )
						$coordinates = $this->geocode_from_google($listing);
			}
		} else {
			 
       		$coordinates = $this->get_node_value($listing,'feedsyncGeocode');
        }

		$this->coord              = $coordinates;
        return  $this->update_feedsync_node($dom,$coordinates);
    }

    /**
     * Regenerate coordinates for listing.
     * @return json mixed
     * @since 3.4.0
     */
    function regenerate_coordinates() {
        
        $id = intval($_POST['id']);

        if($id <= 0)
            return;

        $alllistings = fsdb()->get_results("select * from ".fsdb()->listing." where id = {$id} ");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {

                $this->xmlFile = new DOMDocument('1.0', 'UTF-8');
                $this->xmlFile->preserveWhiteSpace = FALSE;
                $this->xmlFile->loadXML($listing->xml);
                $this->xmlFile->formatOutput = TRUE;
                $this->coord    = '';
                $this->xpath    = new DOMXPath($this->xmlFile);
                $listingXml     = $this->geocode($this->xmlFile->documentElement,true);
                $newxml         = $this->xmlFile->saveXML($this->xmlFile->documentElement);

                $db_data   = array(
                    'xml'       =>  $newxml,
                    'geocode'   =>  $this->coord
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}',
                                geocode         = '{$db_data['geocode']}'
                                WHERE id        = '{$listing->id}'
                            ";
                $this->db->query($query);

                if( !empty( $this->coord ) ) {
                    $geocode_status = json_encode(
                        array(
                            'status'        =>  'success',
                            'coordinates'   =>  $this->coord,
                            'buffer'        =>  'complete'
                        )
                    );
                } else {
                    $geocode_status = json_encode(
                        array(
                            'status'        =>  'fail',
                            'coordinates'   =>  $this->coord,
                            'buffer'        =>  'complete'
                        )
                    );
                }

                die($geocode_status);

            }

        }  else {

            die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Geocode process complete',
                        'buffer'    =>  'complete'
                    )
                )
            );
        }
    }

    /**
     * process missing geocode of existing listings in db
     * @return domDocument Object
     */
    function process_missing_geocode(){

        // if force geocode mode is on delete all coordinates
        if( $this->force_geocode()  && (!isset($_COOKIE['coordinates_resetted']) || $_COOKIE['coordinates_resetted'] != 1) ) {
            $this->db->query("update feedsync set geocode = '' where 1 = 1");
            setcookie("coordinates_resetted", 1);
        }

        $alllistings = $this->db->get_results("select * from ".fsdb()->listing." where geocode = '' AND address != '' LIMIT 1");

        if( !empty($alllistings) ) {

            foreach($alllistings as $listing) {
                $listingXml = new DOMDocument;
                $listingXml->preserveWhiteSpace = FALSE;
                $listingXml->loadXML($listing->xml);
                $listingXml->formatOutput = TRUE;
                $this->coord    = '';
                $listingXml     = $this->geocode($listingXml,true);
                $newxml         = $listingXml->saveXML($listingXml->documentElement);
                if($this->coord == '') {
                    $this->coord          = 'NULL';
                }
                $db_data   = array(
                    'xml'       =>  $newxml,
                    'geocode'   =>  $this->coord
                );

                $db_data    =   array_map(array($this->db,'escape'), $db_data);
                $query = "UPDATE ".fsdb()->listing." SET
                                xml             = '{$db_data['xml']}',
                                geocode         = '{$db_data['geocode']}'
                                WHERE id        = '{$listing->id}'
                            ";
                $this->db->query($query);
                die(
                    json_encode(
                        array(
                            'status'    =>  'success',
                            'message'   =>  '<strong>Geocode Status</strong> <br>
                                                    Address : <em>'.$this->get_address($listingXml).'</em> <br>
                                                    Geocode : <em>'.$this->coord.'</em> <br>',
                            'buffer'    =>  'processing'
                        )
                    )
                );

            }

        }  else {

            die(
                json_encode(
                    array(
                        'status'    =>  'success',
                        'message'   =>  'Geocode process complete',
                        'buffer'    =>  'complete'
                    )
                )
            );
        }
    }

    /**
     * update feedsync node of listing
     * @param  [domDocument Object]
     * @param  [string]
     * @return [domDocument Object]
     */
    function update_feedsync_node($item,$coord) {

        $listing 	= $item->getElementsByTagName('Property')->item(0);
        if( ! $this->has_node($item,'feedsyncGeocode') ) {
            // if node not already exists, add it

            $element = $this->add_node($item,'feedsyncGeocode',$coord);
            $listing->appendChild($element);
        } else {
            // if node already exists, just update the value
            $listing = $this->set_node_value($listing,'feedsyncGeocode',$coord);
        }

        // return item for further processing;
        return $item;

    }

    /**
     * get address from a listing element
     * @param  [domDocument Object]
     * @param  boolean
     * @return [mixed]
     */
    function get_address($listing,$comma_seperated = true) {

    	$db_data = array();
    	$street_name = $this->get_node_value($listing,'STNUM').' '.
					   $this->get_node_value($listing,'STNME').' '.
					   $this->get_node_value($listing,'STTYP');
		$db_data['street']          = $street_name;
        $db_data['suburb']          = $this->get_node_value($listing,'SUB');
        $db_data['state']           = $this->get_node_value($listing,'STATE');
        $db_data['postcode']        = $this->get_node_value($listing,'PCD');
        $db_data['country']         = 'AU';

        return $comma_seperated == true ? implode(", ", $db_data) : implode(" ", $db_data);
    }

}

